<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Satu file Excel rekap PPPK paruh waktu dengan 8 sheet:
 *
 *   1. Per Perangkat Daerah     - jumlah Paruh Waktu tiap instansi
 *   2. Per Pendidikan           - menurut tingkat pendidikan (SD s.d. S3)
 *   3. Per Jenis Jabatan        - Pelaksana / Fungsional / Struktural
 *   4. Per Usia                 - menurut kelompok usia
 *   5. Perkiraan Pensiun        - perkiraan tahun pensiun
 *   6. Rincian JF               - jabatan fungsional: Pendidikan / Kesehatan / Teknis
 *   7. Jabatan Terbanyak        - 10 jabatan terbanyak + "Jabatan lainnya"
 *   8. Persentase per Instansi  - Paruh Waktu dibanding seluruh pegawai instansi
 *
 * Sheet 1-7 memakai class RekapParuhWaktuExport (pilihannya lewat parameter
 * kedua: 'instansi', 'pendidikan', dst). Sheet 8 memakai
 * RekapParuhWaktuPersenExport karena kolomnya berbeda.
 *
 * Dipakai oleh endpoint /rekap/paruh-waktu/export
 * (RekapController::exportParuhWaktu).
 *
 * Catatan teknis (Laravel Excel 4.x):
 *   Class yang diserahkan ke Excel::download() HARUS meng-implement
 *   interface `Export`. Tanpa itu tombol export error. `Exportable`
 *   hanya trait tambahan (mis. ->download()), sama seperti RekapAllExport
 *   dan RekapSdSmpExport.
 */
class RekapParuhWaktuLengkapExport implements Export, WithMultipleSheets
{
    use Exportable;

    /**
     * @param string $periode Bulan laporan, format 'Y-m' (contoh: '2026-10').
     */
    public function __construct(protected string $periode) {}

    /**
     * Daftar sheet, sesuai urutan tab di file Excel.
     * Tiap sheet dibuat sebagai anonymous class supaya judul tab
     * (title) bisa diganti tanpa membuat file class baru.
     * Judul tab maksimal 31 karakter (batas Excel).
     */
    public function sheets(): array
    {
        return [
            // Sheet 1: jumlah Paruh Waktu (pria/wanita/jumlah) per perangkat daerah.
            new class($this->periode, 'instansi') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'Per Perangkat Daerah';
                }
            },
            // Sheet 2: per tingkat pendidikan.
            new class($this->periode, 'pendidikan') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'Per Pendidikan';
                }
            },
            // Sheet 3: per jenis jabatan (Pelaksana / Fungsional / Struktural).
            new class($this->periode, 'jenis_jabatan') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'Per Jenis Jabatan';
                }
            },
            // Sheet 4: per kelompok usia (usia dihitung pada akhir bulan periode).
            new class($this->periode, 'usia') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'Per Usia';
                }
            },
            // Sheet 5: perkiraan pensiun per tahun.
            new class($this->periode, 'pensiun') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'Perkiraan Pensiun';
                }
            },
            // Sheet 6: rincian jabatan fungsional per rumpun.
            new class($this->periode, 'jf_rumpun') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'Rincian JF';
                }
            },
            // Sheet 7: 10 jabatan terbanyak (sisanya digabung "Jabatan lainnya").
            new class($this->periode, 'jabatan') extends RekapParuhWaktuExport implements WithTitle {
                public function title(): string
                {
                    return 'Jabatan Terbanyak';
                }
            },
            // Sheet 8: persentase Paruh Waktu terhadap seluruh pegawai tiap instansi.
            new class($this->periode) extends RekapParuhWaktuPersenExport implements WithTitle {
                public function title(): string
                {
                    return 'Persentase per Instansi';
                }
            },
        ];
    }
}