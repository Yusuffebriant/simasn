<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RekapJabatanExport implements FromArray, WithEvents
{
    protected array $data;

    protected array $eselonList = ['II A', 'II B', 'III A', 'III B', 'IV A', 'IV B'];

    protected int $headerRows = 7;

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapJabatan($periode);
    }

    public function array(): array
    {
        $rows = [];
        $no = 1;

        foreach ($this->data as $data) {
            $rows[] = [
                $no++,
                $data['instansi'],
                ...array_values($data['eselon']),
                $data['jml_eselon'],
                $data['fungsional_umum'],
                $data['fungsional_tertentu'],
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
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT JABATAN');
                $sheet->setCellValue('A3', 'KEADAAN ' . $this->formatPeriode($this->periode));
                $sheet->mergeCells('A1:L1');
                $sheet->mergeCells('A2:L2');
                $sheet->mergeCells('A3:L3');

                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'INSTANSI');
                $sheet->setCellValue('C5', 'ESELON');
                $sheet->setCellValue('I5', 'JML');
                $sheet->setCellValue('J5', 'Fungsional Umum/ Jab.Pelaksana');
                $sheet->setCellValue('K5', 'Fungsional Tertentu/ Jab. Fungsional');
                $sheet->setCellValue('L5', 'JML TOTAL');

                $sheet->mergeCells('A5:A7');
                $sheet->mergeCells('B5:B7');
                $sheet->mergeCells('C5:H5');
                $sheet->mergeCells('I5:I7');
                $sheet->mergeCells('J5:J7');
                $sheet->mergeCells('K5:K7');
                $sheet->mergeCells('L5:L7');

                foreach ($this->eselonList as $index => $eselon) {
                    $sheet->setCellValueByColumnAndRow($index + 3, 6, $eselon);
                }

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                foreach (range('C', 'L') as $column) {
                    $sum = 0;
                    for ($row = $this->headerRows + 1; $row <= $lastDataRow; $row++) {
                        $sum += (float) $sheet->getCell($column . $row)->getValue();
                    }
                    $sheet->setCellValue($column . $totalRow, $sum);
                }

                $sheet->getStyle('A1:A3')->getFont()->setBold(true);
                $sheet->getStyle('A5:L7')->getFont()->setBold(true);
                $sheet->getStyle('A5:L7')->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
                $sheet->getStyle("A5:L{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A{$totalRow}:L{$totalRow}")->getFont()->setBold(true);
            },
        ];
    }
}
