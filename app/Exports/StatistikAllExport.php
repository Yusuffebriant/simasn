<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Export "Export Semua Statistik": satu file Excel berisi SELURUH tabel
 * yang ada di halaman Statistik (Statistik.jsx), masing-masing pada
 * sheet-nya sendiri — pola sama persis seperti RekapAllExport di halaman
 * Admin (bungkus tiap Export class jadi anonymous class + WithTitle()).
 *
 * Urutan sheet mengikuti persis urutan menu di STATISTIK_MENU
 * (resources/js/pages/Statistik/Statistik.jsx), dari atas ke bawah.
 * Nama sheet dipangkas ke <=31 karakter karena itu batas Excel untuk
 * nama sheet.
 *
 * Empat sheet terakhir (PNS/PPPK Kemantren Berdasarkan Tingkat
 * Pendidikan/Golongan) BELUM punya tombol export sendiri di panelnya
 * masing-masing di layar — export class-nya (PnsKemantrenPendidikanExport
 * dkk) dibuat khusus untuk melengkapi file gabungan ini, supaya
 * "Export Semua Statistik" benar-benar mencakup semua tabel yang ada di
 * halaman Statistik, bukan cuma yang sudah punya tombol Export Excel
 * sendiri.
 *
 * PensiunanPnsExport butuh $tahun (int), BEDA dari export lain yang
 * butuh $periode (string, mis. "2026-09"). Kalau $periode diisi,
 * 4 karakter pertamanya (tahun) dipakai; kalau tidak, dibiarkan null
 * supaya PensiunanPnsExport jatuh ke default-nya sendiri (tahun
 * berjalan) — sama seperti perilaku endpoint
 * GET /statistik/pensiunan-pns/export tanpa query periode.
 */
class StatistikAllExport implements Export, WithMultipleSheets
{
    use Exportable;

    public function __construct(protected ?string $periode = null) {}

    public function sheets(): array
    {
        $periode = $this->periode;
        $tahun = $periode ? (int) substr($periode, 0, 4) : null;

        return [
            new class($periode) extends PejabatStrukturalExport implements WithTitle {
                public function title(): string
                {
                    return 'Pejabat Struktural';
                }
            },
            new class($periode) extends PejabatFungsionalExport implements WithTitle {
                public function title(): string
                {
                    return 'Pejabat Fungsional';
                }
            },
            new class($tahun) extends PensiunanPnsExport implements WithTitle {
                public function title(): string
                {
                    return 'Pensiunan PNS';
                }
            },
            new class($periode) extends PnsGolonganExport implements WithTitle {
                public function title(): string
                {
                    return 'PNS Golongan';
                }
            },
            new class($periode) extends PppkGolonganExport implements WithTitle {
                public function title(): string
                {
                    return 'PPPK Golongan';
                }
            },
            new class($periode) extends AsnPendidikanExport implements WithTitle {
                public function title(): string
                {
                    return 'ASN Pendidikan & Gender';
                }
            },
            new class($periode) extends PnsPendidikanExport implements WithTitle {
                public function title(): string
                {
                    return 'PNS Pendidikan & Gender';
                }
            },
            new class($periode) extends PppkPendidikanExport implements WithTitle {
                public function title(): string
                {
                    return 'PPPK Pendidikan & Gender';
                }
            },
            new class($periode) extends StafDinasSkpdExport implements WithTitle {
                public function title(): string
                {
                    return 'Pegawai Pendidikan & SKPD';
                }
            },
            new class extends AsnPerangkatDaerahJenisKelaminExport implements WithTitle {
                public function title(): string
                {
                    return 'ASN Perangkat Daerah';
                }
            },
            new class extends PenjabatPerangkatDaerahExport implements WithTitle {
                public function title(): string
                {
                    return 'Penjabat Perangkat Daerah';
                }
            },
            new class($periode) extends AsnKemantrenPendidikanExport implements WithTitle {
                public function title(): string
                {
                    return 'ASN Kemantren Pendidikan';
                }
            },
            new class($periode) extends PnsKemantrenPendidikanExport implements WithTitle {
                public function title(): string
                {
                    return 'PNS Kemantren Pendidikan';
                }
            },
            new class($periode) extends PppkKemantrenPendidikanExport implements WithTitle {
                public function title(): string
                {
                    return 'PPPK Kemantren Pendidikan';
                }
            },
            new class($periode) extends PnsKemantrenGolonganExport implements WithTitle {
                public function title(): string
                {
                    return 'PNS Kemantren Golongan';
                }
            },
            new class($periode) extends PppkKemantrenGolonganExport implements WithTitle {
                public function title(): string
                {
                    return 'PPPK Kemantren Golongan';
                }
            },
            new class($periode) extends PnsKelurahanExport implements WithTitle {
                public function title(): string
                {
                    return 'PNS Kelurahan';
                }
            },
            new class($periode) extends PppkKelurahanExport implements WithTitle {
                public function title(): string
                {
                    return 'PPPK Kelurahan';
                }
            },
        ];
    }
}
