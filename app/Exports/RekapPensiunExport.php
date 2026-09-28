<?php

namespace App\Exports;

use App\Exports\Concerns\PrintsConsistently;
use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export tabel "Rekapitulasi Pensiun" (Unit x Tahun + Sub Total).
 *
 * Layout mengikuti persis Excel referensi (Rekapitulasi Pensiun):
 *
 *   Baris 1-2 : No | Unit | Tahun (merge sepanjang kolom tahun) | Sub Total
 *   Baris 2   : tahun-tahun (2026 ... 2035)
 *   Baris 3.. : satu baris per unit (51 unit, urut abjad)
 *   Terakhir  : TOTAL (merge kolom A:B) berisi jumlah per kolom
 *
 * Kolom "Sub Total" dan baris "TOTAL" ditulis sebagai rumus SUM (bukan angka
 * mati) seperti pada Excel referensi, jadi ikut berubah kalau angka per
 * tahun diedit manual setelah di-download.
 *
 * WithPreCalculateFormulas: rumus SUM dihitung di server dan hasilnya ikut
 * disimpan di file, sehingga angka Sub Total/TOTAL langsung tampil di
 * Protected View Excel, preview browser, dan viewer HP tanpa Enable Editing.
 *
 * Sumber data: RekapService::rekapPensiun() — sama dengan tabel di halaman
 * Rekapitulasi ASN (tab Pensiun).
 */
class RekapPensiunExport implements FromArray, WithEvents, WithPreCalculateFormulas
{
    use PrintsConsistently;

    protected array $data;

    /** Baris header (No/Unit/Tahun + baris tahun). */
    protected int $headerRows = 2;

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapPensiun($periode);
    }

    public function array(): array
    {
        $tahunList = $this->data['tahun_list'];
        $jumlahTahun = count($tahunList);

        $firstYearCol = 3; // kolom C
        $lastYearCol = $firstYearCol + $jumlahTahun - 1;
        $firstYear = Coordinate::stringFromColumnIndex($firstYearCol);
        $lastYear = Coordinate::stringFromColumnIndex($lastYearCol);

        // Baris 1: 'Tahun' hanya di kolom pertama tahun (sisanya kosong,
        // akan di-merge di AfterSheet). Baris 2: daftar tahun.
        $rows = [
            array_merge(['No', 'Unit', 'Tahun'], array_fill(0, $jumlahTahun - 1, ''), ['Sub Total']),
            array_merge(['', ''], $tahunList, ['']),
        ];

        $no = 1;
        $row = $this->headerRows;

        foreach ($this->data['rows'] as $d) {
            $row++;

            $rows[] = array_merge(
                [$no++, $d['instansi']],
                array_values($d['per_tahun']),
                ["=SUM({$firstYear}{$row}:{$lastYear}{$row})"]
            );
        }

        $lastDataRow = $row;
        $totalRow = [];

        for ($col = $firstYearCol; $col <= $lastYearCol + 1; $col++) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $totalRow[] = "=SUM({$letter}" . ($this->headerRows + 1) . ":{$letter}{$lastDataRow})";
        }

        $rows[] = array_merge(['TOTAL', ''], $totalRow);

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $jumlahTahun = count($this->data['tahun_list']);
                $lastYearCol = 3 + $jumlahTahun - 1;
                $subTotalCol = $lastYearCol + 1;

                $firstYear = Coordinate::stringFromColumnIndex(3);
                $lastYear = Coordinate::stringFromColumnIndex($lastYearCol);
                $subTotal = Coordinate::stringFromColumnIndex($subTotalCol);

                $lastDataRow = $this->headerRows + count($this->data['rows']);
                $totalRow = $lastDataRow + 1;
                $range = "A1:{$subTotal}{$totalRow}";

                // Header bertingkat, sama dengan Excel referensi.
                $sheet->mergeCells('A1:A2');
                $sheet->mergeCells('B1:B2');
                $sheet->mergeCells("{$firstYear}1:{$lastYear}1");
                $sheet->mergeCells("{$subTotal}1:{$subTotal}2");
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                $sheet->getStyle($range)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->getStyle("A1:{$subTotal}2")->getFont()->setBold(true);
                $sheet->getStyle("A1:{$subTotal}2")->getAlignment()
                    ->setHorizontal('center')->setVertical('center')->setWrapText(true);

                $sheet->getStyle("A{$totalRow}:{$subTotal}{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$totalRow}:{$subTotal}{$totalRow}")->getAlignment()
                    ->setHorizontal('center');

                // Nomor & angka rata tengah; nama unit rata kiri.
                $sheet->getStyle("A3:A{$lastDataRow}")->getAlignment()->setHorizontal('center');
                $sheet->getStyle("{$firstYear}3:{$subTotal}{$lastDataRow}")->getAlignment()->setHorizontal('center');
                $sheet->getStyle("{$subTotal}3:{$subTotal}{$lastDataRow}")->getFont()->setBold(true);

                $sheet->getColumnDimension('A')->setWidth(5);
                $sheet->getColumnDimension('B')->setWidth(100);

                for ($col = 3; $col <= $lastYearCol; $col++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(8);
                }

                $sheet->getColumnDimension($subTotal)->setWidth(12);

                $this->applyPrintSetup($sheet, $range, $this->headerRows, 'landscape');
            },
        ];
    }
}
