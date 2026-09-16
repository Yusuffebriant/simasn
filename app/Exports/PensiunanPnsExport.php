<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export tabel "Pensiunan PNS" (Golongan I-IV per jenis kelamin).
 * Sumber data sama persis dengan panel React PensiunanPanel.jsx, yaitu
 * StatistikService::statistikPensiunanPNS().
 *
 * Beda dengan export statistik lain: filter di sini pakai $tahun (int),
 * bukan $periode (string), mengikuti signature statistikPensiunanPNS()
 * yang memfilter pegawai.tanggal_pensiun dalam rentang 1 tahun.
 *
 * Pensiunan dengan golongan kosong/tidak dikenali tetap masuk
 * 'jumlah_pensiunan_pns' tapi tidak masuk bucket Golongan I-IV, jadi
 * selisihnya ditulis sebagai baris "Golongan Tidak Dikenali" supaya
 * angka per baris konsisten dengan baris TOTAL — pola sama seperti
 * baris 'Tidak Dikenali' di PnsKelurahanExport.php.
 */
class PensiunanPnsExport implements FromArray, WithEvents
{
    protected const GOLONGAN_LIST = [
        'golongan_I' => 'Golongan I',
        'golongan_II' => 'Golongan II',
        'golongan_III' => 'Golongan III',
        'golongan_IV' => 'Golongan IV',
    ];

    protected array $data;
    protected array $rows;

    public function __construct(protected ?int $tahun = null)
    {
        $this->data = (new StatistikService())->statistikPensiunanPNS($tahun);
        $this->rows = $this->buildRows();
    }

    protected function buildRows(): array
    {
        $rows = [];
        $no = 1;
        $totalGolongan = 0;

        foreach (self::GOLONGAN_LIST as $key => $label) {
            $detail = $this->data[$key] ?? [];
            $total = $detail['total'] ?? 0;
            $totalGolongan += $total;

            $rows[] = [
                $no++,
                $label,
                $detail['laki_laki'] ?? 0,
                $detail['perempuan'] ?? 0,
                $total,
            ];
        }

        $tidakDikenali = max(($this->data['jumlah_pensiunan_pns'] ?? 0) - $totalGolongan, 0);

        if ($tidakDikenali > 0) {
            $rows[] = ['-', 'Golongan Tidak Dikenali', '', '', $tidakDikenali];
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

                $tahun = $this->data['tahun'] ?? '';

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH PENSIUNAN PNS' . ($tahun ? " TAHUN {$tahun}" : ''));
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT GOLONGAN DAN JENIS KELAMIN');
                $sheet->mergeCells('A1:E1');
                $sheet->mergeCells('A2:E2');

                $sheet->setCellValue('A3', 'No');
                $sheet->setCellValue('B3', 'Golongan');
                $sheet->setCellValue('C3', 'Laki-laki');
                $sheet->setCellValue('D3', 'Perempuan');
                $sheet->setCellValue('E3', 'Jumlah');

                $lastDataRow = 3 + count($this->rows);
                $totalRow = $lastDataRow + 1;

                $totalLaki = 0;
                $totalPerempuan = 0;

                foreach (self::GOLONGAN_LIST as $key => $label) {
                    $totalLaki += $this->data[$key]['laki_laki'] ?? 0;
                    $totalPerempuan += $this->data[$key]['perempuan'] ?? 0;
                }

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");
                $sheet->setCellValue('C' . $totalRow, $totalLaki);
                $sheet->setCellValue('D' . $totalRow, $totalPerempuan);
                $sheet->setCellValue('E' . $totalRow, $this->data['jumlah_pensiunan_pns'] ?? 0);

                $sheet->getStyle('A1:A2')->getFont()->setBold(true);
                $sheet->getStyle('A3:E3')->getFont()->setBold(true);
                $sheet->getStyle('A3:E3')->getAlignment()->setHorizontal('center');
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A3:E{$totalRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                foreach (['A' => 6, 'B' => 26, 'C' => 12, 'D' => 12, 'E' => 14] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}