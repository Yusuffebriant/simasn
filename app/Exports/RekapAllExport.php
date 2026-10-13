<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;
use Maatwebsite\Excel\Concerns\WithTitle;

class RekapAllExport implements Export, WithMultipleSheets, WithPreCalculateFormulas
{
    use Exportable;

    public function __construct(protected string $periode) {}

    public function sheets(): array
    {
        return [
            new class($this->periode) extends RekapStatusKepegawaianExport implements WithTitle {
                public function title(): string
                {
                    return 'Rekap Status Kepegawaian';
                }
            },
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
            new class($this->periode) extends RekapStrukturGolonganExport implements WithTitle {
                public function title(): string
                {
                    return 'Struktural per Golongan';
                }
            },
            new class($this->periode) extends RekapStrukturEselonExport implements WithTitle {
                public function title(): string
                {
                    return 'Struktural per Eselon';
                }
            },
            new class($this->periode) extends RekapJfTertentuExport implements WithTitle {
                public function title(): string
                {
                    return 'JF Tertentu';
                }
            },
            new class($this->periode) extends RekapJfPelaksanaExport implements WithTitle {
                public function title(): string
                {
                    return 'JF Pelaksana';
                }
            },
            new class($this->periode) extends RekapPensiunExport implements WithTitle {
                public function title(): string
                {
                    return 'Rekap Pensiun';
                }
            },
            new class($this->periode) extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'Rekap PPPK Paruh Waktu';
                }
            },
            new class($this->periode, 'pendidikan') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'PW per Pendidikan';
                }
            },
            new class($this->periode, 'jenis_jabatan') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'PW per Jenis Jabatan';
                }
            },
            new class($this->periode, 'usia') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'PW per Usia';
                }
            },
            new class($this->periode, 'pensiun') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'PW Perkiraan Pensiun';
                }
            },
            new class($this->periode, 'jf_rumpun') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'PW Rincian JF';
                }
            },
            new class($this->periode, 'jabatan') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'PW Jabatan Terbanyak';
                }
            },
            new class($this->periode) extends RekapParuhWaktuPersenExport implements WithTitle {
                public function title(): string
                {
                    return 'PW Persentase Instansi';
                }
            },
        ];
    }
}