<?php

namespace App\Exports;

use App\Exports\Concerns\PrintsConsistently;
use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Rekap Guru Fungsional per SD Negeri.
 *
 * Tampilan disamakan dengan sheet-sheet di file "data pegawai" dan dengan
 * RekapAgamaExport: judul kapital bold rata tengah, header bergaris tipis,
 * kolom JUMLAH biru muda, baris TOTAL oranye muda, garis antar-baris "hair".
 */
class RekapSdExport implements FromArray, WithEvents
{
    use PrintsConsistently;

    protected array $data;

    /** 1: judul, 2: keadaan, 3: kosong, 4: header kolom. Data mulai baris 5. */
    protected int $headerRows = 4;

    // Warna disamakan dengan RekapAgamaExport / sheet "agama" di file data pegawai
    protected string $warnaBiru = 'DCE6F2';   // kolom JUMLAH
    protected string $warnaOranye = 'FCD5B5'; // baris TOTAL

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapSdFungsional($periode);
    }

    public function array(): array
    {
        $rows = [];
        $no = 1;
        foreach ($this->data as $d) {
            $rows[] = [$no++, $d['sekolah'], $d['pria'], $d['wanita'], $d['jumlah']];
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
        [$tahun, $bln] = array_pad(explode('-', $periode), 2, '');
        return trim(($bulan[$bln] ?? $bln) . ' ' . $tahun);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $thin = Border::BORDER_THIN;
                $hair = Border::BORDER_HAIR;

                $sheet->insertNewRowBefore(1, $this->headerRows);

                $headerRow = $this->headerRows;                       // 4
                $firstDataRow = $this->headerRows + 1;                // 5
                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;

                /*
                |--------------------------------------------------------------
                | JUDUL (baris 1-2): bold, kapital, rata tengah
                |--------------------------------------------------------------
                */
                $sheet->setCellValue('A1', 'REKAP DATA FUNGSIONAL SD');
                $sheet->setCellValue('A2', 'KEADAAN : ' . $this->formatPeriode($this->periode));
                foreach ([1, 2] as $r) {
                    $sheet->mergeCells("A{$r}:E{$r}");
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                /*
                |--------------------------------------------------------------
                | HEADER KOLOM (baris 4): bold, tengah, border tipis
                |--------------------------------------------------------------
                */
                $sheet->setCellValue("A{$headerRow}", 'NO');
                $sheet->setCellValue("B{$headerRow}", 'SD');
                $sheet->setCellValue("C{$headerRow}", 'PRIA');
                $sheet->setCellValue("D{$headerRow}", 'WANITA');
                $sheet->setCellValue("E{$headerRow}", 'JUMLAH');

                $sheet->getStyle("A{$headerRow}:E{$headerRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$headerRow}:E{$headerRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle("A{$headerRow}:E{$headerRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);
                $sheet->getStyle("E{$headerRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($this->warnaBiru);
                $sheet->getRowDimension($headerRow)->setRowHeight(20);

                /*
                |--------------------------------------------------------------
                | BARIS DATA: garis kiri/kanan tipis, antar-baris "hair"
                | (dilewati bila belum ada sekolah supaya range tidak terbalik)
                |--------------------------------------------------------------
                */
                if (count($this->data) > 0) {
                    for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                        $borders = $sheet->getStyle("A{$r}:E{$r}")->getBorders();
                        $borders->getLeft()->setBorderStyle($thin);
                        $borders->getRight()->setBorderStyle($thin);
                        $borders->getVertical()->setBorderStyle($thin);
                        $borders->getTop()->setBorderStyle($r === $firstDataRow ? $thin : $hair);
                        $borders->getBottom()->setBorderStyle($r === $lastDataRow ? $thin : $hair);
                    }

                    $sheet->getStyle("A{$firstDataRow}:E{$lastDataRow}")->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $sheet->getStyle("A{$firstDataRow}:A{$lastDataRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B{$firstDataRow}:B{$lastDataRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("C{$firstDataRow}:E{$lastDataRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Kolom JUMLAH: biru muda + bold
                    $sheet->getStyle("E{$firstDataRow}:E{$lastDataRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($this->warnaBiru);
                    $sheet->getStyle("E{$firstDataRow}:E{$lastDataRow}")->getFont()->setBold(true);
                }

                /*
                |--------------------------------------------------------------
                | BARIS TOTAL: bold, border tipis, angka oranye muda
                |--------------------------------------------------------------
                */
                $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");
                $sheet->setCellValue("C{$totalRow}", (int) array_sum(array_column($this->data, 'pria')));
                $sheet->setCellValue("D{$totalRow}", (int) array_sum(array_column($this->data, 'wanita')));
                $sheet->setCellValue("E{$totalRow}", (int) array_sum(array_column($this->data, 'jumlah')));

                $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("C{$totalRow}:E{$totalRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($this->warnaOranye);

                /*
                |--------------------------------------------------------------
                | LEBAR KOLOM & TAMPILAN
                |--------------------------------------------------------------
                */
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(45);
                $sheet->getColumnDimension('C')->setWidth(12);
                $sheet->getColumnDimension('D')->setWidth(12);
                $sheet->getColumnDimension('E')->setWidth(12);

                $sheet->setShowGridLines(true);
                $sheet->freezePane('A' . $firstDataRow);

                // Cetak: A4 portrait, muat 1 halaman lebar, header kolom (baris 4) diulang
                $this->applyPrintSetup($sheet, "A1:E{$totalRow}", $headerRow, 'portrait', $headerRow);
            },
        ];
    }
}