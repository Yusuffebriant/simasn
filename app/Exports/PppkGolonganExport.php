<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export tabel "PPPK Berdasarkan Golongan" (I, III, V, VII, IX, X, XI).
 * Sumber data sama persis dengan panel React PppkGolonganPanel.jsx, yaitu
 * StatistikService::statistikPppkGolongan().
 *
 * Layout sheet: satu baris per golongan, lalu baris TOTAL di paling
 * bawah. Kolom: Golongan, Laki-laki, Perempuan, Jumlah — sama seperti
 * kolom pada tabel di PppkGolonganPanel.jsx.
 */
class PppkGolonganExport implements FromArray, WithEvents
{
    protected array $data;
    protected array $rows;

    // HARUS sama persis dengan GOLONGAN_LIST di
    // resources/js/pages/Statistik/PppkGolonganPanel.jsx supaya urutan
    // baris konsisten dengan tampilan tabel di web.
    protected static array $golonganList = ['I', 'III', 'V', 'VII', 'IX', 'X', 'XI'];

    public function __construct(protected ?string $periode = null)
    {
        $this->data = (new StatistikService())->statistikPppkGolongan($periode);
        $this->rows = $this->buildRows();
    }

    protected function buildRows(): array
    {
        $rows = [];

        foreach (self::$golonganList as $g) {
            $golongan = $this->data['golongan'][$g];

            $rows[] = [
                "Golongan {$g}",
                $golongan['laki_laki'],
                $golongan['perempuan'],
                $golongan['total'],
            ];
        }

        return $rows;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->insertNewRowBefore(1, 3);

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH PPPK');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT GOLONGAN DAN JENIS KELAMIN');
                $sheet->mergeCells('A1:D1');
                $sheet->mergeCells('A2:D2');

                $sheet->setCellValue('A3', 'Golongan');
                $sheet->setCellValue('B3', 'Laki-laki');
                $sheet->setCellValue('C3', 'Perempuan');
                $sheet->setCellValue('D3', 'Jumlah');

                $lastDataRow = 3 + count($this->rows);
                $totalRow = $lastDataRow + 1;

                $totalLakiLaki = array_sum(array_map(
                    fn ($g) => $this->data['golongan'][$g]['laki_laki'],
                    self::$golonganList
                ));
                $totalPerempuan = array_sum(array_map(
                    fn ($g) => $this->data['golongan'][$g]['perempuan'],
                    self::$golonganList
                ));

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->setCellValue('B' . $totalRow, $totalLakiLaki);
                $sheet->setCellValue('C' . $totalRow, $totalPerempuan);
                $sheet->setCellValue('D' . $totalRow, $this->data['jumlah_pppk']);

                $sheet->getStyle('A1:A2')->getFont()->setBold(true);
                $sheet->getStyle('A3:D3')->getFont()->setBold(true);
                $sheet->getStyle('A3:D3')->getAlignment()->setHorizontal('center');
                $sheet->getStyle("A{$totalRow}:D{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A3:D{$totalRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                foreach (['A' => 16, 'B' => 12, 'C' => 12, 'D' => 12] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}