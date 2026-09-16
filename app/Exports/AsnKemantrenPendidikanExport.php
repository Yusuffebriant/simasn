<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export tabel silang "ASN Kemantren Berdasarkan Tingkat Pendidikan"
 * (5.03.014). Sumber data sama persis dengan panel React
 * AsnKemantrenPendidikanPanel.jsx, yaitu
 * StatistikService::statistikAsnKemantrenPendidikan().
 *
 * Layout sheet mengikuti tabel di panel: baris = 14 Kemantren, kolom =
 * 10 jenjang pendidikan (SD s.d. S3), plus kolom Total per baris dan
 * baris TOTAL per kolom di paling bawah. Data di scope ini TIDAK dipecah
 * per jenis kelamin.
 *
 * Catatan: grand total di pojok kanan bawah memakai
 * 'jumlah_asn_kemantren' dari service — angka itu SUDAH termasuk
 * 'tidak_dikenali' (ASN Kemantren yang jenjang pendidikannya tidak
 * terpetakan), jadi bisa lebih besar dari jumlah seluruh sel tabel.
 * Selisihnya ditulis eksplisit sebagai baris "Tidak Dikenali" supaya
 * angkanya bisa direkonsiliasi, sama seperti pola di PnsKelurahanExport.
 */
class AsnKemantrenPendidikanExport implements FromArray, WithEvents
{
    protected array $data;
    protected array $rows;

    // HARUS sama persis (nama & urutan) dengan KEMANTREN_LIST di
    // resources/js/pages/Statistik/AsnKemantrenPendidikanPanel.jsx.
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

    // Label kolom memakai chartLabel dari PENDIDIKAN_LIST
    // (resources/js/pages/Statistik/pendidikanList.js) — sama seperti
    // header tabel di panel, supaya kolomnya tidak kelewat lebar.
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
            ->statistikAsnKemantrenPendidikan($periode);
        $this->rows = $this->buildRows();
    }

    protected function toTitleCase(string $text): string
    {
        return ucwords(strtolower($text));
    }

    protected function nilai(string $pendidikanKey, string $kemantren): int
    {
        return (int) ($this->data['pendidikan'][$pendidikanKey]['kemantren'][$kemantren] ?? 0);
    }

    protected function buildRows(): array
    {
        $rows = [];
        $no = 1;

        foreach (self::$kemantrenList as $kemantren) {
            $baris = [$no++, 'Kemantren ' . $this->toTitleCase($kemantren)];
            $totalBaris = 0;

            foreach (array_keys(self::$pendidikanList) as $pendidikanKey) {
                $nilai = $this->nilai($pendidikanKey, $kemantren);
                $baris[] = $nilai;
                $totalBaris += $nilai;
            }

            $baris[] = $totalBaris;
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
                $sheet->insertNewRowBefore(1, 3);

                // Kolom: A = No, B = Kemantren, C..L = 10 jenjang
                // pendidikan, M = Total.
                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH ASN KEMANTREN');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT KEMANTREN DAN TINGKAT PENDIDIKAN');
                $sheet->mergeCells('A1:M1');
                $sheet->mergeCells('A2:M2');

                $sheet->setCellValue('A3', 'No');
                $sheet->setCellValue('B3', 'Kemantren');

                $kolom = 'C';
                foreach (self::$pendidikanList as $label) {
                    $sheet->setCellValue($kolom . '3', $label);
                    $kolom++;
                }
                $sheet->setCellValue('M3', 'Total');

                $lastDataRow = 3 + count($this->rows);
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                $kolom = 'C';
                $grandTotalTabel = 0;
                foreach (array_keys(self::$pendidikanList) as $pendidikanKey) {
                    $totalKolom = array_sum(array_map(
                        fn($kemantren) => $this->nilai($pendidikanKey, $kemantren),
                        self::$kemantrenList
                    ));
                    $sheet->setCellValue($kolom . $totalRow, $totalKolom);
                    $grandTotalTabel += $totalKolom;
                    $kolom++;
                }
                $sheet->setCellValue('M' . $totalRow, $grandTotalTabel);

                $lastRow = $totalRow;

                // Baris rekonsiliasi: ASN Kemantren yang jenjang
                // pendidikannya tidak terpetakan (ikut terhitung di
                // 'jumlah_asn_kemantren' tapi tidak muncul di sel mana pun
                // di tabel atas).
                $tidakDikenali = (int) ($this->data['tidak_dikenali'] ?? 0);

                if ($tidakDikenali > 0) {
                    $barisTidakDikenali = $totalRow + 1;
                    $barisGrandTotal = $totalRow + 2;

                    $sheet->setCellValue('A' . $barisTidakDikenali, 'Tidak Dikenali');
                    $sheet->mergeCells("A{$barisTidakDikenali}:L{$barisTidakDikenali}");
                    $sheet->setCellValue('M' . $barisTidakDikenali, $tidakDikenali);

                    $sheet->setCellValue('A' . $barisGrandTotal, 'JUMLAH ASN KEMANTREN');
                    $sheet->mergeCells("A{$barisGrandTotal}:L{$barisGrandTotal}");
                    $sheet->setCellValue(
                        'M' . $barisGrandTotal,
                        $this->data['jumlah_asn_kemantren'] ?? 0
                    );

                    $sheet->getStyle("A{$barisGrandTotal}:M{$barisGrandTotal}")
                        ->getFont()->setBold(true);

                    $lastRow = $barisGrandTotal;
                }

                $sheet->getStyle('A1:A2')->getFont()->setBold(true);
                $sheet->getStyle('A3:M3')->getFont()->setBold(true);
                $sheet->getStyle('A3:M3')->getAlignment()->setHorizontal('center');
                $sheet->getStyle("A{$totalRow}:M{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("C4:M{$lastRow}")->getAlignment()->setHorizontal('center');
                $sheet->getStyle("A3:M{$lastRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(28);
                foreach (range('C', 'L') as $col) {
                    $sheet->getColumnDimension($col)->setWidth(8);
                }
                $sheet->getColumnDimension('M')->setWidth(10);
            },
        ];
    }
}
