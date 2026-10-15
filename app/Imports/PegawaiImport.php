<?php

namespace App\Imports;

use App\Models\ImportBatch;
use App\Models\ImportBatchError;
use App\Models\Pegawai;
use App\Models\PegawaiDetail;
use App\Services\PegawaiNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\BeforeImport;

/**
 * Import data pegawai dari file Excel (dibaca per chunk 1000 baris).
 *
 * Selain menyimpan data, class ini mencatat dua hal supaya hasil import
 * bisa dijelaskan, bukan hanya berupa angka:
 *
 *  1. Baris GAGAL  -> masuk tabel import_batch_errors beserta alasannya
 *     (nama kosong, NIK bukan 16 digit, STATUS_KEPEGAWAIAN tidak dikenali, dst).
 *
 *  2. Baris GANDA  -> NIP atau NIK yang sama muncul lebih dari sekali di
 *     file. Tabel pegawai punya kunci unik NIP, jadi baris yang belakangan
 *     MENIMPA baris sebelumnya: kedua baris dihitung "berhasil", tetapi di
 *     database hanya ada 1 pegawai. Inilah penyebab jumlah di sistem lebih
 *     sedikit daripada jumlah baris di Excel padahal tidak ada yang gagal.
 *     Detailnya dicatat di $ganda (lihat gandaDetail()).
 *
 *  3. Pegawai TIDAK AKTIF -> kolom KEDUDUKAN_KEPEGAWAIAN selain AKTIF
 *     (MENINGGAL, MUTASI KELUAR, PENSIUN). Barisnya TETAP tersimpan di
 *     database, tetapi seluruh rekap/dashboard hanya menghitung pegawai
 *     berstatus aktif. Inilah penyebab jumlah di sistem lebih sedikit
 *     daripada jumlah baris di Excel padahal tidak ada baris yang gagal
 *     atau ganda. Detailnya dicatat di $tidakAktif (lihat tidakAktifDetail()).
 *
 * Nomor baris pada catatan = nomor baris di Excel (baris 1 = judul kolom,
 * baris 2 biasanya baris nomor kolom 1,2,3,... yang dilewati otomatis).
 */
class PegawaiImport implements ToCollection, WithHeadingRow, WithChunkReading, WithEvents
{
    /** Batas jumlah rincian baris ganda yang disimpan (angka totalnya tetap akurat). */
    private const BATAS_RINCIAN_GANDA = 200;

    protected PegawaiNormalizer $normalizer;
    protected int $barisKe = 1;

    /** NIP / NIK yang sudah tersimpan dari file ini => nomor baris Excel-nya. */
    protected array $nipDilihat = [];
    protected array $nikDilihat = [];

    /** Rincian baris ganda: baris, baris_sebelumnya, nama, nip, alasan. */
    protected array $ganda = [];

    /** Pegawai berstatus tidak aktif (tersimpan, tapi tidak ikut rekap). */
    protected array $tidakAktif = [];
    protected int $tidakAktifJumlah = 0;

    /**
     * Hitungan isi file (baris yang berhasil diproses, termasuk yang ganda),
     * dibandingkan dengan isi database oleh ImportRekonsiliasi.
     * Bentuk: [kategori => [nilai => jumlah]], nilai kosong ditulis '_null'.
     */
    protected array $tally = [
        'total' => 0,
        'status_kepegawaian' => [],
        'status_aktif' => [],
        'status_perkawinan' => [],
        'agama' => [],
        'golongan' => [],
    ];

    public function __construct(protected ImportBatch $batch)
    {
        $this->normalizer = new PegawaiNormalizer();
    }

    /**
     * Simpan total baris di awal supaya frontend bisa menghitung persen progres.
     */
    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                $totals = $event->getReader()->getTotalRows();
                $totalSheet = (int) (reset($totals) ?: 0);
                $total = max(0, $totalSheet - 1); // dikurangi baris heading

                DB::table('import_batches')
                    ->where('id', $this->batch->id)
                    ->update(['total_baris' => $total]);
            },
        ];
    }

    /**
     * Menentukan jumlah baris per chunk
     */
    public function chunkSize(): int
    {
        return 1000; // Bisa diubah sesuai kebutuhan
    }

    public function collection(Collection $rows): void
    {
        $berhasilChunk = 0;
        $gagalChunk = 0;
        $dilindungiChunk = 0;

        foreach ($rows as $row) {
            $this->barisKe++;

            $rowArray = array_change_key_case(
                $row->toArray(),
                CASE_UPPER
            );

            [$valid, $pesan] = $this->normalizer->isRowValid($rowArray);

            if (!$valid) {
                // Baris "index nomor kolom" (mis. 1,2,3,4,...) bukan kesalahan data
                // yang perlu ditinjau manual — itu baris bawaan format export SIMPEG,
                // jadi dilewati diam-diam, TIDAK dihitung sebagai gagal.
                if ($pesan === 'Baris terdeteksi sebagai index palsu, bukan data pegawai') {
                    continue;
                }
                $dilindungi = $this->lindungiDataLama($rowArray);
                if ($dilindungi) {
                    $dilindungiChunk++;
                }
                $this->catatError(
                    $rowArray,
                    $pesan . ($dilindungi ? ' (data lama pegawai ini dipertahankan)' : '')
                );
                $gagalChunk++;

                continue;
            }

            try {
                $hasil = $this->normalizer->normalisasiBaris($rowArray);

                // Cek dulu apakah NIP/NIK ini sudah muncul di baris sebelumnya
                // pada file yang sama (dicatat setelah baris berhasil disimpan).
                $gandaInfo = $this->cekGanda($hasil['pegawai']);

                DB::transaction(function () use ($hasil) {
                    $data = array_merge(
                        $hasil['pegawai'],
                        ['raw_import_id' => $this->batch->id]
                    );

                    $pegawai = null;

                    // Cari berdasarkan NIP dulu, lalu NIK.
                    if (!empty($data['nip'])) {
                        $pegawai = Pegawai::where('nip', $data['nip'])->first();
                    }

                    if (!$pegawai && !empty($data['nik'])) {
                        $pegawai = Pegawai::where('nik', $data['nik'])->first();
                    }

                    if ($pegawai) {
                        // Jangan menimpa NIP lama dengan null kalau file tidak membawa NIP.
                        if (empty($data['nip'])) {
                            unset($data['nip']);
                        }
                        $pegawai->fill($data)->save();
                    } else {
                        $pegawai = Pegawai::create($data);
                    }

                    PegawaiDetail::updateOrCreate(
                        ['pegawai_id' => $pegawai->id],
                        $hasil['detail']
                    );
                });

                $this->catatBerhasil($hasil, $gandaInfo);

                $berhasilChunk++;
            } catch (\Throwable $e) {
                // Termasuk InvalidArgumentException dari normalisasiBaris()
                // (STATUS_KEPEGAWAIAN tidak dikenali) -> masuk import_batch_errors.
                $dilindungi = $this->lindungiDataLama($rowArray);
                if ($dilindungi) {
                    $dilindungiChunk++;
                }
                $this->catatError(
                    $rowArray,
                    $e->getMessage() . ($dilindungi ? ' (data lama pegawai ini dipertahankan)' : '')
                );

                $gagalChunk++;
            }
        }

        DB::table('import_batches')
            ->where('id', $this->batch->id)
            ->update([
                'berhasil' => DB::raw(
                    "berhasil + {$berhasilChunk}"
                ),
                'gagal' => DB::raw(
                    "gagal + {$gagalChunk}"
                ),
                'jumlah_dilindungi' => DB::raw(
                    "jumlah_dilindungi + {$dilindungiChunk}"
                ),
            ]);
    }

    /**
     * Hitungan isi file untuk rekonsiliasi (lihat ImportRekonsiliasi).
     */
    public function tally(): array
    {
        return $this->tally;
    }

    /**
     * Rincian baris yang saling menimpa karena NIP/NIK sama.
     *
     * @return array<int, array{baris:int, baris_sebelumnya:int, nama:string, nip:?string, alasan:string}>
     */
    public function gandaDetail(): array
    {
        return $this->ganda;
    }

    /** Jumlah pegawai berstatus tidak aktif pada file ini. */
    public function tidakAktifJumlah(): int
    {
        return $this->tidakAktifJumlah;
    }

    /**
     * Rincian pegawai tidak aktif (meninggal / mutasi keluar / pensiun).
     *
     * @return array<int, array{baris:int, nama:string, nip:?string, status_kepegawaian:?string, unit:?string, status_aktif:string}>
     */
    public function tidakAktifDetail(): array
    {
        return $this->tidakAktif;
    }

    /**
     * Apakah NIP/NIK baris ini sudah dipakai baris lain di file yang sama?
     * Mengembalikan null kalau tidak ganda.
     */
    protected function cekGanda(array $pegawai): ?array
    {
        $nip = $pegawai['nip'] ?? null;
        $nik = $pegawai['nik'] ?? null;

        $sebabNip = !empty($nip) && isset($this->nipDilihat[$nip]);
        $sebabNik = !empty($nik) && isset($this->nikDilihat[$nik]);

        if (!$sebabNip && !$sebabNik) {
            return null;
        }

        return [
            'baris_sebelumnya' => $sebabNip ? $this->nipDilihat[$nip] : $this->nikDilihat[$nik],
            'alasan' => match (true) {
                $sebabNip && $sebabNik => 'memakai NIP dan NIK yang sama',
                $sebabNip => 'memakai NIP yang sama',
                default => 'memakai NIK yang sama',
            },
        ];
    }

    /**
     * Dipanggil setelah satu baris sukses disimpan: tandai NIP/NIK sebagai
     * sudah dipakai, simpan catatan baris ganda, dan tambah hitungan isi file.
     */
    protected function catatBerhasil(array $hasil, ?array $gandaInfo): void
    {
        $pegawai = $hasil['pegawai'];

        if (!empty($pegawai['nip'])) {
            $this->nipDilihat[$pegawai['nip']] = $this->barisKe;
        }
        if (!empty($pegawai['nik'])) {
            $this->nikDilihat[$pegawai['nik']] = $this->barisKe;
        }

        if ($gandaInfo !== null && count($this->ganda) < self::BATAS_RINCIAN_GANDA) {
            $this->ganda[] = [
                'baris' => $this->barisKe,
                'baris_sebelumnya' => $gandaInfo['baris_sebelumnya'],
                'nama' => $pegawai['nama'] ?? '',
                'nip' => $pegawai['nip'] ?? null,
                'alasan' => $gandaInfo['alasan'],
            ];
        }

        if (($pegawai['status_aktif'] ?? 'aktif') !== 'aktif') {
            $this->tidakAktifJumlah++;

            if (count($this->tidakAktif) < self::BATAS_RINCIAN_GANDA) {
                $this->tidakAktif[] = [
                    'baris' => $this->barisKe,
                    'nama' => $pegawai['nama'] ?? '',
                    'nip' => $pegawai['nip'] ?? null,
                    'status_kepegawaian' => $pegawai['status_kepegawaian'] ?? null,
                    'unit' => $pegawai['unit'] ?? null,
                    // aktif | pensiun | mutasi_keluar | meninggal
                    'status_aktif' => $pegawai['status_aktif'],
                ];
            }
        }

        $this->tally['total']++;
        $this->tambahTally('status_kepegawaian', $pegawai['status_kepegawaian'] ?? null);
        $this->tambahTally('status_aktif', $pegawai['status_aktif'] ?? null);
        $this->tambahTally('agama', $pegawai['agama_id'] ?? null);
        $this->tambahTally('golongan', $pegawai['golongan_ruang_id'] ?? null);
        $this->tambahTally('status_perkawinan', $hasil['detail']['status_perkawinan'] ?? null);
    }

    protected function tambahTally(string $kategori, $nilai): void
    {
        $kunci = ($nilai === null || $nilai === '') ? '_null' : (string) $nilai;
        $this->tally[$kategori][$kunci] = ($this->tally[$kategori][$kunci] ?? 0) + 1;
    }

    /**
     * Baris yang gagal diimpor tidak boleh membuat pegawai lamanya ikut
     * terhapus saat mode ganti total. Data lamanya (kalau ada, dicocokkan
     * lewat NIP/NIK) ditandai ikut batch ini supaya tidak dihapus.
     */
    protected function lindungiDataLama(array $rowArray): bool
    {
        $nip = trim((string) ($rowArray['NIP'] ?? ''));
        $nik = $this->normalizer->normalisasiNik($rowArray['NIK'] ?? null);

        if ($nip === '' && $nik === null) {
            return false;
        }

        return DB::table('pegawai')
            ->where(function ($w) use ($nip, $nik) {
                if ($nip !== '') {
                    $w->orWhere('nip', $nip);
                }
                if ($nik !== null) {
                    $w->orWhere('nik', $nik);
                }
            })
            ->update(['raw_import_id' => $this->batch->id]) > 0;
    }

    protected function catatError(
        array $rowArray,
        ?string $pesan
    ): void {
        ImportBatchError::create([
            'import_batch_id' => $this->batch->id,
            'baris_ke' => $this->barisKe,
            'pesan' => $pesan ?? 'Kesalahan tidak diketahui',
            'data_mentah' => $rowArray,
        ]);
    }
}