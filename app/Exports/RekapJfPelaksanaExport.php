<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapJfPelaksanaExport implements FromArray, WithEvents
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

    protected array $displayOrder = [
        'I/a', 'I', 'I/b', 'I/c', 'III', 'I/d',
        'II/a', 'V', 'II/b', 'II/c', 'VII', 'II/d',
        'III/a', 'IX', 'III/b', 'X', 'III/c', 'III/d',
        'IV/a', 'IV/b', 'IV/c', 'IV/d', 'IV/e',
    ];

    protected array $pnsAggList = ['I', 'II', 'III', 'IV'];
    protected array $pppkAggList = ['I', 'III', 'V', 'VII', 'IX', 'X'];

    protected int $headerRows = 7;

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapJfPelaksana($periode);
    }

    public function array(): array
    {
        $rows = [];
        $no = 1;

        foreach ($this->data as $d) {
            $pria = array_map(fn ($k) => $d['pria'][$k] ?? 0, $this->displayOrder);
            $wanita = array_map(fn ($k) => $d['wanita'][$k] ?? 0, $this->displayOrder);

            $rows[] = array_merge(
                [$no++, $d['instansi']],
                $pria,
                [$d['jml_pria']],
                $wanita,
                [$d['jml_wanita']],
                [$d['jml_total']],
                [''],
                [$d['instansi']],   // nama instansi diulang di depan blok PNS/PPPK
                array_values($d['pns_agg']),
                [$d['pns_total']],
                array_values($d['pppk']),
                [$d['pppk_total']],
            );
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
                $L = fn (int $i) => Coordinate::stringFromColumnIndex($i);

                $n = count($this->displayOrder);

                $colPriaStart = 3;
                $colPriaEnd = $colPriaStart + $n - 1;
                $colJmlPria = $colPriaEnd + 1;
                $colWanitaStart = $colJmlPria + 1;
                $colWanitaEnd = $colWanitaStart + $n - 1;
                $colJmlWanita = $colWanitaEnd + 1;
                $colJmlTotal = $colJmlWanita + 1;
                $colSpacer = $colJmlTotal + 1;
                $colInstansi2 = $colSpacer + 1;   // nama instansi diulang di depan blok PNS/PPPK
                $colPnsStart = $colInstansi2 + 1;
                $colPnsEnd = $colPnsStart + count($this->pnsAggList);
                $colPppkStart = $colPnsEnd + 1;
                $colPppkEnd = $colPppkStart + count($this->pppkAggList);

                $lastCol = $colPppkEnd;
                $mainLast = $L($colJmlTotal);   // kolom terakhir tabel utama

                $sheet->insertNewRowBefore(1, $this->headerRows);

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH ASN FUNGSIONAL UMUM ATAU PELAKSANA PEMERINTAH DAERAH/KABUPATEN/KOTA PEMERINTAH KOTA YOGYAKARTA');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT INSTANSI, GOLONGAN RUANG DAN JENIS KELAMIN');
                $sheet->setCellValue('A3', 'KEADAAN : ' . $this->formatPeriode($this->periode));
                $sheet->mergeCells('A1:' . $mainLast . '1');
                $sheet->mergeCells('A2:' . $mainLast . '2');
                $sheet->mergeCells('A3:' . $mainLast . '3');

                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'INSTANSI');
                $sheet->setCellValue($L($colPriaStart) . '5', 'PRIA');
                $sheet->setCellValue($L($colJmlPria) . '5', 'JML');
                $sheet->setCellValue($L($colWanitaStart) . '5', 'WANITA');
                $sheet->setCellValue($L($colJmlWanita) . '5', 'JML');
                $sheet->setCellValue($L($colJmlTotal) . '5', 'JML TOTAL');
                $sheet->setCellValue($L($colInstansi2) . '5', 'INSTANSI');
                $sheet->setCellValue($L($colPnsStart) . '5', 'PNS');
                $sheet->setCellValue($L($colPppkStart) . '5', 'PPPK');

                $sheet->mergeCells('A5:A7');
                $sheet->mergeCells('B5:B7');
                $sheet->mergeCells($L($colPriaStart) . '5:' . $L($colPriaEnd) . '6');
                $sheet->mergeCells($L($colJmlPria) . '5:' . $L($colJmlPria) . '7');
                $sheet->mergeCells($L($colWanitaStart) . '5:' . $L($colWanitaEnd) . '6');
                $sheet->mergeCells($L($colJmlWanita) . '5:' . $L($colJmlWanita) . '7');
                $sheet->mergeCells($L($colJmlTotal) . '5:' . $L($colJmlTotal) . '7');
                $sheet->mergeCells($L($colInstansi2) . '5:' . $L($colInstansi2) . '7');
                $sheet->mergeCells($L($colPnsStart) . '5:' . $L($colPnsEnd) . '6');
                $sheet->mergeCells($L($colPppkStart) . '5:' . $L($colPppkEnd) . '6');

                foreach ($this->displayOrder as $i => $nama) {
                    $sheet->setCellValue($L($colPriaStart + $i) . '7', $nama);
                    $sheet->setCellValue($L($colWanitaStart + $i) . '7', $nama);
                }

                foreach ($this->pnsAggList as $i => $romawi) {
                    $sheet->setCellValue($L($colPnsStart + $i) . '7', $romawi);
                }
                $sheet->setCellValue($L($colPnsEnd) . '7', 'Total');

                foreach ($this->pppkAggList as $i => $romawi) {
                    $sheet->setCellValue($L($colPppkStart + $i) . '7', $romawi);
                }
                $sheet->setCellValue($L($colPppkEnd) . '7', 'Total');

                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;
                $firstDataRow = $this->headerRows + 1;

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");
                $sheet->setCellValue($L($colInstansi2) . $totalRow, 'TOTAL');

                $kolomAngka = array_merge(
                    range($colPriaStart, $colJmlTotal),
                    range($colPnsStart, $colPppkEnd)
                );
                foreach ($kolomAngka as $colIdx) {
                    $col = $L($colIdx);
                    $sum = 0;
                    for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }

                // ===== Styling (mengikuti sheet "jf pelaksana" di file data pegawai) =====
                $inst2 = $L($colInstansi2);
                $pnsFirst = $L($colPnsStart);
                $pnsLast = $L($colPnsEnd);
                $pppkFirst = $L($colPppkStart);
                $pppkLast = $L($colPppkEnd);
                $last = $L($lastCol);

                $this->rekapJudul($sheet);

                // Tabel utama (A .. JML TOTAL)
                $this->rekapHeader($sheet, "A5:{$mainLast}7");
                $this->rekapBarisData($sheet, "A{$firstDataRow}:{$mainLast}{$lastDataRow}");
                $this->rekapBarisTotal($sheet, "A{$totalRow}:{$mainLast}{$totalRow}");

                // Blok samping (INSTANSI + PNS + PPPK)
                $this->rekapHeader($sheet, "{$inst2}5:{$last}7");
                $this->rekapBarisData($sheet, "{$inst2}{$firstDataRow}:{$last}{$lastDataRow}");
                $this->rekapBarisTotal($sheet, "{$inst2}{$totalRow}:{$last}{$totalRow}");

                // Banner PNS (navy) & PPPK (ungu) — teks putih
                $this->rekapWarnai($sheet, "{$pnsFirst}5:{$pnsLast}6", $this->warnaNavy);
                $this->rekapWarnai($sheet, "{$pppkFirst}5:{$pppkLast}6", $this->warnaViolet);
                $this->rekapTeksPutih($sheet, "{$pnsFirst}5");
                $this->rekapTeksPutih($sheet, "{$pppkFirst}5");
                $sheet->getStyle("{$pnsFirst}5")->getFont()->setSize(16);
                $sheet->getStyle("{$pppkFirst}5")->getFont()->setSize(14);

                // Warna header golongan (baris 7) — dikelompokkan seperti di contoh
                $warnaHeaderGol = [
                    'I/a' => 'F2DCDB', 'I' => 'F2DCDB', 'I/c' => 'F2DCDB', 'III' => 'F2DCDB',
                    'I/b' => $this->warnaBiruMuda,
                    'II/a' => 'FDEADA', 'V' => 'FDEADA', 'II/c' => 'FDEADA',
                    'VII' => 'FDEADA', 'III/a' => 'FDEADA', 'IX' => 'FDEADA',
                    'III/b' => $this->warnaOranye, 'X' => $this->warnaOranye,
                ];
                // Warna badan kolom: golongan I/a, I/b, I/c biru muda; PPPK I & III merah muda pucat;
                // PPPK V, VII, IX, X merah muda
                $warnaBadanGol = [
                    'I/a' => $this->warnaBiruMuda, 'I/b' => $this->warnaBiruMuda, 'I/c' => $this->warnaBiruMuda,
                    'I' => 'F2DCDB', 'III' => 'F2DCDB',
                    'V' => $this->warnaMerahMuda, 'VII' => $this->warnaMerahMuda,
                    'IX' => $this->warnaMerahMuda, 'X' => $this->warnaMerahMuda,
                ];
                foreach ($this->displayOrder as $i => $nama) {
                    foreach ([$colPriaStart + $i, $colWanitaStart + $i] as $idx) {
                        $c = $L($idx);
                        if (isset($warnaHeaderGol[$nama])) {
                            $this->rekapWarnai($sheet, "{$c}7", $warnaHeaderGol[$nama]);
                        }
                        if (isset($warnaBadanGol[$nama])) {
                            $this->rekapWarnai($sheet, "{$c}{$firstDataRow}:{$c}{$totalRow}", $warnaBadanGol[$nama]);
                        }
                    }
                }

                // Kolom JML pria / JML wanita / JML TOTAL
                $cJmlPria = $L($colJmlPria);
                $cJmlWanita = $L($colJmlWanita);
                $cJmlTotal = $L($colJmlTotal);
                $this->rekapWarnai($sheet, "{$cJmlPria}{$firstDataRow}:{$cJmlPria}{$totalRow}", $this->warnaTosca);
                $this->rekapWarnai($sheet, "{$cJmlWanita}{$firstDataRow}:{$cJmlWanita}{$totalRow}", $this->warnaTosca);
                $this->rekapWarnai($sheet, "{$cJmlTotal}5:{$cJmlTotal}7", '93CDDD');
                $this->rekapWarnai($sheet, "{$cJmlTotal}{$firstDataRow}:{$cJmlTotal}{$totalRow}", '93CDDD');

                // Blok PNS (I-IV hijau, Total hijau muda)
                $pnsAgg = $L($colPnsEnd - 1);
                $this->rekapWarnai($sheet, "{$pnsFirst}7:{$pnsAgg}7", 'D7E4BD');
                $this->rekapWarnai($sheet, "{$pnsFirst}{$firstDataRow}:{$pnsAgg}{$lastDataRow}", 'C3D69B');
                $this->rekapWarnai($sheet, "{$pnsFirst}{$totalRow}:{$pnsAgg}{$totalRow}", 'D7E4BD');
                $this->rekapWarnai($sheet, "{$pnsLast}7:{$pnsLast}{$totalRow}", 'EBF1DE');

                // Blok PPPK (toscamuda, kolom Total tosca)
                $pppkAgg = $L($colPppkEnd - 1);
                $this->rekapWarnai($sheet, "{$pppkFirst}7:{$pppkAgg}{$totalRow}", $this->warnaToscaMuda);
                $this->rekapWarnai($sheet, "{$pppkLast}7:{$pppkLast}{$totalRow}", $this->warnaTosca);
                $this->rekapWarnai($sheet, "{$pppkFirst}7:" . $L($colPppkStart + 1) . '7', 'EBF1DE');

                // Lebar kolom
                $lebar = ['A' => 5, 'B' => 113.11];
                for ($i = 0; $i < $n; $i++) {
                    $lebar[$L($colPriaStart + $i)] = 6.6;
                    $lebar[$L($colWanitaStart + $i)] = 6.6;
                }
                $lebar[$cJmlPria] = 8;
                $lebar[$cJmlWanita] = 8;
                $lebar[$cJmlTotal] = 12;
                $lebar[$L($colSpacer)] = 3;
                $lebar[$inst2] = 60;
                for ($idx = $colPnsStart; $idx <= $colPppkEnd; $idx++) {
                    $lebar[$L($idx)] = 6.5;
                }
                $lebar[$pnsLast] = 9.5;
                $lebar[$pppkLast] = 9.5;
                $this->rekapLebarKolom($sheet, $lebar);

                $sheet->getRowDimension(7)->setRowHeight(29.4);
            },
        ];
    }
}