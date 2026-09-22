<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RekapNakesExport implements FromArray, WithEvents, WithTitle
{
    protected array $data;
    protected int $headerRows = 2;

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapNakes($periode);
    }

    public function title(): string
    {
        return $this->formatPeriode($this->periode); // contoh: "Juli 2026"
    }

    public function array(): array
    {
        $rows = [];
        $no = 1;
        foreach ($this->data as $d) {
            $rows[] = [$no++, $d['fasilitas'], $d['pria'], $d['wanita'], $d['jumlah'], $d['alamat']];
        }
        return $rows;
    }

    protected function formatPeriode(string $periode): string
    {
        $bulan = [
            '01' => 'Januari',
            '02' => 'Februari',
            '03' => 'Maret',
            '04' => 'April',
            '05' => 'Mei',
            '06' => 'Juni',
            '07' => 'Juli',
            '08' => 'Agustus',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember',
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

                $sheet->setCellValue(
                    'A1',
                    'Data Pejabat Fungsional Nakes ' . $this->formatPeriode($this->periode)
                );
                $sheet->mergeCells('A1:F1');

                $sheet->setCellValue('A2', 'No.');
                $sheet->setCellValue('B2', 'Fasilitas Kesehatan');
                $sheet->setCellValue('C2', 'Pria');
                $sheet->setCellValue('D2', 'Wanita');
                $sheet->setCellValue('E2', 'Jumlah');
                $sheet->setCellValue('F2', 'Alamat');

                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, '');
                $sheet->setCellValue('B' . $totalRow, 'TOTAL');

                foreach (['C', 'D', 'E'] as $col) {
                    $sum = 0;
                    for ($r = $this->headerRows + 1; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }

                // Judul: ukuran & perataan sama seperti template excel yang dikirim user.
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal('left')->setVertical('top');
                $sheet->getRowDimension(1)->setRowHeight(32);

                $sheet->getStyle('A2:F2')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A2:F2')->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);

                $sheet->getStyle("A2:F{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("B{$totalRow}:E{$totalRow}")->getFont()->setBold(true);

                // Lebar kolom sama seperti template excel yang dikirim user.
                $sheet->getColumnDimension('A')->setWidth(8);
                $sheet->getColumnDimension('B')->setWidth(43.5);
                $sheet->getColumnDimension('C')->setWidth(8.2);
                $sheet->getColumnDimension('D')->setWidth(10.2);
                $sheet->getColumnDimension('E')->setWidth(10.5);
                $sheet->getColumnDimension('F')->setWidth(54.7);

                $sheet->getStyle('B3:B' . $lastDataRow)->getAlignment()->setWrapText(true)->setVertical('top');
                $sheet->getStyle('F3:F' . $lastDataRow)->getAlignment()->setWrapText(true)->setVertical('top');
                $sheet->getStyle("C3:E{$lastDataRow}")->getAlignment()->setHorizontal('center');
            },
        ];
    }
}
