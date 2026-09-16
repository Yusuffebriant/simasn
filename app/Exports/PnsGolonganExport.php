<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export tabel "PNS Berdasarkan Golongan" (I-IV, dengan rincian per
 * pangkat/ruang I/a s.d. IV/e). Sumber data sama persis dengan panel
 * React GolonganPanel.jsx, yaitu StatistikService::statistikPnsGolongan().
 *
 * Layout sheet: satu baris "Golongan <romawi>" (subtotal, tebal) diikuti
 * baris rincian per kode golongan (mis. I/a, I/b, dst), lalu baris TOTAL
 * di paling bawah. Kolom: No, Kode, Golongan/Pangkat, Laki-laki,
 * Perempuan, Jumlah — sama seperti kolom pada GolonganPanel.jsx.
 */
class PnsGolonganExport implements FromArray, WithEvents
{
    protected array $data;
    protected array $rows;

    // Rincian pangkat/ruang per golongan, HARUS sama persis dengan
    // RINCIAN_GOLONGAN di resources/js/pages/Statistik/GolonganPanel.jsx
    // supaya urutan & label baris konsisten dengan tampilan tabel di web.
    protected static array $rincianPerGolongan = [
        'I' => [
            'I/a' => 'Golongan I/a (Juru Muda)',
            'I/b' => 'Golongan I/b (Juru Muda Tingkat I)',
            'I/c' => 'Golongan I/c (Juru)',
            'I/d' => 'Golongan I/d (Juru Tingkat I)',
        ],
        'II' => [
            'II/a' => 'Golongan II/a (Pengatur Muda)',
            'II/b' => 'Golongan II/b (Pengatur Muda Tingkat I)',
            'II/c' => 'Golongan II/c (Pengatur)',
            'II/d' => 'Golongan II/d (Pengatur Tingkat I)',
        ],
        'III' => [
            'III/a' => 'Golongan III/a (Penata Muda)',
            'III/b' => 'Golongan III/b (Penata Muda Tingkat I)',
            'III/c' => 'Golongan III/c (Penata)',
            'III/d' => 'Golongan III/d (Penata Tingkat I)',
        ],
        'IV' => [
            'IV/a' => 'Golongan IV/a (Pembina Muda)',
            'IV/b' => 'Golongan IV/b (Pembina Muda Tingkat I)',
            'IV/c' => 'Golongan IV/c (Pembina)',
            'IV/d' => 'Golongan IV/d (Pembina Tingkat I)',
            'IV/e' => 'Golongan IV/e (Pembina Utama)',
        ],
    ];

    public function __construct(protected ?string $periode = null)
    {
        $this->data = (new StatistikService())->statistikPnsGolongan($periode);
        $this->rows = $this->buildRows();
    }

    protected function buildRows(): array
    {
        $rows = [];

        foreach (self::$rincianPerGolongan as $romawi => $rincianList) {
            $golongan = $this->data["golongan_{$romawi}"];

            $rows[] = [
                '',
                "Golongan {$romawi}",
                $golongan['laki_laki']['total'],
                $golongan['perempuan']['total'],
                $golongan['total'],
            ];

            foreach ($rincianList as $kode => $label) {
                $laki = $golongan['laki_laki']['rincian'][$kode] ?? 0;
                $perempuan = $golongan['perempuan']['rincian'][$kode] ?? 0;

                $rows[] = [
                    $kode,
                    $label,
                    $laki,
                    $perempuan,
                    $laki + $perempuan,
                ];
            }
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

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH PNS');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT GOLONGAN DAN JENIS KELAMIN');
                $sheet->mergeCells('A1:E1');
                $sheet->mergeCells('A2:E2');

                $sheet->setCellValue('A3', 'Kode');
                $sheet->setCellValue('B3', 'Golongan / Pangkat');
                $sheet->setCellValue('C3', 'Laki-laki');
                $sheet->setCellValue('D3', 'Perempuan');
                $sheet->setCellValue('E3', 'Jumlah');

                $lastDataRow = 3 + count($this->rows);
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");
                $sheet->setCellValue(
                    'C' . $totalRow,
                    $this->data['golongan_I']['laki_laki']['total']
                        + $this->data['golongan_II']['laki_laki']['total']
                        + $this->data['golongan_III']['laki_laki']['total']
                        + $this->data['golongan_IV']['laki_laki']['total']
                );
                $sheet->setCellValue(
                    'D' . $totalRow,
                    $this->data['golongan_I']['perempuan']['total']
                        + $this->data['golongan_II']['perempuan']['total']
                        + $this->data['golongan_III']['perempuan']['total']
                        + $this->data['golongan_IV']['perempuan']['total']
                );
                $sheet->setCellValue('E' . $totalRow, $this->data['jumlah_pns']);

                $sheet->getStyle('A1:A2')->getFont()->setBold(true);
                $sheet->getStyle('A3:E3')->getFont()->setBold(true);
                $sheet->getStyle('A3:E3')->getAlignment()->setHorizontal('center');
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A3:E{$totalRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // Baris subtotal "Golongan <romawi>" ditebalkan juga di
                // Excel, sama seperti tampilan tabel di web (bg abu-abu).
                $rowCursor = 4;
                foreach (self::$rincianPerGolongan as $rincianList) {
                    $sheet->getStyle("A{$rowCursor}:E{$rowCursor}")->getFont()->setBold(true);
                    $rowCursor += 1 + count($rincianList);
                }

                foreach (['A' => 10, 'B' => 36, 'C' => 12, 'D' => 12, 'E' => 12] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}