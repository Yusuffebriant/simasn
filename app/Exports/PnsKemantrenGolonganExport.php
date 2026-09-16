<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export tabel "PNS Kemantren Berdasarkan Golongan dan Jenis Kelamin"
 * (5.03.017) — PnsKemantrenGolonganPanel.jsx. Belum ada tombol export
 * sendiri di panel ini (baru dipakai lewat menu "Export Semua Statistik"
 * / StatistikAllExport), bentuknya mengikuti persis tabel yang dirender
 * KemantrenRekapTable.jsx: baris = 14 Kemantren, kolom = golongan I/a
 * s.d. IV/e (masing-masing dipecah L/P), plus kolom Jumlah L/P/Total,
 * dan baris TOTAL di paling bawah.
 *
 * Sumber data: StatistikService::statistikPnsKemantrenGolongan().
 * Struktur nested-nya BEDA dengan tabel pendidikan Kemantren — di sini
 * data dikelompokkan per Kemantren dulu, baru gender, baru golongan
 * ($data['kemantren'][nama]['laki_laki']['golongan_III_a']).
 */
class PnsKemantrenGolonganExport implements FromArray, WithEvents
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
    // PnsKemantrenGolonganPanel.jsx.
    protected static array $golonganList = [
        'golongan_I_a' => 'I/a',
        'golongan_I_b' => 'I/b',
        'golongan_I_c' => 'I/c',
        'golongan_I_d' => 'I/d',
        'golongan_II_a' => 'II/a',
        'golongan_II_b' => 'II/b',
        'golongan_II_c' => 'II/c',
        'golongan_II_d' => 'II/d',
        'golongan_III_a' => 'III/a',
        'golongan_III_b' => 'III/b',
        'golongan_III_c' => 'III/c',
        'golongan_III_d' => 'III/d',
        'golongan_IV_a' => 'IV/a',
        'golongan_IV_b' => 'IV/b',
        'golongan_IV_c' => 'IV/c',
        'golongan_IV_d' => 'IV/d',
        'golongan_IV_e' => 'IV/e',
    ];

    public function __construct(protected ?string $periode = null)
    {
        $this->data = (new StatistikService())
            ->statistikPnsKemantrenGolongan($periode);
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

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH PNS KEMANTREN');
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
