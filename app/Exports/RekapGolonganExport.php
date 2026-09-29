<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Layout kolom di export ini meniru PERSIS format resmi
 * "data-asn-agustus-2026" (sheet "gol"):
 *
 *  C..Z   (24 kolom) : Pria  - golongan PNS & PPPK diselang-seling
 *  AA               : Sub Total Pria
 *  AB..AY (24 kolom) : Wanita - urutan sama seperti Pria
 *  AZ               : Sub Total Wanita
 *  BA               : TOTAL
 *  BB, BC           : kosong (spacer, sesuai referensi)
 *  BD..BH           : blok PNS (I, II, III, IV, Total)
 *  BI..BP           : blok PPPK detail (I/1a, III/1c, ... , Total)
 */
class RekapGolonganExport implements FromArray, WithEvents
{
    protected array $data;

    // Urutan tampil 24 kolom Pria/Wanita, sesuai template referensi.
    // Setiap key di sini adalah key yang sama persis dengan yang dipakai
    // RekapService::rekapGolongan() di $d['pria'] / $d['wanita'].
    protected array $golonganDisplayOrder = [
        'I/a', 'I', 'I/b', 'I/c', 'III', 'I/d',
        'II/a', 'V', 'II/b', 'II/c', 'VII', 'II/d',
        'III/a', 'IX', 'III/b', 'X', 'III/c', 'XI', 'III/d',
        'IV/a', 'IV/b', 'IV/c', 'IV/d', 'IV/e',
    ];

    // Urutan ini HARUS sama dengan urutan $pppkList di RekapService,
    // supaya array_values($d['pppk']) jatuh di label yang benar.
    protected array $pppkDetailLabel = [
        'I/1a', 'III/ 1c', 'V/2a', 'VII/2c', 'IX /3a', 'X/3b', 'XI/3c',
    ];

    protected array $pnsAggList = ['I', 'II', 'III', 'IV'];

    protected int $headerRows = 7;

    // Golongan PPPK (kolom bergaris romawi saja) di dalam $golonganDisplayOrder
    protected array $golonganPppk = ['I', 'III', 'V', 'VII', 'IX', 'X', 'XI'];

    // Warna disamakan dengan sheet "gol" di file data pegawai (palet tema Office)
    protected string $warnaHijau = 'D7E4BD';      // header golongan PNS
    protected string $warnaAqua = 'B7DEE8';       // golongan PPPK
    protected string $warnaAquaMuda = 'DBEEF4';   // Total PPPK
    protected string $warnaUngu = 'CCC1DA';       // Sub Total
    protected string $warnaBiru = 'DCE6F2';       // TOTAL
    protected string $warnaOranye = 'FCD5B5';     // baris TOTAL
    protected string $warnaNavy = '002060';       // label PNS
    protected string $warnaPurpleTua = '7030A0';  // label PPPK

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapGolongan($periode);
    }

    public function array(): array
    {
        $rows = [];
        $no = 1;
        foreach ($this->data as $d) {
            $pria = array_map(fn ($k) => $d['pria'][$k] ?? 0, $this->golonganDisplayOrder);
            $wanita = array_map(fn ($k) => $d['wanita'][$k] ?? 0, $this->golonganDisplayOrder);

            $rows[] = array_merge(
                [$no++, $d['instansi']],
                $pria,
                [$d['jml_pria']],
                $wanita,
                [$d['jml_wanita']],
                [$d['jml_total']],
                ['', ''], // BB, BC: spacer kosong (sesuai referensi)
                array_values($d['pns_agg']),   // BD-BG: I,II,III,IV
                [$d['pns_total']],              // BH
                array_values($d['pppk']),       // BI-BO
                [$d['pppk_total']]              // BP
            );
        }
        return $rows;
    }

    protected function formatPeriode(string $periode): string
    {
        $bulan = [
            '01'=>'JANUARI','02'=>'FEBRUARI','03'=>'MARET','04'=>'APRIL',
            '05'=>'MEI','06'=>'JUNI','07'=>'JULI','08'=>'AGUSTUS',
            '09'=>'SEPTEMBER','10'=>'OKTOBER','11'=>'NOVEMBER','12'=>'DESEMBER',
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

                $L = fn (int $i) => Coordinate::stringFromColumnIndex($i);

                // Judul
                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH ASN PEMERINTAH DAERAH/KABUPATEN/KOTA PEMERINTAH KOTA YOGYAKARTA');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT INSTANSI, GOLONGAN RUANG DAN JENIS KELAMIN');
                $sheet->setCellValue('A3', 'KEADAAN : ' . $this->formatPeriode($this->periode));
                $sheet->mergeCells('A1:BA1');
                $sheet->mergeCells('A2:BA2');
                $sheet->mergeCells('A3:BA3');

                // Header baris 5-7
                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'INSTANSI');
                $sheet->setCellValue('C5', 'PRIA');
                $sheet->setCellValue('AA5', 'Sub Total');
                $sheet->setCellValue('AB5', 'WANITA');
                $sheet->setCellValue('AZ5', 'Sub Total');
                $sheet->setCellValue('BA5', 'TOTAL');
                $sheet->setCellValue('BD5', 'PNS');
                $sheet->setCellValue('BI5', 'PPPK');

                $sheet->mergeCells('A5:A7');
                $sheet->mergeCells('B5:B7');
                $sheet->mergeCells('C5:Z6');   // label PRIA, di tengah blok C..Z
                $sheet->mergeCells('AA5:AA7');
                $sheet->mergeCells('AB5:AY6'); // label WANITA, di tengah blok AB..AY
                $sheet->mergeCells('AZ5:AZ7');
                $sheet->mergeCells('BA5:BA7');
                $sheet->mergeCells('BD5:BH6');
                $sheet->mergeCells('BI5:BP6'); // label PPPK, di tengah blok BI..BP

                // Sub-kolom golongan baris 7: Pria C..Z, Wanita AB..AY
                foreach ($this->golonganDisplayOrder as $i => $nama) {
                    $sheet->setCellValue($L(3 + $i) . '7', $nama);
                    $sheet->setCellValue($L(28 + $i) . '7', $nama);
                }

                // Blok PNS: BD..BG = I,II,III,IV ; BH = Total
                foreach ($this->pnsAggList as $i => $romawi) {
                    $sheet->setCellValue($L(56 + $i) . '7', $romawi);
                }
                $sheet->setCellValue('BH7', 'Total');

                // Blok PPPK detail: BI..BO ; BP = Total
                foreach ($this->pppkDetailLabel as $i => $label) {
                    $sheet->setCellValue($L(61 + $i) . '7', $label);
                }
                $sheet->setCellValue('BP7', 'Total');

                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                // SUM semua kolom angka: C..BA (Pria, Sub Total, Wanita, Sub Total, TOTAL)
                // dan BD..BP (PNS + PPPK). BB & BC (spacer) dilewati.
                $kolomAngka = array_merge(range(3, 53), range(56, 68));
                foreach ($kolomAngka as $colIdx) {
                    $col = $L($colIdx);
                    $sum = 0;
                    for ($r = $this->headerRows + 1; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }

                // ===== Styling (mengikuti sheet "gol" di file data pegawai) =====
                $thin = Border::BORDER_THIN;
                $hair = Border::BORDER_HAIR;
                $firstDataRow = $this->headerRows + 1;

                $warnai = function (string $range, string $rgb) use ($sheet) {
                    $sheet->getStyle($range)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($rgb);
                };
                $garisKotak = function (string $range) use ($sheet, $thin) {
                    $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle($thin);
                };
                // Garis tegak tipis (kiri/kanan tiap sel) + garis atas/bawah halus (hair),
                // dipakai untuk baris data supaya antar-baris tidak terlalu tebal.
                $garisDataPerKolom = function (string $kolomAwal, string $kolomAkhir) use ($sheet, $thin, $hair, $firstDataRow, $lastDataRow) {
                    for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                        $top = $r === $firstDataRow ? $thin : $hair;
                        $bottom = $r === $lastDataRow ? $thin : $hair;
                        $borders = $sheet->getStyle("{$kolomAwal}{$r}:{$kolomAkhir}{$r}")->getBorders();
                        $borders->getLeft()->setBorderStyle($thin);
                        $borders->getRight()->setBorderStyle($thin);
                        $borders->getTop()->setBorderStyle($top);
                        $borders->getBottom()->setBorderStyle($bottom);
                    }
                };

                // Posisi kolom golongan (0-based di $golonganDisplayOrder) -> kolom Pria / Wanita
                $kolomGolongan = [];   // [ [kolomPria, kolomWanita, isPppk], ... ]
                foreach ($this->golonganDisplayOrder as $i => $nama) {
                    $kolomGolongan[] = [$L(3 + $i), $L(28 + $i), in_array($nama, $this->golonganPppk, true)];
                }

                // Judul: bold & rata tengah
                foreach ([1, 2, 3] as $r) {
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                // Header baris 5-7: bold, rata tengah, border tipis
                $garisKotak('A5:BA7');
                $garisKotak('BD5:BP7');
                foreach (['A5:BA7', 'BD5:BP7'] as $range) {
                    $sheet->getStyle($range)->getFont()->setBold(true);
                    $sheet->getStyle($range)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);
                }

                // Header golongan (baris 7): PNS hijau, PPPK aqua
                foreach ($kolomGolongan as $g) {
                    [$kPria, $kWanita, $isPppk] = $g;
                    $warna = $isPppk ? $this->warnaAqua : $this->warnaHijau;
                    $warnai("{$kPria}7", $warna);
                    $warnai("{$kWanita}7", $warna);
                }
                $warnai('AA5:AA7', $this->warnaUngu);   // Sub Total Pria
                $warnai('AZ5:AZ7', $this->warnaUngu);   // Sub Total Wanita
                $warnai('BA5:BA7', $this->warnaBiru);   // TOTAL

                // Blok kanan: label PNS (navy) & PPPK (ungu tua) dengan huruf putih
                $warnai('BD5:BH6', $this->warnaNavy);
                $warnai('BI5:BP6', $this->warnaPurpleTua);
                $sheet->getStyle('BD5:BP6')->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
                $warnai('BD7:BH7', $this->warnaHijau);
                $warnai('BI7:BO7', $this->warnaAqua);
                $warnai('BP7', $this->warnaAquaMuda);

                // Baris data: garis kotak per sel (kiri/kanan tipis, atas/bawah halus)
                $garisDataPerKolom('A', 'BA');
                $garisDataPerKolom('BD', 'BP');

                // Kolom golongan PPPK diwarnai aqua, dari baris data sampai TOTAL
                foreach ($kolomGolongan as $g) {
                    [$kPria, $kWanita, $isPppk] = $g;
                    if ($isPppk) {
                        $warnai("{$kPria}{$firstDataRow}:{$kPria}{$lastDataRow}", $this->warnaAqua);
                        $warnai("{$kWanita}{$firstDataRow}:{$kWanita}{$lastDataRow}", $this->warnaAqua);
                    }
                }

                // Sub Total, TOTAL, dan Total PNS/PPPK: bold + warna
                $warnai("AA{$firstDataRow}:AA{$lastDataRow}", $this->warnaUngu);
                $warnai("AZ{$firstDataRow}:AZ{$lastDataRow}", $this->warnaUngu);
                $warnai("BA{$firstDataRow}:BA{$lastDataRow}", $this->warnaBiru);
                foreach (['AA', 'AZ', 'BA', 'BH', 'BP'] as $col) {
                    $sheet->getStyle("{$col}{$firstDataRow}:{$col}{$lastDataRow}")->getFont()->setBold(true);
                }

                // Baris TOTAL: bold, border tipis, angka berwarna oranye muda
                $garisKotak("A{$totalRow}:BA{$totalRow}");
                $garisKotak("BD{$totalRow}:BP{$totalRow}");
                $sheet->getStyle("A{$totalRow}:BA{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("BD{$totalRow}:BP{$totalRow}")->getFont()->setBold(true);
                $warnai("C{$totalRow}:BA{$totalRow}", $this->warnaOranye);
                $warnai("BD{$totalRow}:BP{$totalRow}", $this->warnaOranye);

                // Lebar kolom
                $sheet->getColumnDimension('A')->setWidth(3.44);
                $sheet->getColumnDimension('B')->setWidth(120.33);
                for ($c = 3; $c <= 26; $c++) {
                    $sheet->getColumnDimension($L($c))->setWidth(7);      // golongan Pria
                }
                for ($c = 28; $c <= 51; $c++) {
                    $sheet->getColumnDimension($L($c))->setWidth(7);      // golongan Wanita
                }
                foreach (['AA', 'AZ', 'BA'] as $col) {
                    $sheet->getColumnDimension($col)->setWidth(9);        // Sub Total & TOTAL
                }
                $sheet->getColumnDimension('BB')->setWidth(2.5);           // spacer
                $sheet->getColumnDimension('BC')->setWidth(2.5);           // spacer
                for ($c = 56; $c <= 60; $c++) {
                    $sheet->getColumnDimension($L($c))->setWidth(6.5);    // PNS I..IV
                }
                $sheet->getColumnDimension('BH')->setWidth(8);            // Total PNS
                for ($c = 61; $c <= 67; $c++) {
                    $sheet->getColumnDimension($L($c))->setWidth(8);      // PPPK detail
                }
                $sheet->getColumnDimension('BP')->setWidth(8);            // Total PPPK
            },
        ];
    }
} 