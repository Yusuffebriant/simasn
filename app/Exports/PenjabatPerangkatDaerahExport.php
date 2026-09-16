<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export tabel "Penjabat Perangkat Daerah Berdasarkan Jenis Kelamin"
 * (5.03.013). Sumber data sama persis dengan panel React
 * PenjabatPerangkatDaerahPanel.jsx, yaitu
 * StatistikService::statistikPenjabatPerangkatDaerahJenisKelamin().
 *
 * Layout sheet: satu baris per jenis penjabat. Kolom: No, Jenis
 * Penjabat, Laki-laki, Perempuan, Jumlah.
 *
 * TIDAK ada baris TOTAL — sama seperti tabel di panel React: tiap
 * kategori berbeda jenis dan saling beririsan (mis. Lurah juga terhitung
 * sebagai Pejabat ASN Struktural), jadi menjumlahkannya akan
 * menghasilkan angka yang menyesatkan.
 */
class PenjabatPerangkatDaerahExport implements FromArray, WithEvents
{
    protected array $data;
    protected array $rows;

    // HARUS sama persis (key & urutan) dengan PENJABAT_LIST di
    // resources/js/pages/Statistik/PenjabatPerangkatDaerahPanel.jsx.
    protected static array $penjabatList = [
        'kepala_daerah' => 'Kepala Daerah',
        'mantri_pamong_praja' => 'Jumlah Mantri Pamong Praja',
        'lurah' => 'Jumlah Lurah',
        'kepala_opd' => 'Jumlah Kepala OPD',
        'pejabat_asn_struktural' => 'Jumlah Pejabat ASN Struktural',
        'pejabat_asn_pelaksana' => 'Jumlah Pejabat ASN Pelaksana',
        'anggota_tim_baperjakat' => 'Jumlah Anggota Tim Badan Pertimbangan dan Kepangkatan',
    ];

    public function __construct()
    {
        $this->data = (new StatistikService())
            ->statistikPenjabatPerangkatDaerahJenisKelamin();
        $this->rows = $this->buildRows();
    }

    protected function buildRows(): array
    {
        $rows = [];
        $no = 1;

        foreach (self::$penjabatList as $key => $label) {
            $item = $this->data[$key] ?? [
                'laki_laki' => 0,
                'perempuan' => 0,
                'total' => 0,
            ];

            $rows[] = [
                $no++,
                $label,
                $item['laki_laki'],
                $item['perempuan'],
                $item['total'],
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

                $sheet->setCellValue('A1', 'REKAPITULASI PENJABAT PERANGKAT DAERAH');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT JENIS PENJABAT DAN JENIS KELAMIN');
                $sheet->mergeCells('A1:E1');
                $sheet->mergeCells('A2:E2');

                $sheet->setCellValue('A3', 'No');
                $sheet->setCellValue('B3', 'Jenis Penjabat');
                $sheet->setCellValue('C3', 'Laki-laki');
                $sheet->setCellValue('D3', 'Perempuan');
                $sheet->setCellValue('E3', 'Jumlah');

                $lastDataRow = 3 + count($this->rows);

                $sheet->getStyle('A1:A2')->getFont()->setBold(true);
                $sheet->getStyle('A3:E3')->getFont()->setBold(true);
                $sheet->getStyle('A3:E3')->getAlignment()->setHorizontal('center');
                $sheet->getStyle("A3:E{$lastDataRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("C4:E{$lastDataRow}")->getAlignment()
                    ->setHorizontal('center');

                foreach (['A' => 6, 'B' => 52, 'C' => 12, 'D' => 12, 'E' => 12] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}
