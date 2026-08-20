<?php

namespace App\Imports;

use App\Models\ImportBatch;
use App\Models\ImportBatchError;
use App\Models\Pegawai;
use App\Models\PegawaiDetail;
use App\Services\PegawaiNormalizer;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PegawaiImport implements ToCollection, WithHeadingRow, WithChunkReading, WithBatchInserts
{
    protected PegawaiNormalizer $normalizer;
    protected int $berhasil = 0;
    protected int $gagal = 0;
    protected int $barisKe = 1;

    public function __construct(protected ImportBatch $batch)
    {
        $this->normalizer = new PegawaiNormalizer();
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->barisKe++;
            $rowArray = $row->toArray();
            $rowArray = array_change_key_case($rowArray, CASE_UPPER);

            [$valid, $pesan] = $this->normalizer->isRowValid($rowArray);

            if (!$valid) {
                $this->catatError($rowArray, $pesan);
                continue;
            }

            try {
                $hasil = $this->normalizer->normalisasiBaris($rowArray);

                DB::transaction(function () use ($hasil) {
                    $pegawai = Pegawai::updateOrCreate(
                        ['nip' => $hasil['pegawai']['nip']],
                        array_merge($hasil['pegawai'], [
                            'raw_import_id' => $this->batch->id,
                        ])
                    );

                    PegawaiDetail::updateOrCreate(
                        ['pegawai_id' => $pegawai->id],
                        $hasil['detail']
                    );
                });

                $this->berhasil++;
            } catch (\Throwable $e) {
                $this->catatError($rowArray, $e->getMessage());
            }
        }

        $this->batch->update([
            'berhasil' => $this->berhasil,
            'gagal' => $this->gagal,
        ]);
    }

    protected function catatError(array $rowArray, ?string $pesan): void
    {
        $this->gagal++;
        ImportBatchError::create([
            'import_batch_id' => $this->batch->id,
            'baris_ke' => $this->barisKe,
            'pesan' => $pesan ?? 'Kesalahan tidak diketahui',
            'data_mentah' => $rowArray,
        ]);
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 500;
    }
}