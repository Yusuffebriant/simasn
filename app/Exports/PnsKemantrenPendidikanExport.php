<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export tabel "PNS Kemantren Berdasarkan Tingkat Pendidikan dan Jenis
 * Kelamin" (5.03.015) — PnsKemantrenPendidikanPanel.jsx. Belum ada
 * tombol export sendiri di panel ini (baru dipakai lewat menu
 * "Export Semua Statistik" / StatistikAllExport), makanya bentuk file
 * ini dibuat mengikuti persis tabel yang dirender KemantrenRekapTable.jsx
 * di layar: baris = 14 Kemantren, kolom = 10 jenjang pendidikan
 * (masing-masing dipecah L/P), plus kolom Jumlah L/P/Total, dan baris
 * TOTAL di paling bawah.
 *
 * Sumber data: StatistikService::statistikPnsKemantrenPendidikan().
 * Struktur nested-nya BEDA dengan AsnKemantrenPendidikanExport — di sini
 * tiap jenjang pendidikan sudah dipecah per gender dulu baru per
 * Kemantren ($data['pendidikan'][key]['laki_laki']['kemantren'][nama]).
 */
class PnsKemantrenPendidikanExport implements FromArray, WithEvents
{
    protected array $data;
    protected array $rows;

    protected static array $kemantrenList = [
        'TEGALREJO',
        'JETIS',
        'GONDOKUSUMAN',
        'DANUREJAN',
        'GEDONGTENGEN',
        'NGAMPILAN',
        'WIROBRAJAN',
        'MANTRIJERON',
        'KRATON',
        'GONDOMANAN',
        'PAKUALAMAN',
        'MERGANGSAN',
        'UMBULHARJO',
        'KOTAGEDE',
    ];

    // Label kolom pakai chartLabel dari PENDIDIKAN_LIST (pendidikanList.js),
    // sama seperti header tabel di panel React.
    protected static array $pendidikanList = [
        'sd' => 'SD',
        'smp' => 'SMP',
        'sma' => 'SMA',
        'diploma_i' => 'D-I',
        'diploma_ii' => 'D-II',
        'diploma_iii' => 'D-III',
        'diploma_iv' => 'D-IV',
        'strata_1' => 'S1',
        'strata_2' => 'S2',
        'strata_3' => 'S3',
    ];

    public function __construct(protected ?string $periode = null)
    {
        $this->data = (new StatistikService())
            ->statistikPnsKemantrenPendidikan($periode);
        $this->rows = $this->buildRows();
    }

    protected function toTitleCase(string $text): string
    {
        return ucwords(strtolower($text));
    }

    protected function nilai(string $pendidikanKey, string $gender, string $kemantren): int
    {
        return (int) ($this->data['pendidikan'][$pendidikanKey][$gender]['kemantren'][$kemantren] ?? 0);
    }

    protected function buildRows(): array
    {
        $rows = [];
        $no = 1;

        foreach (self::$kemantrenList as $kemantren) {
            $baris = [$no++, $this->toTitleCase($kemantren)];
            $jumlahLaki = 0;
            $jumlahPerempuan = 0;

            foreach (array_keys(self::$pendidikanList) as $key) {
                $l = $this->nilai($key, 'laki_laki', $kemantren);
                $p = $this->nilai($key, 'perempuan', $kemantren);
                $baris[] = $l;
                $baris[] = $p;
                $jumlahLaki += $l;
                $jumlahPerempuan += $p;
            }

            $baris[] = $jumlahLaki;
            $baris[] = $jumlahPerempuan;
            $baris[] = $jumlahLaki + $jumlahPerempuan;

            $rows[] = $baris;
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
                $sheet->insertNewRowBefore(1, 4);

                $jumlahKategori = count(self::$pendidikanList);
                $kolomTerakhirIdx = 2 + ($jumlahKategori * 2) + 3; // No + Kemantren + (L/P per kategori) + L/P/Total
                $kolomTerakhir = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($kolomTerakhirIdx);

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH PNS KEMANTREN');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT TINGKAT PENDIDIKAN DAN JENIS KELAMIN');
                $sheet->mergeCells("A1:{$kolomTerakhir}1");
                $sheet->mergeCells("A2:{$kolomTerakhir}2");

                $sheet->mergeCells('A3:A4');
                $sheet->mergeCells('B3:B4');
                $sheet->setCellValue('A3', 'No');
                $sheet->setCellValue('B3', 'Kemantren');

                $kolom = 3;
                foreach (self::$pendidikanList as $label) {
                    $c1 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($kolom);
                    $c2 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($kolom + 1);
                    $sheet->mergeCells("{$c1}3:{$c2}3");
                    $sheet->setCellValue("{$c1}3", $label);
                    $sheet->setCellValue("{$c1}4", 'L');
                    $sheet->setCellValue("{$c2}4", 'P');
                    $kolom += 2;
                }

                $cJumlahL = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($kolom);
                $cJumlahP = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($kolom + 1);
                $cJumlahT = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($kolom + 2);
                $sheet->mergeCells("{$cJumlahL}3:{$cJumlahT}3");
                $sheet->setCellValue("{$cJumlahL}3", 'Jumlah');
                $sheet->setCellValue("{$cJumlahL}4", 'L');
                $sheet->setCellValue("{$cJumlahP}4", 'P');
                $sheet->setCellValue("{$cJumlahT}4", 'Total');

                $lastDataRow = 4 + count($this->rows);
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                $kolom = 3;
                $totalJumlahL = 0;
                $totalJumlahP = 0;
                foreach (array_keys(self::$pendidikanList) as $key) {
                    $totalL = array_sum(array_map(
                        fn($k) => $this->nilai($key, 'laki_laki', $k),
                        self::$kemantrenList
                    ));
                    $totalP = array_sum(array_map(
                        fn($k) => $this->nilai($key, 'perempuan', $k),
                        self::$kemantrenList
                    ));
                    $c1 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($kolom);
                    $c2 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($kolom + 1);
                    $sheet->setCellValue($c1 . $totalRow, $totalL);
                    $sheet->setCellValue($c2 . $totalRow, $totalP);
                    $totalJumlahL += $totalL;
                    $totalJumlahP += $totalP;
                    $kolom += 2;
                }
                $sheet->setCellValue($cJumlahL . $totalRow, $totalJumlahL);
                $sheet->setCellValue($cJumlahP . $totalRow, $totalJumlahP);
                $sheet->setCellValue($cJumlahT . $totalRow, $totalJumlahL + $totalJumlahP);

                $sheet->getStyle('A1:A2')->getFont()->setBold(true);
                $sheet->getStyle("A3:{$kolomTerakhir}4")->getFont()->setBold(true);
                $sheet->getStyle("A3:{$kolomTerakhir}4")->getAlignment()->setHorizontal('center');
                $sheet->getStyle("A{$totalRow}:{$kolomTerakhir}{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("C5:{$kolomTerakhir}{$totalRow}")->getAlignment()->setHorizontal('center');
                $sheet->getStyle("A3:{$kolomTerakhir}{$totalRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(20);
                for ($i = 3; $i <= $kolomTerakhirIdx; $i++) {
                    $sheet->getColumnDimensionByColumn($i)->setWidth(8);
                }
            },
        ];
    }
}
