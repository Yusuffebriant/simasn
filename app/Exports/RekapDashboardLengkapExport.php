<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Laporan Dashboard (RekapDashboardExport) + sheet "Status Kepegawaian"
 * yang merinci PNS, PPPK, PPPK Paruh Waktu, dan Lainnya (NABAN, Pegawai
 * Titipan).
 *
 * STATUS: TIDAK DIPAKAI di controller mana pun.
 * Export Dashboard yang berjalan saat ini adalah RekapDashboardExport
 * langsung (RekapController::exportDashboard dan exportDashboardTotal).
 * RekapDashboardExport sudah menambahkan sheet "Status Kepegawaian" sendiri
 * lewat RekapStatusKepegawaianExport::isiWorksheet(), jadi kalau class ini
 * dipakai, sheet tersebut akan muncul DUA kali. Class dibiarkan ada (dan
 * sudah diperbaiki agar valid) kalau suatu saat dibutuhkan sebagai
 * pembungkus; jangan dipasang di controller tanpa menghapus sheet
 * tambahan di RekapDashboardExport.
 *
 * Sheet Status Kepegawaian memakai data yang sama dengan yang tampil di
 * layar ($data['ringkasan_status'] dan $data['rincian_lainnya']), jadi
 * angkanya selalu sama dengan dashboard, termasuk untuk periode arsip.
 *
 * Catatan teknis (Laravel Excel 4.x): class yang diserahkan ke
 * Excel::download() harus meng-implement interface `Export`.
 */
class RekapDashboardLengkapExport implements Export, WithMultipleSheets
{
    use Exportable;

    /**
     * @param array  $data    Hasil RekapService::rekapDashboard() (sama dengan yang tampil di dashboard).
     * @param string $periode Bulan laporan, format 'Y-m' (contoh: '2026-10').
     */
    public function __construct(
        protected array $data,
        protected string $periode
    ) {}

    /**
     * Sheet 1: laporan dashboard lengkap (kartu, tabel, grafik).
     * Sheet 2: Status Kepegawaian (PNS, PPPK, Paruh Waktu, Lainnya).
     */
    public function sheets(): array
    {
        return [
            new RekapDashboardExport($this->data, $this->periode),
            new RekapStatusKepegawaianExport(
                $this->periode,
                $this->data['ringkasan_status'] ?? null,
                $this->data['rincian_lainnya'] ?? null
            ),
        ];
    }
}