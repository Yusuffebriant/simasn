<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export tabel "PNS Kelurahan" (5.03.019), dikelompokkan berdasarkan
 * Kemantren induknya. Sumber data sama persis dengan panel React
 * PnsKelurahanPanel.jsx, yaitu StatistikService::statistikPnsKelurahan().
 *
 * Layout sheet: satu baris per Kelurahan, dikelompokkan per Kemantren,
 * dengan baris "Jumlah <Kemantren>" di akhir tiap kelompok dan baris
 * "TOTAL" di paling bawah. Kolom: No, Kemantren, Kelurahan, Laki-laki,
 * Perempuan, Jumlah PNS — rincian gender diambil dari
 * $detail['kelurahan_gender'] (ditambahkan di statistikPnsKelurahan()
 * khusus untuk kebutuhan export ini; tidak mengubah struktur
 * $detail['kelurahan'] yang sudah dipakai panel React, supaya tidak
 * breaking).
 */
class PnsKelurahanExport implements FromArray, WithEvents, WithStrictNullComparison
{
    protected array $data;
    protected array $rows;

    /** Indeks (0-based, di $this->rows) baris subtotal "Jumlah Kemantren ...". */
    protected array $subtotalIndexes = [];

    /** Indeks (0-based, di $this->rows) baris "Tidak Dikenali", null kalau tidak ada. */
    protected ?int $tidakDikenaliIndex = null;

    protected int $grandLaki = 0;
    protected int $grandPerempuan = 0;

    public function __construct(protected ?string $periode = null)
    {
        $this->data = (new StatistikService())->statistikPnsKelurahan($periode);
        $this->rows = $this->buildRows();
    }

    protected function buildRows(): array
    {
        $rows = [];
        $no = 1;

        foreach ($this->data['kemantren'] as $namaKemantren => $detail) {
            $genderData = $detail['kelurahan_gender'] ?? [];
            $totalLakiKemantren = 0;
            $totalPerempuanKemantren = 0;

            foreach ($detail['kelurahan'] as $namaKelurahan => $jumlah) {
                $laki = $genderData[$namaKelurahan]['laki_laki'] ?? 0;
                $perempuan = $genderData[$namaKelurahan]['perempuan'] ?? 0;

                $totalLakiKemantren += $laki;
                $totalPerempuanKemantren += $perempuan;
                $this->grandLaki += $laki;
                $this->grandPerempuan += $perempuan;

                $rows[] = [
                    $no++,
                    $this->toTitleCase($namaKemantren),
                    $this->toTitleCase($namaKelurahan),
                    $laki,
                    $perempuan,
                    $jumlah,
                ];
            }

            $this->subtotalIndexes[] = count($rows);
            $rows[] = [
                '',
                'Jumlah Kemantren ' . $this->toTitleCase($namaKemantren),
                '',
                $totalLakiKemantren,
                $totalPerempuanKemantren,
                $detail['total'],
            ];
        }

        if (($this->data['tidak_dikenali'] ?? 0) > 0) {
            $this->tidakDikenaliIndex = count($rows);
            $rows[] = ['', 'Tidak Dikenali', '', '', '', $this->data['tidak_dikenali']];
        }

        return $rows;
    }

    public function array(): array
    {
        return $this->rows;
    }

    protected function toTitleCase(string $text): string
    {
        return ucwords(strtolower($text));
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Sisipkan 3 baris di atas data: judul, subjudul, header kolom.
                // (Sebelumnya hanya 2 baris, sehingga header menimpa baris data
                // pertama dan tabel jadi tidak sama dengan tampilan statistik.)
                $sheet->insertNewRowBefore(1, 3);

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH PNS KELURAHAN');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT KEMANTREN, KELURAHAN, DAN JENIS KELAMIN');
                $sheet->mergeCells('A1:F1');
                $sheet->mergeCells('A2:F2');

                // Header kolom sama dengan tabel di halaman Statistik.
                $sheet->setCellValue('A3', 'No');
                $sheet->setCellValue('B3', 'Kemantren');
                $sheet->setCellValue('C3', 'Kelurahan');
                $sheet->setCellValue('D3', 'L');
                $sheet->setCellValue('E3', 'P');
                $sheet->setCellValue('F3', 'Jumlah PNS');

                $firstDataRow = 4;
                $lastDataRow = 3 + count($this->rows);
                $totalRow = $lastDataRow + 1;

                // Baris TOTAL: L, P, dan Jumlah, sama seperti <tfoot> di halaman Statistik.
                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:C{$totalRow}");
                $sheet->setCellValue('D' . $totalRow, $this->grandLaki);
                $sheet->setCellValue('E' . $totalRow, $this->grandPerempuan);
                $sheet->setCellValue('F' . $totalRow, $this->data['jumlah_pns_kelurahan']);

                // Gaya umum.
                $sheet->getStyle('A1:A2')->getFont()->setBold(true);
                $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A3:F3')->getFont()->setBold(true);
                $sheet->getStyle('A3:F3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A3:F3')->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F9FAFB');
                $sheet->getStyle("A3:F{$totalRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                // No & angka rata tengah seperti di halaman Statistik.
                $sheet->getStyle("A{$firstDataRow}:A{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("D{$firstDataRow}:F{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F{$firstDataRow}:F{$totalRow}")->getFont()->setBold(true);

                // Baris subtotal "Jumlah Kemantren ...": tebal, abu-abu, label digabung B:C.
                foreach ($this->subtotalIndexes as $index) {
                    $row = $firstDataRow + $index;
                    $sheet->mergeCells("B{$row}:C{$row}");
                    $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:F{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F3F4F6');
                }

                // Baris "Tidak Dikenali" (hanya muncul jika ada): kuning muda, label digabung B:E.
                if ($this->tidakDikenaliIndex !== null) {
                    $row = $firstDataRow + $this->tidakDikenaliIndex;
                    $sheet->mergeCells("B{$row}:E{$row}");
                    $sheet->getStyle("A{$row}:F{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFF8E1');
                }

                // Baris TOTAL: tebal & abu-abu.
                $sheet->getStyle("A{$totalRow}:F{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$totalRow}:F{$totalRow}")->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F3F4F6');

                foreach (['A' => 6, 'B' => 22, 'C' => 22, 'D' => 12, 'E' => 12, 'F' => 14] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}