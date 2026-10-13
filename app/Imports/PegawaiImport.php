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

class PegawaiImport implements ToCollection, WithHeadingRow, WithChunkReading, WithEvents
{
    protected PegawaiNormalizer $normalizer;
    protected int $barisKe = 1;

    /**
     * Rekap apa yang dibaca dari file (hanya baris yang sukses tersimpan).
     * Dipakai ImportRekonsiliasi untuk dibandingkan dengan isi database.
     * Kunci null dicatat sebagai '_null'.
     */
    protected array $tally = [
        'total' => 0,
        'status_kepegawaian' => [],
        'status_aktif' => [],
        'status_perkawinan' => [],
        'agama' => [],
        'golongan' => [],
    ];

    public function tally(): array
    {
        return $this->tally;
    }

    protected function catatTally(array $hasil): void
    {
        $tambah = function (string $kategori, $nilai): void {
            $kunci = ($nilai === null || $nilai === '') ? '_null' : (string) $nilai;
            $this->tally[$kategori][$kunci] = ($this->tally[$kategori][$kunci] ?? 0) + 1;
        };

        $this->tally['total']++;
        $tambah('status_kepegawaian', $hasil['pegawai']['status_kepegawaian'] ?? null);
        $tambah('status_aktif', $hasil['pegawai']['status_aktif'] ?? null);
        $tambah('agama', $hasil['pegawai']['agama_id'] ?? null);
        $tambah('golongan', $hasil['pegawai']['golongan_ruang_id'] ?? null);
        $tambah('status_perkawinan', $hasil['detail']['status_perkawinan'] ?? null);
    }

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
                $this->catatError($rowArray, $pesan);
                $gagalChunk++;

                continue;
            }

            try {
                $hasil = $this->normalizer->normalisasiBaris($rowArray);

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

                $this->catatTally($hasil);
                $berhasilChunk++;
            } catch (\Throwable $e) {
                // Termasuk InvalidArgumentException dari normalisasiBaris()
                // (STATUS_KEPEGAWAIAN tidak dikenali) -> masuk import_batch_errors.
                $this->catatError(
                    $rowArray,
                    $e->getMessage()
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
            ]);
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