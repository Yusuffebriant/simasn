<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RekapPendidikanExport implements FromArray, WithEvents
{
    protected array $data;
    protected array $pendidikanList = ['SD', 'SLTP', 'SLTA', 'D I', 'D II', 'D III', 'D IV', 'S1', 'S2', 'S3'];
    protected int $headerRows = 7;

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapPendidikan($periode);
    }

    public function array(): array
    {
        $rows = [];
        $no = 1;
        foreach ($this->data as $d) {
            $rows[] = array_merge(
                [$no++, $d['instansi']],
                array_values($d['pria']),
                [$d['jml_pria']],
                array_values($d['wanita']),
                [$d['jml_wanita']],
                [$d['jml_total']]
            );
        }
        return $rows;
    }

    protected function formatPeriode(string $periode): string
    {
        $bulan = [
            '01'=>'JANUARI','02'=>'FEBRUARI','03'=>'MARET','04'=>'APRIL',
            '05'=>'MEI','06'=>'JUNI','07'=>'JULI','08'=>'AGUSTUS',
            '09'=>'SEPTEMBER','10'=>'OKTOBER','11'=>'NOVEMBER','12'=>'DESEMBER',
        ];
        [$tahun, $bln] = explode('-', $periode);
        return ($bulan[$bln] ?? $bln) . ' ' . $tahun;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->insertNewRowBefore(1, $this->headerRows);

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH ASN PEMERINTAH DAERAH/KABUPATEN/KOTA PEMERINTAH KOTA YOGYAKARTA');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT PENDIDIKAN DAN JENIS KELAMIN');
                $sheet->setCellValue('A3', 'KEADAAN : ' . $this->formatPeriode($this->periode));
                $sheet->mergeCells('A1:Y1');
                $sheet->mergeCells('A2:Y2');
                $sheet->mergeCells('A3:Y3');

                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'INSTANSI');
                $sheet->setCellValue('C5', 'PRIA');
                $sheet->setCellValue('M5', 'JML');
                $sheet->setCellValue('N5', 'WANITA');
                $sheet->setCellValue('X5', 'JML');
                $sheet->setCellValue('Y5', 'JML TOTAL');

                $sheet->mergeCells('A5:A7');
                $sheet->mergeCells('B5:B7');
                $sheet->mergeCells('C5:L5');
                $sheet->mergeCells('M5:M7');
                $sheet->mergeCells('N5:W5');
                $sheet->mergeCells('X5:X7');
                $sheet->mergeCells('Y5:Y7');

                $kolomPria = ['C','D','E','F','G','H','I','J','K','L'];
                $kolomWanita = ['N','O','P','Q','R','S','T','U','V','W'];
                foreach ($this->pendidikanList as $i => $nama) {
                    $sheet->setCellValue($kolomPria[$i] . '7', $nama);
                    $sheet->setCellValue($kolomWanita[$i] . '7', $nama);
                }

                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                foreach (range('C', 'Y') as $col) {
                    $sum = 0;
                    for ($r = $this->headerRows + 1; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }
                $sheet->getStyle("A{$totalRow}:Y{$totalRow}")->getFont()->setBold(true);

                $sheet->getStyle('A1:A3')->getFont()->setBold(true);
                $sheet->getStyle('A5:Y7')->getFont()->setBold(true);
                $sheet->getStyle('A5:Y7')->getAlignment()->setHorizontal('center')->setVertical('center');
                $sheet->getStyle("A5:Y{$totalRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }
}