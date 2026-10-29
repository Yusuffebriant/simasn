<?php

namespace App\Imports;

use App\Models\ImportBatch;
use App\Models\ImportBatchError;
use App\Models\Pegawai;
use App\Models\PegawaiDetail;
use App\Services\PegawaiNormalizer;
use App\Support\AlasanImport;
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
 *  4. Pegawai tersimpan tapi ISIAN WAJIB KOSONG -> dicatat per pegawai beserta
 *     sebabnya (kolom tidak ada di file, sel kosong, atau isi tidak dikenali),
 *     lihat AlasanImport dan kosongDetail(). Supaya admin tahu persis apa yang
 *     kurang dan kenapa, bukan hanya angka "belum lengkap".
 *
 *  5. Sel berisi RUMUS Excel -> sel yang isinya rumus (=VLOOKUP(...) atau
 *     ="1234...") dicatat per kolom beserta contoh barisnya, lihat
 *     catatRumus() dan rumusRingkas(). Import membaca teks rumusnya, bukan
 *     nilai hasilnya, sehingga isian bisa gagal disimpan atau tersimpan salah
 *     (mis. NIP menjadi 21 karakter). Perbaikannya di Excel: Paste Special > Values.
 *
 * Nomor baris pada catatan = nomor baris di Excel (baris 1 = judul kolom,
 * baris 2 biasanya baris nomor kolom 1,2,3,... yang dilewati otomatis).
 */
class PegawaiImport implements ToCollection, WithHeadingRow, WithChunkReading, WithEvents
{
    /** Batas jumlah rincian baris ganda yang disimpan (angka totalnya tetap akurat). */
    private const BATAS_RINCIAN_GANDA = 200;

    /**
     * Batas rincian baris ganda (lebih longgar karena dipakai untuk unduhan
     * Excel dan daftar lengkap di Riwayat Import). Jumlah totalnya tetap akurat.
     */
    private const BATAS_RINCIAN_GANDA_LENGKAP = 2000;

    protected PegawaiNormalizer $normalizer;
    protected int $barisKe = 1;

    /** NIP / NIK yang sudah tersimpan dari file ini => nomor baris Excel-nya. */
    protected array $nipDilihat = [];
    protected array $nikDilihat = [];

    /**
     * Siapa yang memegang NIP/NIK itu di baris sebelumnya: 'nip:123' / 'nik:456'
     * => [baris, nama, nip, nik]. Dipakai untuk menyebut siapa yang tertimpa.
     */
    protected array $pemegangIdentitas = [];

    /**
     * Rincian baris ganda: baris, baris_sebelumnya, nama, nip, nik, alasan,
     * nama_sebelumnya, nip_sebelumnya, nik_sebelumnya, nik_nama_beda.
     */
    protected array $ganda = [];

    /** Jumlah baris ganda yang NIK-nya sama tetapi namanya berbeda (semua baris). */
    protected int $gandaNikBedaNamaJumlah = 0;

    /** Pegawai tersimpan dengan isian wajib kosong: rincian (dibatasi) dan jumlah total. */
    protected array $kosong = [];
    protected int $kosongJumlah = 0;

    /**
     * Ringkasan sebab isian kosong untuk SEMUA baris (bukan hanya yang masuk
     * rincian): [label => [sebab => jumlah]] dan nilai Excel yang tidak dikenali
     * [label => [nilai => jumlah]].
     */
    protected array $kosongSebab = [];
    protected array $kosongNilai = [];

    /**
     * Sel berisi rumus Excel per kolom (dihitung dari SEMUA baris, termasuk yang
     * gagal): [kolom => [jumlah, per_jenis[jenis => n], contoh_isi, contoh_baris[]]]
     * dan jumlah baris yang punya minimal satu sel berrumus.
     */
    protected array $rumus = [];
    protected int $rumusBaris = 0;

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

                // DIAGNOSA SEMENTARA (boleh dihapus): jumlah baris menurut Excel.
                // Angka jutaan (mis. 1048576) berarti ada baris kosong berformat.
                \Illuminate\Support\Facades\Log::warning(sprintf(
                    '[MEMORI] batch %d Excel melaporkan %d baris (heading ikut dihitung), memori sekarang %.1f MB',
                    $this->batch->id,
                    $totalSheet,
                    memory_get_usage() / 1048576
                ));

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

        // DIAGNOSA SEMENTARA (boleh dihapus): memori saat potongan baris diterima.
        \Illuminate\Support\Facades\Log::warning(sprintf(
            '[MEMORI] batch %d potongan mulai di baris %d (%d baris diterima): sekarang %.1f MB, puncak %.1f MB',
            $this->batch->id,
            $this->barisKe + 1,
            $rows->count(),
            memory_get_usage() / 1048576,
            memory_get_peak_usage() / 1048576
        ));

        foreach ($rows as $row) {
            $this->barisKe++;

            $rowArray = array_change_key_case(
                $row->toArray(),
                CASE_UPPER
            );

            $this->catatRumus($rowArray);

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
                $this->catatKosong($rowArray, $hasil);

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

        // DIAGNOSA SEMENTARA (boleh dihapus)
        \Illuminate\Support\Facades\Log::warning(sprintf(
            '[MEMORI] batch %d potongan selesai di baris %d (berhasil %d, gagal %d): sekarang %.1f MB, puncak %.1f MB',
            $this->batch->id,
            $this->barisKe,
            $berhasilChunk,
            $gagalChunk,
            memory_get_usage() / 1048576,
            memory_get_peak_usage() / 1048576
        ));
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

    /**
     * Jumlah baris yang NIK-nya sama dengan baris sebelumnya tetapi namanya
     * berbeda: pegawai di baris sebelumnya tertimpa dan hilang dari database.
     */
    public function gandaNikBedaNamaJumlah(): int
    {
        return $this->gandaNikBedaNamaJumlah;
    }

    /** Jumlah pegawai tersimpan yang punya isian wajib kosong. */
    public function kosongJumlah(): int
    {
        return $this->kosongJumlah;
    }

    /**
     * Rincian per pegawai: baris, nama, nip, nik, dan daftar isian yang kosong
     * beserta sebabnya (lihat AlasanImport::diagnosaKosong()).
     */
    public function kosongDetail(): array
    {
        return $this->kosong;
    }

    /**
     * Ringkasan sebab per isian untuk seluruh file, dan nilai Excel yang paling
     * sering tidak dikenali.
     *
     * @return array<int, array{label:string,kosong_di_file:int,kolom_tidak_ada:int,tidak_dikenali:int,nilai_tidak_dikenali:array}>
     */
    public function kosongRingkas(): array
    {
        $hasil = [];

        foreach ($this->kosongSebab as $label => $sebab) {
            $nilai = $this->kosongNilai[$label] ?? [];
            arsort($nilai);

            $hasil[] = [
                'label' => $label,
                'kosong_di_file' => $sebab[AlasanImport::SEBAB_KOSONG_DI_FILE] ?? 0,
                'kolom_tidak_ada' => $sebab[AlasanImport::SEBAB_KOLOM_TIDAK_ADA] ?? 0,
                'tidak_dikenali' => $sebab[AlasanImport::SEBAB_TIDAK_DIKENALI] ?? 0,
                'nilai_tidak_dikenali' => collect(array_slice($nilai, 0, 5, true))
                    ->map(fn ($jumlah, $isi) => ['nilai' => (string) $isi, 'jumlah' => $jumlah])
                    ->values()
                    ->all(),
            ];
        }

        usort($hasil, fn ($a, $b) => array_sum([$b['kosong_di_file'], $b['kolom_tidak_ada'], $b['tidak_dikenali']])
            <=> array_sum([$a['kosong_di_file'], $a['kolom_tidak_ada'], $a['tidak_dikenali']]));

        return $hasil;
    }

    /** Jumlah baris di file yang punya minimal satu sel berisi rumus Excel. */
    public function rumusJumlahBaris(): int
    {
        return $this->rumusBaris;
    }

    /**
     * Ringkasan sel berisi rumus per kolom, terbanyak lebih dulu.
     * jenis: teks_dibungkus (="123") | rumus (mis. =VLOOKUP) | campuran.
     *
     * @return array<int, array{kolom:string,jumlah:int,jenis:string,contoh_isi:string,contoh_baris:array<int,int>}>
     */
    public function rumusRingkas(): array
    {
        $hasil = [];

        foreach ($this->rumus as $kolom => $r) {
            $jenis = count($r['per_jenis']) > 1 ? 'campuran' : (array_key_first($r['per_jenis']) ?? 'rumus');

            $hasil[] = [
                'kolom' => (string) $kolom,
                'jumlah' => $r['jumlah'],
                'jenis' => $jenis,
                'contoh_isi' => $r['contoh_isi'],
                'contoh_baris' => $r['contoh_baris'],
            ];
        }

        usort($hasil, fn ($a, $b) => $b['jumlah'] <=> $a['jumlah']);

        return $hasil;
    }

    /**
     * Catat sel berisi rumus Excel pada satu baris. Hanya pencatatan: kegagalan
     * di sini tidak boleh membuat baris dianggap gagal.
     */
    protected function catatRumus(array $rowArray): void
    {
        try {
            $rumus = AlasanImport::kolomBerumus($rowArray);

            if ($rumus === []) {
                return;
            }

            $this->rumusBaris++;

            foreach ($rumus as $kolom => $teks) {
                $jenis = AlasanImport::jenisRumus($teks);

                $this->rumus[$kolom] ??= [
                    'jumlah' => 0,
                    'per_jenis' => [],
                    'contoh_isi' => mb_substr($teks, 0, 80),
                    'contoh_baris' => [],
                ];

                $this->rumus[$kolom]['jumlah']++;
                $this->rumus[$kolom]['per_jenis'][$jenis] = ($this->rumus[$kolom]['per_jenis'][$jenis] ?? 0) + 1;

                if (count($this->rumus[$kolom]['contoh_baris']) < 5) {
                    $this->rumus[$kolom]['contoh_baris'][] = $this->barisKe;
                }
            }
        } catch (\Throwable $e) {
            // Abaikan: ini hanya catatan tambahan.
        }
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

        // Yang tertimpa: penyimpanan mencari NIP dulu, baru NIK (lihat collection()).
        $pemegang = $sebabNip
            ? ($this->pemegangIdentitas['nip:' . $nip] ?? null)
            : ($this->pemegangIdentitas['nik:' . $nik] ?? null);

        $namaSebelumnya = $pemegang['nama'] ?? null;

        return [
            'baris_sebelumnya' => $sebabNip ? $this->nipDilihat[$nip] : $this->nikDilihat[$nik],
            'alasan' => match (true) {
                $sebabNip && $sebabNik => 'memakai NIP dan NIK yang sama',
                $sebabNip => 'memakai NIP yang sama',
                default => 'memakai NIK yang sama',
            },
            'nama_sebelumnya' => $namaSebelumnya,
            'nip_sebelumnya' => $pemegang['nip'] ?? null,
            'nik_sebelumnya' => $pemegang['nik'] ?? null,
            // NIK sama tetapi nama berbeda: kemungkinan salah ketik NIK, atau
            // dua orang berbeda sehingga salah satunya hilang dari sistem.
            'nik_nama_beda' => $sebabNik
                && $namaSebelumnya !== null
                && !$this->samaNama($namaSebelumnya, $pegawai['nama'] ?? null),
        ];
    }

    /**
     * Bandingkan dua nama tanpa memedulikan huruf besar/kecil, spasi, tanda
     * baca, dan gelar belakang (teks setelah koma, mis. "BUDI, S.Pd").
     * Gelar depan (mis. "DR.") tidak dibuang, jadi bisa tertandai berbeda.
     */
    protected function samaNama(?string $a, ?string $b): bool
    {
        return $this->kunciNama($a) === $this->kunciNama($b);
    }

    protected function kunciNama(?string $nama): string
    {
        $nama = mb_strtoupper((string) $nama);
        $nama = preg_replace('/,.*$/u', '', $nama) ?? $nama;

        return preg_replace('/[^\p{L}]+/u', '', $nama) ?? '';
    }

    /**
     * Dipanggil setelah satu baris sukses disimpan: tandai NIP/NIK sebagai
     * sudah dipakai, simpan catatan baris ganda, dan tambah hitungan isi file.
     */
    protected function catatBerhasil(array $hasil, ?array $gandaInfo): void
    {
        $pegawai = $hasil['pegawai'];

        if ($gandaInfo !== null) {
            if ($gandaInfo['nik_nama_beda']) {
                $this->gandaNikBedaNamaJumlah++;
            }

            if (count($this->ganda) < self::BATAS_RINCIAN_GANDA_LENGKAP) {
                $this->ganda[] = [
                    'baris' => $this->barisKe,
                    'baris_sebelumnya' => $gandaInfo['baris_sebelumnya'],
                    'nama' => $pegawai['nama'] ?? '',
                    'nip' => $pegawai['nip'] ?? null,
                    'nik' => $pegawai['nik'] ?? null,
                    'alasan' => $gandaInfo['alasan'],
                    'nama_sebelumnya' => $gandaInfo['nama_sebelumnya'],
                    'nip_sebelumnya' => $gandaInfo['nip_sebelumnya'],
                    'nik_sebelumnya' => $gandaInfo['nik_sebelumnya'],
                    'nik_nama_beda' => $gandaInfo['nik_nama_beda'],
                ];
            }
        }

        // Catat pemegang NIP/NIK ini SETELAH pengecekan ganda, supaya baris
        // berikutnya tahu siapa yang akan ia timpa.
        $pemegang = [
            'baris' => $this->barisKe,
            'nama' => $pegawai['nama'] ?? '',
            'nip' => $pegawai['nip'] ?? null,
            'nik' => $pegawai['nik'] ?? null,
        ];

        if (!empty($pegawai['nip'])) {
            $this->nipDilihat[$pegawai['nip']] = $this->barisKe;
            $this->pemegangIdentitas['nip:' . $pegawai['nip']] = $pemegang;
        }
        if (!empty($pegawai['nik'])) {
            $this->nikDilihat[$pegawai['nik']] = $this->barisKe;
            $this->pemegangIdentitas['nik:' . $pegawai['nik']] = $pemegang;
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

    /**
     * Catat isian wajib yang kosong pada baris yang baru disimpan. Hanya
     * pencatatan: kegagalan di sini tidak boleh membuat baris dianggap gagal.
     */
    protected function catatKosong(array $rowArray, array $hasil): void
    {
        try {
            $kurang = AlasanImport::diagnosaKosong($rowArray, $hasil['pegawai'], $hasil['detail']);

            if ($kurang === []) {
                return;
            }

            $this->kosongJumlah++;

            foreach ($kurang as $k) {
                $this->kosongSebab[$k['label']][$k['sebab']] = ($this->kosongSebab[$k['label']][$k['sebab']] ?? 0) + 1;

                // Batasi variasi nilai yang diingat supaya memori tetap kecil.
                if ($k['sebab'] === AlasanImport::SEBAB_TIDAK_DIKENALI && $k['nilai'] !== null
                    && (isset($this->kosongNilai[$k['label']][$k['nilai']]) || count($this->kosongNilai[$k['label']] ?? []) < 50)) {
                    $this->kosongNilai[$k['label']][$k['nilai']] = ($this->kosongNilai[$k['label']][$k['nilai']] ?? 0) + 1;
                }
            }

            if (count($this->kosong) < self::BATAS_RINCIAN_GANDA) {
                $pegawai = $hasil['pegawai'];

                $this->kosong[] = [
                    'baris' => $this->barisKe,
                    'nama' => $pegawai['nama'] ?? '',
                    'nip' => $pegawai['nip'] ?? null,
                    'nik' => $pegawai['nik'] ?? null,
                    'kurang' => array_map(fn ($k) => [
                        'label' => $k['label'],
                        'sebab' => $k['sebab'],
                        'keterangan' => $k['keterangan'],
                    ], $kurang),
                ];
            }
        } catch (\Throwable $e) {
            // Abaikan: ini hanya catatan tambahan.
        }
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