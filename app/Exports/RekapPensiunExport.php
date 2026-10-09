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
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export tabel "Rekapitulasi Pensiun" (Unit x Tahun + Sub Total).
 *
 * Layout mengikuti persis Excel referensi (Rekapitulasi Pensiun):
 *
 *   Baris 1-3 : judul (REKAPITULASI PENSIUN ..., KOTA YOGYAKARTA, KEADAAN ...)
 *   Baris 4   : kosong
 *   Baris 5-6 : No | Unit | Tahun (merge sepanjang kolom tahun) | Sub Total
 *   Baris 6   : tahun-tahun (2026 ... 2035)
 *   Baris 7.. : satu baris per unit (51 unit, urut abjad)
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

    /** 3 baris judul + 1 baris kosong + 2 baris header tabel (baris 5-6). */
    protected int $headerRows = 6;

    /** Baris pertama header tabel (No/Unit/Tahun). Baris tahun = +1. */
    protected int $headerStartRow = 5;

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
            ['REKAPITULASI JUMLAH PNS YANG MEMASUKI MASA PENSIUN'],
            ['KOTA YOGYAKARTA'],
            ['KEADAAN : ' . $this->formatPeriode($this->periode)],
            [''], // baris 4 pemisah. Jangan pakai [] — baris kosong dibuang Laravel Excel sehingga semua baris bergeser naik
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

    /** "2026-08" => "AGUSTUS 2026" (sama dengan export rekap lain). */
    protected function formatPeriode(string $periode): string
    {
        $bulan = [
            '01' => 'JANUARI', '02' => 'FEBRUARI', '03' => 'MARET', '04' => 'APRIL',
            '05' => 'MEI', '06' => 'JUNI', '07' => 'JULI', '08' => 'AGUSTUS',
            '09' => 'SEPTEMBER', '10' => 'OKTOBER', '11' => 'NOVEMBER', '12' => 'DESEMBER',
        ];

        [$tahun, $bln] = explode('-', $periode);

        return ($bulan[$bln] ?? $bln) . ' ' . $tahun;
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

                $h1 = $this->headerStartRow;   // 5
                $h2 = $h1 + 1;                 // 6
                $firstDataRow = $this->headerRows + 1; // 7

                // Judul (baris 1-3): merge sepanjang tabel, bold, rata tengah
                foreach ([1, 2, 3] as $r) {
                    $sheet->mergeCells("A{$r}:{$subTotal}{$r}");
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal('center')->setVertical('center');
                }

                // Header bertingkat, sama dengan Excel referensi.
                $sheet->mergeCells("A{$h1}:A{$h2}");
                $sheet->mergeCells("B{$h1}:B{$h2}");
                $sheet->mergeCells("{$firstYear}{$h1}:{$lastYear}{$h1}");
                $sheet->mergeCells("{$subTotal}{$h1}:{$subTotal}{$h2}");
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                $tabel = "A{$h1}:{$subTotal}{$totalRow}";
                $sheet->getStyle($tabel)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->getStyle("A{$h1}:{$subTotal}{$h2}")->getFont()->setBold(true);
                $sheet->getStyle("A{$h1}:{$subTotal}{$h2}")->getAlignment()
                    ->setHorizontal('center')->setVertical('center')->setWrapText(true);

                $sheet->getStyle("A{$totalRow}:{$subTotal}{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$totalRow}:{$subTotal}{$totalRow}")->getAlignment()
                    ->setHorizontal('center');

                // Nomor & angka rata tengah; nama unit rata kiri.
                $sheet->getStyle("A{$firstDataRow}:A{$lastDataRow}")->getAlignment()->setHorizontal('center');
                $sheet->getStyle("{$firstYear}{$firstDataRow}:{$subTotal}{$lastDataRow}")->getAlignment()->setHorizontal('center');
                $sheet->getStyle("{$subTotal}{$firstDataRow}:{$subTotal}{$lastDataRow}")->getFont()->setBold(true);

                // Warna: hanya bagian penting.
                $warnai = function (string $rangeWarna, string $rgb) use ($sheet) {
                    $sheet->getStyle($rangeWarna)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($rgb);
                };
                $warnai("A{$h1}:{$subTotal}{$h2}", 'DCE6F2');                                        // judul kolom
                $warnai("{$subTotal}{$firstDataRow}:{$subTotal}{$lastDataRow}", 'E6E0EC');           // kolom Sub Total
                $warnai("A{$totalRow}:{$subTotal}{$totalRow}", 'E6B9B8');                            // baris TOTAL (merah)

                $sheet->getRowDimension($h1)->setRowHeight(20);
                $sheet->getRowDimension($h2)->setRowHeight(20);

                $sheet->getColumnDimension('A')->setWidth(5);
                $sheet->getColumnDimension('B')->setWidth(100);

                for ($col = 3; $col <= $lastYearCol; $col++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(8);
                }

                $sheet->getColumnDimension($subTotal)->setWidth(12);

                // Bekukan judul & header kolom (baris 1-6) supaya tetap terlihat saat scroll
                $sheet->freezePane('A' . $firstDataRow);

                // Cetak: baris header tabel (5-6) diulang di tiap halaman
                $this->applyPrintSetup($sheet, "A1:{$subTotal}{$totalRow}", $h2, 'landscape', $h1);
            },
        ];
    }
}