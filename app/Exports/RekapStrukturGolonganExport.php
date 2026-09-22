<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RekapStrukturGolonganExport implements FromArray, WithEvents
{
    protected array $data;

    protected array $golonganList = [
        'III/a', 'III/b', 'III/c', 'III/d',
        'IV/a', 'IV/b', 'IV/c', 'IV/d',
    ];

    protected int $headerRows = 7;

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapStrukturGolonganInstansi($periode);
    }

    public function array(): array
    {
        $rows = [];
        $no = 1;

        foreach ($this->data as $data) {
            $rows[] = [
                $no++,
                $data['instansi'],
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

                $sheet->setCellValue('A1', 'REKAPITULASI STRUKTURAL BERDASARKAN GOLONGAN PEMERINTAH DAERAH/KABUPATEN/KOTA PEMERINTAH KOTA YOGYAKARTA');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT INSTANSI, GOLONGAN RUANG DAN JENIS KELAMIN');
                $sheet->setCellValue('A3', 'KEADAAN : ' . $this->formatPeriode($this->periode));
                $sheet->mergeCells('A1:U1');
                $sheet->mergeCells('A2:U2');
                $sheet->mergeCells('A3:U3');

                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'INSTANSI');
                $sheet->setCellValue('C5', 'PRIA');
                $sheet->setCellValue('K5', 'JML');
                $sheet->setCellValue('L5', 'WANITA');
                $sheet->setCellValue('T5', 'JML');
                $sheet->setCellValue('U5', 'JML TOTAL');

                $sheet->mergeCells('A5:A7');
                $sheet->mergeCells('B5:B7');
                $sheet->mergeCells('C5:J5');
                $sheet->mergeCells('K5:K7');
                $sheet->mergeCells('L5:S5');
                $sheet->mergeCells('T5:T7');
                $sheet->mergeCells('U5:U7');

                foreach ($this->golonganList as $index => $golongan) {
                    $sheet->setCellValue([$index + 3, 7], $golongan);
                    $sheet->setCellValue([$index + 12, 7], $golongan);
                }

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                foreach (range('C', 'U') as $column) {
                    $sum = 0;
                    for ($row = $this->headerRows + 1; $row <= $lastDataRow; $row++) {
                        $sum += (float) $sheet->getCell($column . $row)->getValue();
                    }
                    $sheet->setCellValue($column . $totalRow, $sum);
                }

                $sheet->getStyle('A1:A3')->getFont()->setBold(true);
                $sheet->getStyle('A5:U7')->getFont()->setBold(true);
                $sheet->getStyle('A5:U7')->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
                $sheet->getStyle("A5:U{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A{$totalRow}:U{$totalRow}")->getFont()->setBold(true);
            },
        ];
    }
}