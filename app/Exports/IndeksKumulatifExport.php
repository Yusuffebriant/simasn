<?php

namespace App\Exports;

use App\Services\IndeksKumulatifService;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Export Excel menu Indeks Kumulatif: satu sheet "Ringkasan" (kesimpulan
 * perubahan sepanjang rentang), lalu satu sheet per dimensi (agama,
 * pendidikan, golongan, JFT, JFU) + satu sheet "Keterangan" berisi cara
 * menghitung. Angkanya SAMA dengan yang tampil di layar karena memakai
 * IndeksKumulatifService (sumber data) dan hitungDeret() (rumus) yang sama.
 *
 * Tanpa $awal/$akhir: Januari s.d. bulan terakhir pada $tahun (perilaku lama,
 * periode dasar = bulan pertama yang punya data). Dengan $awal/$akhir: rentang
 * pilihan pengguna (boleh lintas tahun), periode dasar = $awal.
 */
class IndeksKumulatifExport implements Export, WithMultipleSheets
{
    use Exportable;

    public function __construct(
        protected string $tahun,
        protected ?string $awal = null,
        protected ?string $akhir = null
    ) {}

    public function sheets(): array
    {
        $service = new IndeksKumulatifService();
        $rentang = $this->awal !== null && $this->akhir !== null;

        $data = $rentang
            ? $service->susunRentang($this->awal, $this->akhir)
            : $service->susun($this->tahun);
        $periode = array_column($data['bulan'], 'periode');
        $dasar = $rentang ? 0 : null; // rentang pilihan: periode dasar = periode awal

        $sheets = [];

        // Urutan: Ringkasan (mulai membaca dari sini), Keterangan (cara membaca),
        // lalu tabel lengkap per kelompok.
        $sheets[] = new IndeksKumulatifRingkasanExport($periode, $data['dimensi'], $dasar);
        $sheets[] = new IndeksKumulatifKeteranganExport($this->tahun, $periode);

        foreach ($data['dimensi'] as $tabel) {
            $sheets[] = new IndeksKumulatifSheetExport($this->tahun, $periode, $tabel, $dasar);
        }

        return $sheets;
    }
}