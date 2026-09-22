<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Gabungan Rekap SD + SMP dalam satu file Excel (2 sheet: "SD" dan "SMP"),
 * sesuai tata letak "Rekap_SD_dan_SMP_Des_2025_-_dummy.xlsx" yang
 * dikirim user (satu laporan, dua sheet, bukan dua file terpisah).
 */
class RekapSdSmpExport implements Export, WithMultipleSheets
{
    use Exportable;

    public function __construct(protected string $periode) {}

    public function sheets(): array
    {
        return [
            new class($this->periode) extends RekapSdExport implements WithTitle {
                public function title(): string
                {
                    return 'SD';
                }
            },
            new class($this->periode) extends RekapSmpExport implements WithTitle {
                public function title(): string
                {
                    return 'SMP';
                }
            },
        ];
    }
}
