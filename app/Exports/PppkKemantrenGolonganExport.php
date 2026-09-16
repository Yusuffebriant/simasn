<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export tabel "PPPK Kemantren Berdasarkan Golongan dan Jenis Kelamin"
 * (5.03.018) — PppkKemantrenGolonganPanel.jsx. Belum ada tombol export
 * sendiri di panel ini (baru dipakai lewat menu "Export Semua Statistik"
 * / StatistikAllExport), bentuknya mengikuti persis tabel yang dirender
 * KemantrenRekapTable.jsx: baris = 14 Kemantren, kolom = golongan I/a
 * s.d. IV/e (masing-masing dipecah L/P), plus kolom Jumlah L/P/Total,
 * dan baris TOTAL di paling bawah.
 *
 * Sumber data: StatistikService::statistikPppkKemantrenGolongan().
 * Struktur nested-nya BEDA dengan tabel pendidikan Kemantren — di sini
 * data dikelompokkan per Kemantren dulu, baru gender, baru golongan
 * ($data['kemantren'][nama]['laki_laki']['golongan_III_a']).
 */
class PppkKemantrenGolonganExport implements FromArray, WithEvents
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

    // HARUS sama persis (key & urutan) dengan GOLONGAN_LIST di
    // PppkKemantrenGolonganPanel.jsx. Golongan PPPK memakai kode romawi
    // POLOS tanpa huruf (I, III, V, VII, IX, X, XI — lihat
    // GolonganRuangSeeder), BEDA dengan golongan PNS yang formatnya
    // 'III/a'. Key JSON dari statistikPppkKemantrenGolongan() memang
    // 'golongan_I', 'golongan_III', dst — bukan 'golongan_I_a' seperti
    // punya PNS.
    protected static array $golonganList = [
        'golongan_I' => 'I',
        'golongan_III' => 'III',
        'golongan_V' => 'V',
        'golongan_VII' => 'VII',
        'golongan_IX' => 'IX',
        'golongan_X' => 'X',
        'golongan_XI' => 'XI',
    ];

    public function __construct(protected ?string $periode = null)
    {
        $this->data = (new StatistikService())
            ->statistikPppkKemantrenGolongan($periode);
        $this->rows = $this->buildRows();
    }

    protected function toTitleCase(string $text): string
    {
        return ucwords(strtolower($text));
    }

    protected function nilai(string $kemantren, string $gender, string $golonganKey): int
    {
        return (int) ($this->data['kemantren'][$kemantren][$gender][$golonganKey] ?? 0);
    }

    protected function buildRows(): array
    {
        $rows = [];
        $no = 1;

        foreach (self::$kemantrenList as $kemantren) {
            $baris = [$no++, $this->toTitleCase($kemantren)];
            $jumlahLaki = (int) ($this->data['kemantren'][$kemantren]['laki_laki']['total'] ?? 0);
            $jumlahPerempuan = (int) ($this->data['kemantren'][$kemantren]['perempuan']['total'] ?? 0);

            foreach (array_keys(self::$golonganList) as $key) {
                $baris[] = $this->nilai($kemantren, 'laki_laki', $key);
                $baris[] = $this->nilai($kemantren, 'perempuan', $key);
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

                $jumlahKategori = count(self::$golonganList);
                $kolomTerakhirIdx = 2 + ($jumlahKategori * 2) + 3;
                $kolomTerakhir = Coordinate::stringFromColumnIndex($kolomTerakhirIdx);

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH PPPK KEMANTREN');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT GOLONGAN RUANG DAN JENIS KELAMIN');
                $sheet->mergeCells("A1:{$kolomTerakhir}1");
                $sheet->mergeCells("A2:{$kolomTerakhir}2");

                $sheet->mergeCells('A3:A4');
                $sheet->mergeCells('B3:B4');
                $sheet->setCellValue('A3', 'No');
                $sheet->setCellValue('B3', 'Kemantren');

                $kolom = 3;
                foreach (self::$golonganList as $label) {
                    $c1 = Coordinate::stringFromColumnIndex($kolom);
                    $c2 = Coordinate::stringFromColumnIndex($kolom + 1);
                    $sheet->mergeCells("{$c1}3:{$c2}3");
                    $sheet->setCellValue("{$c1}3", $label);
                    $sheet->setCellValue("{$c1}4", 'L');
                    $sheet->setCellValue("{$c2}4", 'P');
                    $kolom += 2;
                }

                $cJumlahL = Coordinate::stringFromColumnIndex($kolom);
                $cJumlahP = Coordinate::stringFromColumnIndex($kolom + 1);
                $cJumlahT = Coordinate::stringFromColumnIndex($kolom + 2);
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
                foreach (array_keys(self::$golonganList) as $key) {
                    $totalL = array_sum(array_map(
                        fn($k) => $this->nilai($k, 'laki_laki', $key),
                        self::$kemantrenList
                    ));
                    $totalP = array_sum(array_map(
                        fn($k) => $this->nilai($k, 'perempuan', $key),
                        self::$kemantrenList
                    ));
                    $c1 = Coordinate::stringFromColumnIndex($kolom);
                    $c2 = Coordinate::stringFromColumnIndex($kolom + 1);
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
                    $sheet->getColumnDimensionByColumn($i)->setWidth(7);
                }
            },
        ];
    }
}
