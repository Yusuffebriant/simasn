<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Satu file Excel rekap PPPK paruh waktu dengan 8 sheet:
 * per perangkat daerah, per pendidikan, per jenis jabatan, per usia,
 * perkiraan pensiun, rincian JF, jabatan terbanyak, dan persentase
 * per instansi.
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
            new class($this->periode, 'usia') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'Per Usia';
                }
            },
            new class($this->periode, 'pensiun') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'Perkiraan Pensiun';
                }
            },
            new class($this->periode, 'jf_rumpun') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'Rincian JF';
                }
            },
            new class($this->periode, 'jabatan') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'Jabatan Terbanyak';
                }
            },
            new class($this->periode) extends RekapParuhWaktuPersenExport implements WithTitle {
                public function title(): string
                {
                    return 'Persentase per Instansi';
                }
            },
        ];
    }
}