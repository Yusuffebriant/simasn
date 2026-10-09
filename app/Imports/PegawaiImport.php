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
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PegawaiImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected PegawaiNormalizer $normalizer;
    protected int $barisKe = 1;

    public function __construct(protected ImportBatch $batch)
    {
        $this->normalizer = new PegawaiNormalizer();
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