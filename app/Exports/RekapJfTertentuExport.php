<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RekapJfTertentuExport implements FromArray, WithEvents
{
    protected array $data;

    // Urutan tampil golongan Pria/Wanita, PNS & PPPK diselang-seling —
    // harus sama persis dgn key $d['pria'] / $d['wanita'] dari
    // RekapService::rekapJfTertentu().
    protected array $displayOrder = [
        'II/a', 'V', 'II/b', 'II/c', 'VII', 'II/d',
        'III/a', 'IX', 'III/b', 'X', 'III/c', 'XI', 'III/d',
        'IV/a', 'IV/b', 'IV/c', 'IV/d', 'IV/e',
    ];

    protected array $pnsAggList = ['I', 'II', 'III', 'IV'];
    protected array $pppkAggList = ['V', 'VII', 'IX', 'X', 'XI'];

    protected int $headerRows = 7;

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapJfTertentu($periode);
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
                [$d['instansi']],
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

                // Palet warna (mengikuti sheet "jf tertentu" di file data pegawai)
                $pink = 'E6B8B7';       // kolom JML pria & JML total
                $pinkMuda = 'F2DCDB';   // kolom JML wanita
                $biruTua = 'B7DEE8';    // kolom JML / Total
                $biruMuda = 'DAEEF3';   // JML wanita & sub-header PNS/PPPK
                $navy = '002060';       // header PNS
                $ungu = '7030A0';       // header PPPK
                $putih = 'FFFFFF';

                $n = count($this->displayOrder);

                $colNo = 1;
                $colInstansi = 2;
                $colPriaStart = 3;
                $colPriaEnd = $colPriaStart + $n - 1;
                $colJmlPria = $colPriaEnd + 1;
                $colWanitaStart = $colJmlPria + 1;
                $colWanitaEnd = $colWanitaStart + $n - 1;
                $colJmlWanita = $colWanitaEnd + 1;
                $colJmlTotal = $colJmlWanita + 1;
                $colSpacer = $colJmlTotal + 1;
                $colPnsStart = $colSpacer + 1;
                $colPnsEnd = $colPnsStart + count($this->pnsAggList);
                $colPppkStart = $colPnsEnd + 1;
                $colPppkEnd = $colPppkStart + count($this->pppkAggList);

                $lastCol = $colPppkEnd;

                $sheet->insertNewRowBefore(1, $this->headerRows);

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH ASN FUNGSIONAL TERTENTU PEMERINTAH DAERAH/KABUPATEN/KOTA PEMERINTAH KOTA YOGYAKARTA');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT INSTANSI, GOLONGAN RUANG DAN JENIS KELAMIN');
                $sheet->setCellValue('A3', 'KEADAAN : ' . $this->formatPeriode($this->periode));
                // Judul di-merge A s/d JML TOTAL supaya tepat di tengah tabel utama
                foreach ([1, 2, 3] as $r) {
                    $sheet->mergeCells("A{$r}:" . $L($colJmlTotal) . $r);
                }

                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'INSTANSI');
                $sheet->setCellValue($L($colPriaStart) . '5', 'PRIA');
                $sheet->setCellValue($L($colJmlPria) . '5', 'JML');
                $sheet->setCellValue($L($colWanitaStart) . '5', 'WANITA');
                $sheet->setCellValue($L($colJmlWanita) . '5', 'JML');
                $sheet->setCellValue($L($colJmlTotal) . '5', 'JML TOTAL');
                $sheet->setCellValue($L($colPnsStart) . '5', 'PNS');
                $sheet->setCellValue($L($colPppkStart) . '5', 'PPPK');

                $sheet->mergeCells('A5:A7');
                $sheet->mergeCells('B5:B7');
                $sheet->mergeCells($L($colPriaStart) . '5:' . $L($colPriaEnd) . '6');
                $sheet->mergeCells($L($colJmlPria) . '5:' . $L($colJmlPria) . '7');
                $sheet->mergeCells($L($colWanitaStart) . '5:' . $L($colWanitaEnd) . '6');
                $sheet->mergeCells($L($colJmlWanita) . '5:' . $L($colJmlWanita) . '7');
                $sheet->mergeCells($L($colJmlTotal) . '5:' . $L($colJmlTotal) . '7');
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

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                $kolomAngka = array_merge(
                    range($colPriaStart, $colJmlTotal),
                    range($colPnsStart, $colPppkEnd)
                );
                foreach ($kolomAngka as $colIdx) {
                    $col = $L($colIdx);
                    $sum = 0;
                    for ($r = $this->headerRows + 1; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }
                $sheet->getStyle("A{$totalRow}:" . $L($lastCol) . "{$totalRow}")->getFont()->setBold(true);

                $fill = function (string $range, string $rgb) use ($sheet) {
                    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
                };
                $lastL = $L($lastCol);
                $lastMain = $L($colJmlTotal);

                // Header spacer -> kolom nama instansi untuk tabel PNS/PPPK
                $sheet->setCellValue($L($colSpacer) . '5', 'INSTANSI');
                $sheet->mergeCells($L($colSpacer) . '5:' . $L($colSpacer) . '7');
                $sheet->setCellValue($L($colSpacer) . $totalRow, 'TOTAL');

                // Judul rata tengah
                $sheet->getStyle("A1:{$lastMain}3")->getFont()->setBold(true)->getColor()->setRGB('000000');
                $sheet->getStyle("A1:{$lastMain}3")->getAlignment()->setHorizontal('center')->setVertical('center');
                $sheet->getRowDimension(1)->setRowHeight(20);
                $sheet->getStyle('A1')->getFont()->setSize(13);

                // Semua sel tabel: border, rata tengah angka
                $sheet->getStyle("A5:{$lastL}{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("C8:{$lastL}{$totalRow}")->getAlignment()->setHorizontal('center')->setVertical('center');
                $sheet->getStyle("A8:B{$totalRow}")->getAlignment()->setVertical('center');
                $sheet->getStyle("A8:A{$lastDataRow}")->getAlignment()->setHorizontal('center');
                $sheet->getStyle($L($colSpacer) . "8:" . $L($colSpacer) . $totalRow)->getAlignment()->setHorizontal('left');

                // Header
                $sheet->getStyle("A5:{$lastMain}7")->getFont()->setBold(true);
                $sheet->getStyle("A5:{$lastL}7")->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
                $sheet->getRowDimension(7)->setRowHeight(29.4);

                // Header (atas) biru
                $fill("A5:{$lastMain}7", $biruTua);

                // Kolom JML pria / JML wanita / JML TOTAL (pink, hanya isi data)
                $fill($L($colJmlPria) . "8:" . $L($colJmlPria) . $totalRow, $pink);
                $fill($L($colJmlWanita) . "8:" . $L($colJmlWanita) . $totalRow, $pinkMuda);
                $fill($L($colJmlTotal) . "8:" . $L($colJmlTotal) . $totalRow, $pink);

                // Baris TOTAL (bawah) pink
                $fill("A{$totalRow}:{$lastMain}{$totalRow}", $pink);
                $sheet->getStyle($L($colJmlTotal) . "8:" . $L($colJmlTotal) . $totalRow)->getFont()->setBold(true);

                // Tabel PNS (header navy) & PPPK (header ungu), teks putih
                $fill($L($colPnsStart) . '5:' . $L($colPnsEnd) . '6', $navy);
                $fill($L($colPppkStart) . '5:' . $L($colPppkEnd) . '6', $ungu);
                $sheet->getStyle($L($colPnsStart) . '5:' . $L($colPppkEnd) . '6')->getFont()->getColor()->setRGB($putih);
                $fill($L($colPnsStart) . '7:' . $L($colPppkEnd) . '7', $biruMuda);
                foreach ([$colPnsEnd, $colPppkEnd] as $ci) {
                    $fill($L($ci) . "8:" . $L($ci) . $totalRow, $biruTua);
                    $sheet->getStyle($L($ci) . "8:" . $L($ci) . $totalRow)->getFont()->setBold(true);
                }
                $fill($L($colPnsStart) . "{$totalRow}:" . $L($colPnsEnd) . $totalRow, $biruMuda);
                $fill($L($colPppkStart) . "{$totalRow}:" . $L($colPppkEnd) . $totalRow, $biruMuda);
                $fill($L($colPnsEnd) . $totalRow, $biruTua);
                $fill($L($colPppkEnd) . $totalRow, $biruTua);

                // Baris TOTAL: tebal
                $sheet->getStyle("A{$totalRow}:{$lastL}{$totalRow}")->getFont()->setBold(true);

                // Lebar kolom
                $sheet->getColumnDimension('A')->setWidth(5);
                $sheet->getColumnDimension('B')->setWidth(62);
                foreach (array_merge(range($colPriaStart, $colJmlTotal), range($colPnsStart, $colPppkEnd)) as $ci) {
                    $sheet->getColumnDimension($L($ci))->setWidth(7);
                }
                $sheet->getColumnDimension($L($colJmlTotal))->setWidth(11);
                $sheet->getColumnDimension($L($colSpacer))->setWidth(62);

                // Bekukan hanya baris header (judul di tengah tidak terpotong kolom beku)
                $sheet->freezePane('A' . ($this->headerRows + 1));
            },
        ];
    }
}