<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export tabel "Pejabat Struktural" (Eselon II/III/IV per jenis kelamin).
 * Sumber data sama persis dengan panel React StrukturalPanel.jsx, yaitu
 * StatistikService::statistikPejabatStruktural().
 *
 * Layout sheet: satu baris per Eselon (II, III, IV) dengan kolom Laki-laki,
 * Perempuan, dan Total, lalu baris "TOTAL" di paling bawah — pola sama
 * seperti PnsKelurahanExport.php / PppkKelurahanExport.php.
 */
class PejabatStrukturalExport implements FromArray, WithEvents
{
    protected array $data;
    protected array $rows;

    public function __construct(protected ?string $periode = null)
    {
        $this->data = (new StatistikService())->statistikPejabatStruktural($periode);
        $this->rows = $this->buildRows();
    }

    protected function buildRows(): array
    {
        $eselon = [
            'Eselon II' => $this->data['eselon_ii'],
            'Eselon III' => $this->data['eselon_iii'],
            'Eselon IV' => $this->data['eselon_iv'],
        ];

        $rows = [];
        $no = 1;

        foreach ($eselon as $label => $detail) {
            $rows[] = [
                $no++,
                $label,
                $detail['laki_laki'] ?? 0,
                $detail['perempuan'] ?? 0,
                $detail['total'] ?? 0,
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

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH PEJABAT STRUKTURAL');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT ESELON DAN JENIS KELAMIN');
                $sheet->mergeCells('A1:E1');
                $sheet->mergeCells('A2:E2');

                $sheet->setCellValue('A3', 'No');
                $sheet->setCellValue('B3', 'Eselon');
                $sheet->setCellValue('C3', 'Laki-laki');
                $sheet->setCellValue('D3', 'Perempuan');
                $sheet->setCellValue('E3', 'Jumlah');

                $lastDataRow = 3 + count($this->rows);
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");
                $sheet->setCellValue('C' . $totalRow, '');
                $sheet->setCellValue('D' . $totalRow, '');
                $sheet->setCellValue('E' . $totalRow, $this->data['jumlah_pejabat_struktural']);

                $sheet->getStyle('A1:A2')->getFont()->setBold(true);
                $sheet->getStyle('A3:E3')->getFont()->setBold(true);
                $sheet->getStyle('A3:E3')->getAlignment()->setHorizontal('center');
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A3:E{$totalRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                foreach (['A' => 6, 'B' => 22, 'C' => 12, 'D' => 12, 'E' => 14] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}