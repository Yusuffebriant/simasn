<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Satu file Excel rekap PPPK paruh waktu dengan 3 sheet:
 * per perangkat daerah, per pendidikan, dan per jenis jabatan.
 * Dipakai oleh endpoint /rekap/paruh-waktu/export.
 */
class RekapParuhWaktuLengkapExport implements WithMultipleSheets
{
    public function __construct(protected string $periode) {}

    public function sheets(): array
    {
        return [
            new class($this->periode, 'instansi') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'Per Perangkat Daerah';
                }
            },
            new class($this->periode, 'pendidikan') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'Per Pendidikan';
                }
            },
            new class($this->periode, 'jenis_jabatan') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'Per Jenis Jabatan';
                }
            },
        ];
    }
}