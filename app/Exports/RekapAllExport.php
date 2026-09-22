<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class RekapAllExport implements Export, WithMultipleSheets
{
    use Exportable;

    public function __construct(protected string $periode) {}

    public function sheets(): array
    {
        return [
            new class($this->periode) extends RekapAgamaExport implements WithTitle {
                public function title(): string
                {
                    return 'Rekap Agama';
                }
            },
            new class($this->periode) extends RekapPendidikanExport implements WithTitle {
                public function title(): string
                {
                    return 'Rekap Pendidikan';
                }
            },
            new class($this->periode) extends RekapGolonganExport implements WithTitle {
                public function title(): string
                {
                    return 'Rekap Golongan';
                }
            },
            new class($this->periode) extends RekapJabatanExport implements WithTitle {
                public function title(): string
                {
                    return 'Rekap Jabatan';
                }
            },
            new class($this->periode) extends RekapEselonGolonganGenderExport implements WithTitle {
                public function title(): string
                {
                    return 'Rekap Eselon & Golongan';
                }
            },
            new class($this->periode) extends RekapNakesExport implements WithTitle {
                public function title(): string
                {
                    return 'Rekap Nakes';
                }
            },
            new class($this->periode) extends RekapSdExport implements WithTitle {
                public function title(): string
                {
                    return 'Rekap SD';
                }
            },
            new class($this->periode) extends RekapSmpExport implements WithTitle {
                public function title(): string
                {
                    return 'Rekap SMP';
                }
            },
            new class($this->periode) extends RekapKecamatanExport implements WithTitle {
                public function title(): string
                {
                    return 'Rekap Kecamatan-Kelurahan';
                }
            },
        ];
    }
}
