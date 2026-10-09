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
 * Rekap ASN yang bekerja di Kecamatan (Kemantren) / Kelurahan.
 *
 * Tampilan disamakan dengan sheet-sheet di file "data pegawai" dan dengan
 * RekapAgamaExport: judul kapital bold rata tengah, blok header 3 baris
 * (baris 5-7) dengan border tipis, kolom total berwarna biru muda,
 * baris TOTAL berwarna oranye muda, garis antar-baris "hair".
 *
 * Kolom (A-R):
 *   A No | B Kemantren | C Alamat | D-K Kecamatan (Fungsional, Struktural,
 *   Pelaksana, Total; masing-masing L/P) | L Kelurahan | M-R Kelurahan
 *   (Struktural, Pelaksana, Total; masing-masing L/P)
 */
class RekapKecamatanExport implements FromArray, WithEvents
{
    use PrintsConsistently;

    protected array $data;

    /** 3 baris judul + 1 baris kosong + 3 baris header tabel (baris 5-7). */
    protected int $headerRows = 7;

    // Warna disamakan dengan RekapAgamaExport / sheet "agama" di file data pegawai
    protected string $warnaBiru = 'DCE6F2';   // kolom total & sub-header total
    protected string $warnaOranye = 'FCD5B5'; // baris TOTAL

    /** Kolom angka => key di hasil RekapService::rekapKecamatanKelurahan(). */
    protected array $kolomAngka = [
        'D' => 'fungsional_l',
        'E' => 'fungsional_p',
        'F' => 'struktural_l',
        'G' => 'struktural_p',
        'H' => 'pelaksana_l',
        'I' => 'pelaksana_p',
        'J' => 'kecamatan_total_l',
        'K' => 'kecamatan_total_p',
        'M' => 'kel_struktural_l',
        'N' => 'kel_struktural_p',
        'O' => 'kel_pelaksana_l',
        'P' => 'kel_pelaksana_p',
        'Q' => 'kelurahan_total_l',
        'R' => 'kelurahan_total_p',
    ];

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())
            ->rekapKecamatanKelurahan($periode);
    }

    /**
     * Data Excel. Kolom Kemantren/Alamat/Kecamatan hanya terisi di baris
     * pertama tiap grup kemantren (baris lain null) karena kolom A-K
     * di-merge per grup pada registerEvents().
     */
    public function array(): array
    {
        $rows = [];
        $no = 0;

        foreach ($this->data as $d) {
            if ($d['jumlah_baris_kemantren'] !== null) {
                $no++;
            }

            $rows[] = [
                $d['jumlah_baris_kemantren'] !== null ? $no : null,
                $d['kemantren'],
                $d['kemantren_alamat'],

                $d['fungsional_l'],
                $d['fungsional_p'],
                $d['struktural_l'],
                $d['struktural_p'],
                $d['pelaksana_l'],
                $d['pelaksana_p'],
                $d['kecamatan_total_l'],
                $d['kecamatan_total_p'],

                $d['kelurahan'],
                $d['kel_struktural_l'],
                $d['kel_struktural_p'],
                $d['kel_pelaksana_l'],
                $d['kel_pelaksana_p'],
                $d['kelurahan_total_l'],
                $d['kelurahan_total_p'],
            ];
        }

        return $rows;
    }

    /** "2026-08" => "AGUSTUS 2026" (kapital, sama dengan RekapAgamaExport). */
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
                $thin = Border::BORDER_THIN;
                $hair = Border::BORDER_HAIR;

                // Sisipkan 7 baris di atas data (judul + header tabel)
                $sheet->insertNewRowBefore(1, $this->headerRows);

                $firstDataRow = $this->headerRows + 1;               // 8
                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;
                $boxRow = $totalRow + 2;                              // TOTAL L (P di baris berikutnya)
                $lastRow = $boxRow + 1;

                /*
                |--------------------------------------------------------------
                | JUDUL (baris 1-3): bold, kapital, rata tengah
                |--------------------------------------------------------------
                */
                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH ASN YANG BEKERJA DI KECAMATAN / KELURAHAN');
                $sheet->setCellValue('A2', 'KOTA YOGYAKARTA');
                $sheet->setCellValue('A3', 'KEADAAN : ' . $this->formatPeriode($this->periode));

                foreach ([1, 2, 3] as $r) {
                    $sheet->mergeCells("A{$r}:R{$r}");
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                /*
                |--------------------------------------------------------------
                | HEADER TABEL (baris 5-7)
                |--------------------------------------------------------------
                */
                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'KEMANTREN');
                $sheet->setCellValue('C5', 'ALAMAT KEMANTREN');
                $sheet->setCellValue('D5', 'JUMLAH PNS YANG BEKERJA DI KECAMATAN');
                $sheet->setCellValue('L5', 'KELURAHAN');
                $sheet->setCellValue('M5', 'JUMLAH PNS YANG BEKERJA DI KELURAHAN');

                foreach (['A', 'B', 'C', 'L'] as $col) {
                    $sheet->mergeCells("{$col}5:{$col}7");
                }
                $sheet->mergeCells('D5:K5');
                $sheet->mergeCells('M5:R5');

                // [kolom L, kolom P, label kategori, ikut warna biru?]
                $groups = [
                    ['D', 'E', 'FUNGSIONAL', false],
                    ['F', 'G', 'STRUKTURAL', false],
                    ['H', 'I', 'PELAKSANA', false],
                    ['J', 'K', 'TOTAL KECAMATAN', true],
                    ['M', 'N', 'STRUKTURAL', false],
                    ['O', 'P', 'PELAKSANA', false],
                    ['Q', 'R', 'TOTAL KELURAHAN', true],
                ];

                foreach ($groups as [$colL, $colP, $label, $biru]) {
                    $sheet->setCellValue("{$colL}6", $label);
                    $sheet->mergeCells("{$colL}6:{$colP}6");
                    $sheet->setCellValue("{$colL}7", 'Laki-Laki');
                    $sheet->setCellValue("{$colP}7", 'Perempuan');

                    if ($biru) {
                        $sheet->getStyle("{$colL}6:{$colP}7")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB($this->warnaBiru);
                    }
                }

                $sheet->getStyle('A5:R7')->getFont()->setBold(true);
                $sheet->getStyle('A5:R7')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle('A5:R7')->getBorders()->getAllBorders()->setBorderStyle($thin);

                /*
                |--------------------------------------------------------------
                | ISI: merge kolom A-K per kemantren, border, tinggi baris
                |
                | Excel tidak auto-fit tinggi baris untuk sel yang di-merge,
                | jadi tinggi dihitung dari perkiraan jumlah baris teks alamat
                | lalu dibagi rata ke seluruh baris dalam grup.
                |--------------------------------------------------------------
                */
                $charsPerLine = 50;   // perkiraan karakter per baris di kolom C (lebar 52)
                $lineHeightPt = 15;
                $minRowHeight = 24;

                $row = $firstDataRow;
                while ($row <= $lastDataRow) {
                    $d = $this->data[$row - $firstDataRow];
                    $span = max(1, (int) ($d['jumlah_baris_kemantren'] ?? 1));
                    $endRow = min($row + $span - 1, $lastDataRow);

                    if ($endRow > $row) {
                        foreach (range('A', 'K') as $col) {
                            $sheet->mergeCells("{$col}{$row}:{$col}{$endRow}");
                        }
                    }

                    // Kolom A-K (satu blok per kemantren): kotak luar + garis tegak
                    $sheet->getStyle("A{$row}:K{$endRow}")->applyFromArray(['borders' => [
                        'outline' => ['borderStyle' => $thin],
                        'vertical' => ['borderStyle' => $thin],
                    ]]);

                    // Kolom L-R (satu baris per kelurahan): garis antar-baris halus
                    $sheet->getStyle("L{$row}:R{$endRow}")->applyFromArray(['borders' => [
                        'outline' => ['borderStyle' => $thin],
                        'vertical' => ['borderStyle' => $thin],
                        'horizontal' => ['borderStyle' => $hair],
                    ]]);

                    // Tinggi baris (alamat panjang di-wrap ke bawah)
                    $alamat = (string) ($d['kemantren_alamat'] ?? '');
                    $lines = $alamat !== '' ? (int) ceil(mb_strlen($alamat) / $charsPerLine) : 1;
                    $needed = max($minRowHeight * ($endRow - $row + 1), $lines * $lineHeightPt + 6);
                    $perRow = $needed / ($endRow - $row + 1);

                    for ($r = $row; $r <= $endRow; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight($perRow);
                    }

                    $row = $endRow + 1;
                }

                // Alignment isi tabel
                $sheet->getStyle("A{$firstDataRow}:R{$lastDataRow}")->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A{$firstDataRow}:A{$lastDataRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$firstDataRow}:C{$lastDataRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setWrapText(true);
                $sheet->getStyle("D{$firstDataRow}:K{$lastDataRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("L{$firstDataRow}:L{$lastDataRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("M{$firstDataRow}:R{$lastDataRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Nama kemantren tebal
                $sheet->getStyle("B{$firstDataRow}:B{$lastDataRow}")->getFont()->setBold(true);

                // Kolom total (Kecamatan J:K, Kelurahan Q:R): biru muda + bold
                foreach (['J:K', 'Q:R'] as $kolom) {
                    [$a, $b] = explode(':', $kolom);
                    $range = "{$a}{$firstDataRow}:{$b}{$lastDataRow}";
                    $sheet->getStyle($range)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($this->warnaBiru);
                    $sheet->getStyle($range)->getFont()->setBold(true);
                }

                /*
                |--------------------------------------------------------------
                | BARIS TOTAL: bold, border tipis, angka oranye muda
                |--------------------------------------------------------------
                */
                $sheet->setCellValue("A{$totalRow}", 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:C{$totalRow}");

                $jumlah = [];
                foreach ($this->kolomAngka as $col => $key) {
                    $jumlah[$key] = (int) array_sum(array_column($this->data, $key));
                    $sheet->setCellValue("{$col}{$totalRow}", $jumlah[$key]);
                }

                $sheet->getStyle("A{$totalRow}:R{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$totalRow}:K{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);
                $sheet->getStyle("L{$totalRow}:R{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);
                $sheet->getStyle("A{$totalRow}:R{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                foreach (["D{$totalRow}:K{$totalRow}", "M{$totalRow}:R{$totalRow}"] as $range) {
                    $sheet->getStyle($range)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($this->warnaOranye);
                }
                $sheet->getRowDimension($totalRow)->setRowHeight(22);

                /*
                |--------------------------------------------------------------
                | TOTAL L / TOTAL P (Kecamatan + Kelurahan)
                |--------------------------------------------------------------
                */
                $grand = [
                    ['TOTAL L (Kecamatan + Kelurahan)', $jumlah['kecamatan_total_l'] + $jumlah['kelurahan_total_l']],
                    ['TOTAL P (Kecamatan + Kelurahan)', $jumlah['kecamatan_total_p'] + $jumlah['kelurahan_total_p']],
                ];

                foreach ($grand as $i => [$label, $nilai]) {
                    $r = $boxRow + $i;
                    $sheet->setCellValue("A{$r}", $label);
                    $sheet->mergeCells("A{$r}:C{$r}");
                    $sheet->setCellValue("D{$r}", $nilai);
                    $sheet->getStyle("A{$r}:D{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}:D{$r}")->getBorders()->getAllBorders()->setBorderStyle($thin);
                    $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($this->warnaOranye);
                }

                /*
                |--------------------------------------------------------------
                | LEBAR KOLOM & TINGGI HEADER
                |--------------------------------------------------------------
                */
                $lebar = ['A' => 5, 'B' => 20, 'C' => 52, 'L' => 22];
                foreach (range('A', 'R') as $col) {
                    $sheet->getColumnDimension($col)->setWidth($lebar[$col] ?? 11);
                }
                foreach ([5, 6, 7] as $r) {
                    $sheet->getRowDimension($r)->setRowHeight(20);
                }

                /*
                |--------------------------------------------------------------
                | TAMPILAN & CETAK
                |
                | Freeze kolom A-B (No, Kemantren) + header tabel. Cetak mengikuti
                | PrintsConsistently: A4 landscape, muat 1 halaman lebar, baris
                | header 5-7 diulang di tiap halaman.
                |--------------------------------------------------------------
                */
                $sheet->setShowGridLines(true);
                $sheet->freezePane("C{$firstDataRow}");
                $this->applyPrintSetup($sheet, "A1:R{$lastRow}", 7, 'landscape', 5);
            },
        ];
    }
}