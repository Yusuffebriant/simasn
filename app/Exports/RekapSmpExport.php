<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RekapSmpExport implements FromArray, WithEvents
{
    protected array $data;
    protected int $headerRows = 2;

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapSmpFungsional($periode);
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->insertNewRowBefore(1, $this->headerRows);

                $sheet->setCellValue('A1', 'REKAP Data Fungsional SMP');
                $sheet->mergeCells('A1:E1');

                $sheet->setCellValue('A2', 'No');
                $sheet->setCellValue('B2', 'SMP');
                $sheet->setCellValue('C2', 'Pria');
                $sheet->setCellValue('D2', 'Wanita');
                $sheet->setCellValue('E2', 'Total');

                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;
                $sheet->setCellValue('B' . $totalRow, 'TOTAL');

                foreach (['C', 'D', 'E'] as $col) {
                    $sum = 0;
                    for ($r = $this->headerRows + 1; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }

                $sheet->getStyle('A1')->getFont()->setBold(true);
                $sheet->getStyle('A2:E2')->getFont()->setBold(true);
                $sheet->getStyle('A2:E2')->getAlignment()->setHorizontal('center');
                $sheet->getStyle("A2:E{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("B{$totalRow}:E{$totalRow}")->getFont()->setBold(true);
            },
        ];
    }
}
