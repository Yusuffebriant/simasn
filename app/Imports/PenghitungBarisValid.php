<?php

namespace App\Imports;

use App\Services\PegawaiNormalizer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Hanya MENGHITUNG baris file Excel yang lolos validasi dasar (nama + NIP/NIK,
 * bukan baris index palsu), memakai aturan yang sama dengan PegawaiImport.
 * Tidak menyimpan apa pun ke database. Dipakai ImportPengaman untuk
 * pratinjau sebelum data lama diganti.
 */
class PenghitungBarisValid implements ToCollection, WithHeadingRow, WithChunkReading
{
    public int $valid = 0;

    protected PegawaiNormalizer $normalizer;

    public function __construct()
    {
        $this->normalizer = new PegawaiNormalizer();
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $baris = array_change_key_case($row->toArray(), CASE_UPPER);

            [$valid] = $this->normalizer->isRowValid($baris);

            if ($valid) {
                $this->valid++;
            }
        }
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}