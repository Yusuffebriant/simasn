<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Laporan Dashboard (RekapDashboardExport) + sheet "Status Kepegawaian"
 * yang merinci PNS, PPPK, PPPK Paruh Waktu, dan Lainnya (NABAN, Pegawai
 * Titipan). Dipakai oleh export Dashboard dan Dashboard Total.
 *
 * Sheet Status Kepegawaian memakai data yang sama dengan yang tampil di
 * layar ($data['ringkasan_status'] dan $data['rincian_lainnya']), jadi
 * angkanya selalu sama dengan dashboard, termasuk untuk periode arsip.
 */
class RekapDashboardLengkapExport implements WithMultipleSheets
{
    public function __construct(
        protected array $data,
        protected string $periode
    ) {}

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