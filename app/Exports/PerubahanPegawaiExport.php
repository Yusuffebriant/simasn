<?php

namespace App\Exports;

use App\Models\ImportBatch;
use App\Models\ImportPerubahan;
use App\Services\PerubahanPegawaiService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export daftar perubahan pegawai satu import ke Excel
 * (satu baris per perubahan, urut jenis lalu nama).
 */
class PerubahanPegawaiExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function __construct(protected ImportBatch $batch) {}

    public function headings(): array
    {
        return ['No', 'Jenis Perubahan', 'Nama', 'NIP', 'Status', 'Unit', 'Dari', 'Menjadi', 'Keterangan'];
    }

    public function array(): array
    {
        $urutan = array_keys(PerubahanPegawaiService::LABEL);

        $rows = ImportPerubahan::where('import_batch_id', $this->batch->id)
            ->get()
            ->sortBy([
                fn ($a, $b) => array_search($a->jenis, $urutan) <=> array_search($b->jenis, $urutan),
                fn ($a, $b) => strcasecmp($a->nama, $b->nama),
            ]);

        $hasil = [];
        $no = 1;

        foreach ($rows as $r) {
            $hasil[] = [
                $no++,
                PerubahanPegawaiService::LABEL[$r->jenis] ?? $r->jenis,
                $r->nama,
                // Tanda kutip depan supaya Excel tidak mengubah NIP jadi notasi ilmiah.
                $r->nip ? "'" . $r->nip : '',
                $r->status_label,
                $r->unit,
                $r->dari,
                $r->ke,
                $r->keterangan,
            ];
        }

        return $hasil;
    }
}