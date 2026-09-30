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

class RekapStrukturGolonganExport implements FromArray, WithEvents
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
                $sheet->setCellValue('A3', 'KEADAAN ' . $this->formatPeriode($this->periode));
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

                // ===== Styling (mengikuti sheet "struk gol" di file data pegawai) =====
                $firstDataRow = $this->headerRows + 1;

                $this->rekapJudul($sheet);
                $this->rekapHeader($sheet, 'A5:U7');
                $this->rekapBarisData($sheet, "A{$firstDataRow}:U{$lastDataRow}");
                $this->rekapBarisTotal($sheet, "A{$totalRow}:U{$totalRow}");

                // Nama golongan (baris 7): biru muda
                $this->rekapWarnai($sheet, 'C7:J7', $this->warnaBiruMuda);
                $this->rekapWarnai($sheet, 'L7:S7', $this->warnaBiruMuda);

                // Kolom JML pria (K) & JML wanita (T): ungu muda
                $this->rekapWarnai($sheet, "K{$firstDataRow}:K{$lastDataRow}", $this->warnaUngu);
                $this->rekapWarnai($sheet, "T{$firstDataRow}:T{$lastDataRow}", $this->warnaUngu);

                // Baris TOTAL: tebal ukuran 12, tanpa warna (sama seperti contoh)
                $sheet->getStyle("A{$totalRow}:U{$totalRow}")->getFont()->setSize(12);

                $sheet->getRowDimension(7)->setRowHeight(28.8);
                $this->rekapLebarKolom($sheet, [
                    'A' => 5, 'B' => 113.11,
                    'C' => 6.6, 'D' => 6.6, 'E' => 6.6, 'F' => 6.6,
                    'G' => 6.6, 'H' => 6.6, 'I' => 6.6, 'J' => 6.6,
                    'K' => 8,
                    'L' => 6.6, 'M' => 6.6, 'N' => 6.6, 'O' => 6.6,
                    'P' => 6.6, 'Q' => 6.6, 'R' => 6.6, 'S' => 6.6,
                    'T' => 8, 'U' => 11,
                ]);
            },
        ];
    }
}