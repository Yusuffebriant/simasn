<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RekapEselonGolonganGenderExport implements FromArray, WithEvents
{
    protected array $data;

    protected array $golonganList = [
        'III/a', 'III/b', 'III/c', 'III/d',
        'IV/a', 'IV/b', 'IV/c', 'IV/d', 'IV/e',
    ];

    protected int $headerRows = 7;

    // Warna disamakan dengan sheet "es gol gender" di file data pegawai
    protected string $warnaBiru = 'DCE6F2';   // header golongan & kolom ESELON
    protected string $warnaOranye = 'FCD5B5'; // kolom JML TOTAL & baris TOTAL

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapEselonGolonganGender($periode);
    }

    public function array(): array
    {
        $rows = [];
        $no = 1;

        foreach ($this->data as $data) {
            $rows[] = [
                $no++,
                $data['eselon'],
                ...array_values($data['pria']),
                $data['jml_pria'],
                ...array_values($data['wanita']),
                $data['jml_wanita'],
                $data['jml_total'],
            ];
        }

        return $rows;
    }

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
                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;

                $sheet->insertNewRowBefore(1, $this->headerRows);

                $sheet->setCellValue('A1', 'REKAPITULASI ASN PEMERINTAH DAERAH KAB/KOTA PEMERINTAH KOTA YOGYAKARTA');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT ESELON ,GOLONGAN DAN JENIS KELAMIN');
                $sheet->setCellValue('A3', 'KEADAAN : ' . $this->formatPeriode($this->periode));
                $sheet->mergeCells('A1:W1');
                $sheet->mergeCells('A2:W2');
                $sheet->mergeCells('A3:W3');

                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'ESELON');
                $sheet->setCellValue('C5', 'PRIA /GOLONGAN RUANG');
                $sheet->setCellValue('L5', 'JML');
                $sheet->setCellValue('M5', 'WANITA / ESELON');
                $sheet->setCellValue('V5', 'JML');
                $sheet->setCellValue('W5', 'JML TOTAL');

                $sheet->mergeCells('A5:A7');
                $sheet->mergeCells('B5:B7');
                $sheet->mergeCells('C5:K5');
                $sheet->mergeCells('L5:L7');
                $sheet->mergeCells('M5:U5');
                $sheet->mergeCells('V5:V7');
                $sheet->mergeCells('W5:W7');

                foreach ($this->golonganList as $index => $golongan) {
                    $sheet->setCellValue([$index + 3, 7], $golongan);
                    $sheet->setCellValue([$index + 13, 7], $golongan);
                }

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                foreach (range('C', 'W') as $column) {
                    $sum = 0;
                    for ($row = $this->headerRows + 1; $row <= $lastDataRow; $row++) {
                        $sum += (float) $sheet->getCell($column . $row)->getValue();
                    }
                    $sheet->setCellValue($column . $totalRow, $sum);
                }

                // ===== Styling (mengikuti sheet "es gol gender" di file data pegawai) =====
                $thin = Border::BORDER_THIN;
                $hair = Border::BORDER_HAIR;
                $firstDataRow = $this->headerRows + 1;

                $warnai = function (string $range, string $rgb) use ($sheet) {
                    $sheet->getStyle($range)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($rgb);
                };

                // Judul: bold & rata tengah
                foreach ([1, 2, 3] as $r) {
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                // Header baris 5-7: bold, rata tengah, border tipis
                $sheet->getStyle('A5:W7')->getFont()->setBold(true);
                $sheet->getStyle('A5:W7')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle('A5:W7')->getBorders()->getAllBorders()->setBorderStyle($thin);

                // Nama golongan (baris 7, blok pria C:K & wanita M:U): biru muda
                $warnai('C7:K7', $this->warnaBiru);
                $warnai('M7:U7', $this->warnaBiru);

                // Baris data: garis kotak per baris (kiri/kanan tipis, atas/bawah halus)
                for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                    $top = $r === $firstDataRow ? $thin : $hair;
                    $bottom = $r === $lastDataRow ? $thin : $hair;
                    $borders = $sheet->getStyle("A{$r}:W{$r}")->getBorders();
                    $borders->getLeft()->setBorderStyle($thin);
                    $borders->getRight()->setBorderStyle($thin);
                    $borders->getVertical()->setBorderStyle($thin);
                    $borders->getTop()->setBorderStyle($top);
                    $borders->getBottom()->setBorderStyle($bottom);
                }

                // Kolom ESELON (B) tiap baris data: biru muda
                $warnai("B{$firstDataRow}:B{$lastDataRow}", $this->warnaBiru);

                // Kolom JML TOTAL (W) tiap baris data: oranye muda + bold
                $warnai("W{$firstDataRow}:W{$lastDataRow}", $this->warnaOranye);
                $sheet->getStyle("W{$firstDataRow}:W{$lastDataRow}")->getFont()->setBold(true);

                // Baris TOTAL: bold, border tipis, angka (C:W) berwarna oranye muda
                $sheet->getStyle("A{$totalRow}:W{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$totalRow}:W{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);
                $warnai("C{$totalRow}:W{$totalRow}", $this->warnaOranye);

                // Lebar kolom
                $sheet->getColumnDimension('A')->setWidth(3.44);
                $sheet->getColumnDimension('B')->setWidth(10);
                foreach (array_merge(range('C', 'K'), range('M', 'U')) as $col) {
                    $sheet->getColumnDimension($col)->setWidth(7);
                }
                $sheet->getColumnDimension('L')->setWidth(10);
                $sheet->getColumnDimension('V')->setWidth(10);
                $sheet->getColumnDimension('W')->setWidth(11);
            },
        ];
    }
}