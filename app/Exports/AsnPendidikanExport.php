<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export tabel "ASN Berdasarkan Tingkat Pendidikan dan Jenis Kelamin"
 * (PNS + PPPK digabung, scope satu kota). Sumber data sama persis dengan
 * panel React AsnPendidikanPanel.jsx, yaitu
 * StatistikService::statistikAsnPendidikan().
 *
 * Layout sheet: satu baris per jenjang pendidikan (SD s.d. S3), lalu
 * baris TOTAL di paling bawah. Kolom: Tingkat Pendidikan, Laki-laki,
 * Perempuan, Jumlah — sama seperti kolom pada tabel di
 * AsnPendidikanPanel.jsx.
 */
class AsnPendidikanExport implements FromArray, WithEvents
{
    protected array $data;
    protected array $rows;

    // HARUS sama persis dengan PENDIDIKAN_LIST di
    // resources/js/pages/Statistik/pendidikanList.js supaya urutan &
    // label baris konsisten dengan tampilan tabel di web.
    protected static array $pendidikanList = [
        'sd' => 'Tamat SD atau sederajat',
        'smp' => 'SMP atau sederajat',
        'sma' => 'SMA atau sederajat',
        'diploma_i' => 'Diploma I',
        'diploma_ii' => 'Diploma II',
        'diploma_iii' => 'Diploma III',
        'diploma_iv' => 'Diploma IV',
        'strata_1' => 'Strata 1',
        'strata_2' => 'Strata 2',
        'strata_3' => 'Strata 3',
    ];

    public function __construct(protected ?string $periode = null)
    {
        $this->data = (new StatistikService())->statistikAsnPendidikan($periode);
        $this->rows = $this->buildRows();
    }

    protected function buildRows(): array
    {
        $rows = [];

        foreach (self::$pendidikanList as $key => $label) {
            $jenjang = $this->data['pendidikan'][$key];

            $rows[] = [
                $label,
                $jenjang['laki_laki'],
                $jenjang['perempuan'],
                $jenjang['total'],
            ];
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

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH ASN');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT TINGKAT PENDIDIKAN DAN JENIS KELAMIN');
                $sheet->mergeCells('A1:D1');
                $sheet->mergeCells('A2:D2');

                $sheet->setCellValue('A3', 'Tingkat Pendidikan');
                $sheet->setCellValue('B3', 'Laki-laki');
                $sheet->setCellValue('C3', 'Perempuan');
                $sheet->setCellValue('D3', 'Jumlah');

                $lastDataRow = 3 + count($this->rows);
                $totalRow = $lastDataRow + 1;

                $totalLakiLaki = array_sum(array_map(
                    fn ($key) => $this->data['pendidikan'][$key]['laki_laki'],
                    array_keys(self::$pendidikanList)
                ));
                $totalPerempuan = array_sum(array_map(
                    fn ($key) => $this->data['pendidikan'][$key]['perempuan'],
                    array_keys(self::$pendidikanList)
                ));

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->setCellValue('B' . $totalRow, $totalLakiLaki);
                $sheet->setCellValue('C' . $totalRow, $totalPerempuan);
                $sheet->setCellValue('D' . $totalRow, $this->data['jumlah_asn']);

                $sheet->getStyle('A1:A2')->getFont()->setBold(true);
                $sheet->getStyle('A3:D3')->getFont()->setBold(true);
                $sheet->getStyle('A3:D3')->getAlignment()->setHorizontal('center');
                $sheet->getStyle("A{$totalRow}:D{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A3:D{$totalRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                foreach (['A' => 26, 'B' => 12, 'C' => 12, 'D' => 12] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}