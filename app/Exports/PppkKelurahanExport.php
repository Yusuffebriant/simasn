<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export tabel "PPPK Kelurahan" (5.03.020), dikelompokkan berdasarkan
 * Kemantren induknya. Sumber data sama persis dengan panel React
 * PppkKelurahanPanel.jsx, yaitu StatistikService::statistikPppkKelurahan().
 *
 * Layout sheet: satu baris per Kelurahan, dikelompokkan per Kemantren,
 * dengan baris "Jumlah <Kemantren>" di akhir tiap kelompok dan baris
 * "TOTAL" di paling bawah. Kolom: No, Kemantren, Kelurahan, Laki-laki,
 * Perempuan, Jumlah PPPK — rincian gender diambil dari
 * $detail['kelurahan_gender'] (ditambahkan di statistikPppkKelurahan()
 * khusus untuk kebutuhan export ini; tidak mengubah struktur
 * $detail['kelurahan'] yang sudah dipakai panel React, supaya tidak
 * breaking).
 */
class PppkKelurahanExport implements FromArray, WithEvents
{
    protected array $data;
    protected array $rows;

    public function __construct(protected ?string $periode = null)
    {
        $this->data = (new StatistikService())->statistikPppkKelurahan($periode);
        $this->rows = $this->buildRows();
    }

    protected function buildRows(): array
    {
        $rows = [];
        $no = 1;

        foreach ($this->data['kemantren'] as $namaKemantren => $detail) {
            $genderData = $detail['kelurahan_gender'] ?? [];
            $totalLakiKemantren = 0;
            $totalPerempuanKemantren = 0;

            foreach ($detail['kelurahan'] as $namaKelurahan => $jumlah) {
                $laki = $genderData[$namaKelurahan]['laki_laki'] ?? 0;
                $perempuan = $genderData[$namaKelurahan]['perempuan'] ?? 0;

                $totalLakiKemantren += $laki;
                $totalPerempuanKemantren += $perempuan;

                $rows[] = [
                    $no++,
                    $this->toTitleCase($namaKemantren),
                    $this->toTitleCase($namaKelurahan),
                    $laki,
                    $perempuan,
                    $jumlah,
                ];
            }

            $rows[] = [
                '',
                'Jumlah Kemantren ' . $this->toTitleCase($namaKemantren),
                '',
                $totalLakiKemantren,
                $totalPerempuanKemantren,
                $detail['total'],
            ];
        }

        if (($this->data['tidak_dikenali'] ?? 0) > 0) {
            $rows[] = ['', 'Tidak Dikenali', '', '', '', $this->data['tidak_dikenali']];
        }

        return $rows;
    }

    public function array(): array
    {
        return $this->rows;
    }

    protected function toTitleCase(string $text): string
    {
        return ucwords(strtolower($text));
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->insertNewRowBefore(1, 2);

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH PPPK KELURAHAN');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT KEMANTREN, KELURAHAN, DAN JENIS KELAMIN');
                $sheet->mergeCells('A1:F1');
                $sheet->mergeCells('A2:F2');

                $sheet->setCellValue('A3', 'No');
                $sheet->setCellValue('B3', 'Kemantren');
                $sheet->setCellValue('C3', 'Kelurahan');
                $sheet->setCellValue('D3', 'Laki-laki');
                $sheet->setCellValue('E3', 'Perempuan');
                $sheet->setCellValue('F3', 'Jumlah PPPK');

                $lastDataRow = 3 + count($this->rows);
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:C{$totalRow}");
                $sheet->setCellValue('D' . $totalRow, '');
                $sheet->setCellValue('E' . $totalRow, '');
                $sheet->setCellValue('F' . $totalRow, $this->data['jumlah_pppk_kelurahan']);

                $sheet->getStyle('A1:A2')->getFont()->setBold(true);
                $sheet->getStyle('A3:F3')->getFont()->setBold(true);
                $sheet->getStyle('A3:F3')->getAlignment()->setHorizontal('center');
                $sheet->getStyle("A{$totalRow}:F{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A3:F{$totalRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                foreach (['A' => 6, 'B' => 22, 'C' => 22, 'D' => 12, 'E' => 12, 'F' => 14] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}
