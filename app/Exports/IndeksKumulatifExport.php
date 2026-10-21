<?php

namespace App\Exports;

use App\Services\IndeksKumulatifService;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Export Excel menu Indeks Kumulatif: satu sheet per dimensi (agama,
 * pendidikan, golongan, JFT, JFU) + satu sheet "Keterangan" berisi cara
 * menghitung. Angkanya SAMA dengan yang tampil di layar karena memakai
 * IndeksKumulatifService (sumber data) dan hitungDeret() (rumus) yang sama.
 */
class IndeksKumulatifExport implements Export, WithMultipleSheets
{
    use Exportable;

    public function __construct(protected string $tahun) {}

    public function sheets(): array
    {
        $data = (new IndeksKumulatifService())->susun($this->tahun);
        $periode = array_column($data['bulan'], 'periode');

        $sheets = [];
        foreach ($data['dimensi'] as $tabel) {
            $sheets[] = new IndeksKumulatifSheetExport($this->tahun, $periode, $tabel);
        }

        $sheets[] = new IndeksKumulatifKeteranganExport($this->tahun, $periode);

        return $sheets;
    }
}