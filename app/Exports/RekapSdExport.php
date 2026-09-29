<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RekapSdExport implements FromArray, WithEvents
{
    protected array $data;
    protected int $headerRows = 3; // 1: judul, 2: keadaan/periode, 3: header kolom

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapSdFungsional($periode);
    }

    public function array(): array
    {
        $rows = [];
        $no = 1;
        foreach ($this->data as $d) {
            $rows[] = [$no++, $d['sekolah'], $d['pria'], $d['wanita'], $d['jumlah']];
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
        [$tahun, $bln] = array_pad(explode('-', $periode), 2, '');
        return trim(($bulan[$bln] ?? $bln) . ' ' . $tahun);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->insertNewRowBefore(1, $this->headerRows);

                // === Judul & periode ===
                $sheet->setCellValue('A1', 'REKAP DATA FUNGSIONAL SD');
                $sheet->setCellValue('A2', 'KEADAAN : ' . $this->formatPeriode($this->periode));
                $sheet->mergeCells('A1:E1');
                $sheet->mergeCells('A2:E2');

                // === Header kolom tabel ===
                $headerRow = 3;
                $sheet->setCellValue("A{$headerRow}", 'NO');
                $sheet->setCellValue("B{$headerRow}", 'SD');
                $sheet->setCellValue("C{$headerRow}", 'PRIA');
                $sheet->setCellValue("D{$headerRow}", 'WANITA');
                $sheet->setCellValue("E{$headerRow}", 'JUMLAH');

                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;
                $sheet->setCellValue("B{$totalRow}", 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                foreach (['C', 'D', 'E'] as $col) {
                    $sum = 0;
                    for ($r = $this->headerRows + 1; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }

                // === Palet warna (senada dengan file rekap data pegawai) ===
                $navy = '1F4E78';
                $headerFill = 'D9E2F3';
                $totalFill = 'FCE4D6';
                $bandFill = 'F2F6FC';

                // Judul: bold, rata tengah
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB($navy);
                $sheet->getStyle('A1')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(24);

                // Sub-judul (periode): bold miring, rata tengah
                $sheet->getStyle('A2')->getFont()->setBold(true)->setItalic(true)->setSize(11);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension(2)->setRowHeight(18);

                // Header tabel: bold, rata tengah, latar biru muda
                $sheet->getStyle("A{$headerRow}:E{$headerRow}")->getFont()->setBold(true)->getColor()->setRGB($navy);
                $sheet->getStyle("A{$headerRow}:E{$headerRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A{$headerRow}:E{$headerRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($headerFill);
                $sheet->getRowDimension($headerRow)->setRowHeight(20);

                // Garis tepi tipis untuk seluruh tabel (header s/d total)
                $sheet->getStyle("A{$headerRow}:E{$totalRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // Baris data: selang-seling agar mudah dibaca
                for ($r = $this->headerRows + 1; $r <= $lastDataRow; $r++) {
                    if ((($r - $this->headerRows) % 2) === 0) {
                        $sheet->getStyle("A{$r}:E{$r}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bandFill);
                    }
                }

                // Perataan isi data
                $firstDataRow = $this->headerRows + 1;
                $sheet->getStyle("A{$firstDataRow}:A{$lastDataRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$firstDataRow}:B{$lastDataRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("C{$firstDataRow}:E{$lastDataRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Baris TOTAL: bold, latar menonjol
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($totalFill);
                $sheet->getStyle("B{$totalRow}:E{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Lebar kolom
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(40);
                $sheet->getColumnDimension('C')->setWidth(12);
                $sheet->getColumnDimension('D')->setWidth(12);
                $sheet->getColumnDimension('E')->setWidth(12);

                $sheet->freezePane('A' . ($headerRow + 1));
            },
        ];
    }
}