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

    public function collection(Collection $rows)
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
                $this->catatError($rowArray, $pesan);
                $gagalChunk++;

                continue;
            }

            try {
                $hasil = $this->normalizer->normalisasiBaris($rowArray);

                DB::transaction(function () use ($hasil) {
                    $pegawai = Pegawai::updateOrCreate(
                        ['nip' => $hasil['pegawai']['nip']],
                        array_merge(
                            $hasil['pegawai'],
                            ['raw_import_id' => $this->batch->id]
                        )
                    );

                    PegawaiDetail::updateOrCreate(
                        ['pegawai_id' => $pegawai->id],
                        $hasil['detail']
                    );
                });

                $berhasilChunk++;
            } catch (\Throwable $e) {
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