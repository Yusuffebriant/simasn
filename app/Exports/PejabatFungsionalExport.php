<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export tabel "Pejabat Fungsional" (JFU & JFT per jenis kelamin).
 * Sumber data sama persis dengan panel React FungsionalPanel.jsx, yaitu
 * StatistikService::statistikPejabatFungsional().
 *
 * Layout sheet mengikuti tabel di panel: baris Fungsional Umum (JFU),
 * baris Fungsional Tertentu (JFT), lalu baris rincian "Rumpun ..." yang
 * merupakan pecahan DI DALAM JFT — karena itu baris rumpun TIDAK ikut
 * dijumlah ke baris TOTAL (sama seperti catatan di panel React), supaya
 * tidak dobel hitung.
 */
class PejabatFungsionalExport implements FromArray, WithEvents
{
    /**
     * Label rumpun jabatan fungsional tertentu, urutan disamakan dengan
     * RUMPUN_LIST di FungsionalPanel.jsx.
     */
    protected const RUMPUN_LIST = [
        'dosen' => 'Dosen',
        'guru' => 'Guru',
        'medis' => 'Medis',
        'teknis' => 'Teknis',
        'auditor' => 'Auditor',
        'p2upd' => 'P2UPD',
    ];

    protected array $data;
    protected array $rows;

    public function __construct(protected ?string $periode = null)
    {
        $this->data = (new StatistikService())->statistikPejabatFungsional($periode);
        $this->rows = $this->buildRows();
    }

    protected function buildRows(): array
    {
        $umum = $this->data['fungsional_umum'];
        $tertentu = $this->data['fungsional_tertentu'];
        $rumpunLaki = $this->data['fungsional_tertentu_laki_laki'] ?? [];
        $rumpunPerempuan = $this->data['fungsional_tertentu_perempuan'] ?? [];

        $rows = [];

        $rows[] = [
            1,
            'Fungsional Umum (JFU)',
            $umum['laki_laki'] ?? 0,
            $umum['perempuan'] ?? 0,
            $umum['total'] ?? 0,
        ];

        $rows[] = [
            2,
            'Fungsional Tertentu (JFT)',
            $tertentu['laki_laki'] ?? 0,
            $tertentu['perempuan'] ?? 0,
            $tertentu['total'] ?? 0,
        ];

        foreach (self::RUMPUN_LIST as $key => $label) {
            $laki = $rumpunLaki[$key] ?? 0;
            $perempuan = $rumpunPerempuan[$key] ?? 0;

            $rows[] = [
                '-',
                '    Rumpun ' . $label,
                $laki,
                $perempuan,
                $laki + $perempuan,
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

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH PEJABAT FUNGSIONAL');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT KATEGORI JABATAN DAN JENIS KELAMIN');
                $sheet->mergeCells('A1:E1');
                $sheet->mergeCells('A2:E2');

                $sheet->setCellValue('A3', 'No');
                $sheet->setCellValue('B3', 'Kategori');
                $sheet->setCellValue('C3', 'Laki-laki');
                $sheet->setCellValue('D3', 'Perempuan');
                $sheet->setCellValue('E3', 'Jumlah');

                $lastDataRow = 3 + count($this->rows);
                $totalRow = $lastDataRow + 1;

                // Total = JFU + JFT saja. Baris rumpun sengaja tidak ikut
                // dijumlah karena sudah termasuk di dalam JFT.
                $umum = $this->data['fungsional_umum'];
                $tertentu = $this->data['fungsional_tertentu'];

                $sheet->setCellValue('A' . $totalRow, 'TOTAL (JFU + JFT)');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");
                $sheet->setCellValue('C' . $totalRow, ($umum['laki_laki'] ?? 0) + ($tertentu['laki_laki'] ?? 0));
                $sheet->setCellValue('D' . $totalRow, ($umum['perempuan'] ?? 0) + ($tertentu['perempuan'] ?? 0));
                $sheet->setCellValue('E' . $totalRow, ($umum['total'] ?? 0) + ($tertentu['total'] ?? 0));

                // Catatan kaki, menjelaskan kenapa baris rumpun tidak dijumlah.
                $catatanRow = $totalRow + 2;
                $sheet->setCellValue(
                    'A' . $catatanRow,
                    'Catatan: baris "Rumpun ..." adalah rincian di dalam Fungsional Tertentu (JFT) dan tidak dijumlah lagi ke baris TOTAL.'
                );
                $sheet->mergeCells("A{$catatanRow}:E{$catatanRow}");
                $sheet->getStyle('A' . $catatanRow)->getFont()->setItalic(true)->setSize(9);

                $sheet->getStyle('A1:A2')->getFont()->setBold(true);
                $sheet->getStyle('A3:E3')->getFont()->setBold(true);
                $sheet->getStyle('A3:E3')->getAlignment()->setHorizontal('center');
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A3:E{$totalRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                foreach (['A' => 6, 'B' => 30, 'C' => 12, 'D' => 12, 'E' => 14] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}