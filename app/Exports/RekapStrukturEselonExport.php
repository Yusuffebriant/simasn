<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapStrukturEselonExport implements FromArray, WithEvents
{
    // Palet warna (RGB tanpa '#') — diambil dari file contoh.
    protected string $warnaBiruMuda = 'DCE6F2';
    protected string $warnaOranye = 'FCD5B5';
    protected string $warnaTosca = 'B7DEE8';
    protected string $warnaToscaMuda = 'DBEEF4';
    protected string $warnaUngu = 'E6E0EC';
    protected string $warnaMerahMuda = 'E6B9B8';
    protected string $warnaNavy = '002060';
    protected string $warnaViolet = '7030A0';

    protected function rekapWarnai(Worksheet $sheet, string $range, string $rgb): void
    {
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($rgb);
    }

    /** Baris 1-3 (judul): bold, rata tengah. */
    protected function rekapJudul(Worksheet $sheet): void
    {
        foreach ([1, 2, 3] as $r) {
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            $sheet->getStyle("A{$r}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }
    }

    /** Blok header: bold, tengah, wrap, border tipis di semua sisi. */
    protected function rekapHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
    }

    /** Baris data: garis luar & garis tegak tipis, garis antar-baris halus (hair). */
    protected function rekapBarisData(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray(['borders' => [
            'outline' => ['borderStyle' => Border::BORDER_THIN],
            'vertical' => ['borderStyle' => Border::BORDER_THIN],
            'horizontal' => ['borderStyle' => Border::BORDER_HAIR],
        ]]);
    }

    /** Baris TOTAL: bold + border tipis. */
    protected function rekapBarisTotal(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
    }

    /** Teks putih + bold (untuk banner PNS / PPPK yang berlatar gelap). */
    protected function rekapTeksPutih(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    }

    /** @param array<string,float|int> $lebar  kolom => lebar */
    protected function rekapLebarKolom(Worksheet $sheet, array $lebar): void
    {
        foreach ($lebar as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
    }

    protected array $data;

    protected array $eselonList = ['II A', 'II B', 'III A', 'III B', 'IV A', 'IV B'];

    protected int $headerRows = 8;

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapStrukturEselonInstansi($periode);
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

                $sheet->setCellValue('A1', 'REKAPITULASI PEJABAT ESELON PEMERINTAH DAERAH KAB/KOTA PEMERINTAH KOTA YOGYAKARTA');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT ESELON DAN JENIS KELAMIN');
                $sheet->setCellValue('A3', 'KEADAAN : ' . $this->formatPeriode($this->periode));
                $sheet->mergeCells('A1:Q1');
                $sheet->mergeCells('A2:Q2');
                $sheet->mergeCells('A3:Q3');

                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'INSTANSI');
                $sheet->setCellValue('C5', 'PRIA / ESELON');
                $sheet->setCellValue('I5', 'JML');
                $sheet->setCellValue('J5', 'WANITA / ESELON');
                $sheet->setCellValue('P5', 'JML');
                $sheet->setCellValue('Q5', 'JML TOTAL');

                $sheet->mergeCells('A5:A7');
                $sheet->mergeCells('B5:B7');
                $sheet->mergeCells('C5:H5');
                $sheet->mergeCells('I5:I7');
                $sheet->mergeCells('J5:O5');
                $sheet->mergeCells('P5:P7');
                $sheet->mergeCells('Q5:Q7');

                foreach ($this->eselonList as $index => $eselon) {
                    $sheet->setCellValue([$index + 3, 7], $eselon);
                    $sheet->setCellValue([$index + 10, 7], $eselon);
                }

                // Baris nomor kolom (1 s/d 17) tepat di bawah judul golongan
                for ($kolom = 1; $kolom <= 17; $kolom++) {
                    $sheet->setCellValue([$kolom, 8], $kolom);
                }

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                foreach (range('C', 'Q') as $column) {
                    $sum = 0;
                    for ($row = $this->headerRows + 1; $row <= $lastDataRow; $row++) {
                        $sum += (float) $sheet->getCell($column . $row)->getValue();
                    }
                    $sheet->setCellValue($column . $totalRow, $sum);
                }

                // ===== Styling (mengikuti sheet "struk es" di file data pegawai) =====
                $firstDataRow = $this->headerRows + 1;

                $this->rekapJudul($sheet);
                $this->rekapHeader($sheet, 'A5:Q8');
                $this->rekapBarisData($sheet, "A{$firstDataRow}:Q{$lastDataRow}");
                $this->rekapBarisTotal($sheet, "A{$totalRow}:Q{$totalRow}");

                // Nama eselon (baris 7): biru muda
                $this->rekapWarnai($sheet, 'C7:H7', $this->warnaBiruMuda);
                $this->rekapWarnai($sheet, 'J7:O7', $this->warnaBiruMuda);

                // Kolom JML pria (I) & JML wanita (P): ungu muda
                $this->rekapWarnai($sheet, "I{$firstDataRow}:I{$lastDataRow}", $this->warnaUngu);
                $this->rekapWarnai($sheet, "P{$firstDataRow}:P{$lastDataRow}", $this->warnaUngu);

                // Baris TOTAL: tebal ukuran 12, tanpa warna (sama seperti contoh)
                $sheet->getStyle("A{$totalRow}:Q{$totalRow}")->getFont()->setSize(12);

                $sheet->getRowDimension(7)->setRowHeight(20);

                $this->rekapLebarKolom($sheet, [
                    'A' => 5, 'B' => 113.11,
                    'C' => 6.6, 'D' => 6.6, 'E' => 6.6, 'F' => 6.6, 'G' => 6.6, 'H' => 6.6,
                    'I' => 11.66,
                    'J' => 6.6, 'K' => 6.6, 'L' => 6.6, 'M' => 6.6, 'N' => 6.6, 'O' => 6.6,
                    'P' => 11.66, 'Q' => 11,
                ]);
            },
        ];
    }
}