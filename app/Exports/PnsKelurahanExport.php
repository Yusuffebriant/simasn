<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export tabel "PNS Kelurahan" (5.03.019), dikelompokkan berdasarkan
 * Kemantren induknya. Sumber data sama persis dengan panel React
 * PnsKelurahanPanel.jsx, yaitu StatistikService::statistikPnsKelurahan().
 *
 * Layout sheet: satu baris per Kelurahan, dikelompokkan per Kemantren,
 * dengan baris "Total PNS Kelurahan di Wilayah Kemantren <Nama>" di akhir tiap kelompok dan baris
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
                'Total PNS Kelurahan di Wilayah Kemantren ' . $this->toTitleCase($namaKemantren),
                '',
                '',
                $totalLakiKemantren,
                $totalPerempuanKemantren,
                $detail['total'],
            ];
        }

        if (($this->data['tidak_dikenali'] ?? 0) > 0) {
            $this->tidakDikenaliIndex = count($rows);
            $rows[] = ['Tidak Dikenali', '', '', '', '', $this->data['tidak_dikenali']];
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

    protected function formatPeriode(?string $periode): string
    {
        $bulan = [
            '01' => 'JANUARI', '02' => 'FEBRUARI', '03' => 'MARET', '04' => 'APRIL',
            '05' => 'MEI', '06' => 'JUNI', '07' => 'JULI', '08' => 'AGUSTUS',
            '09' => 'SEPTEMBER', '10' => 'OKTOBER', '11' => 'NOVEMBER', '12' => 'DESEMBER',
        ];

        if (!$periode || !preg_match('/^(\d{4})-(\d{2})/', $periode, $m)) {
            $m = [null, date('Y'), date('m')];
        }

        return ($bulan[$m[2]] ?? $m[2]) . ' ' . $m[1];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Baris 1-3 judul, baris 4 kosong, baris 5 header kolom, data mulai baris 6
                // (mengikuti sheet contoh di file data pegawai).
                $headerRow = 5;
                $sheet->insertNewRowBefore(1, $headerRow);

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH PNS KELURAHAN');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT KEMANTREN, KELURAHAN, DAN JENIS KELAMIN');
                $sheet->setCellValue('A3', 'KEADAAN : ' . $this->formatPeriode($this->periode));
                foreach ([1, 2, 3] as $r) {
                    $sheet->mergeCells("A{$r}:F{$r}");
                }

                foreach (['No', 'Kemantren', 'Kelurahan', 'L', 'P', 'Jumlah PNS'] as $i => $label) {
                    $sheet->setCellValue(chr(65 + $i) . $headerRow, $label);
                }

                $firstDataRow = $headerRow + 1;
                $lastDataRow = $headerRow + count($this->rows);
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:C{$totalRow}");
                $sheet->setCellValue('D' . $totalRow, $this->grandLaki);
                $sheet->setCellValue('E' . $totalRow, $this->grandPerempuan);
                $sheet->setCellValue('F' . $totalRow, $this->data['jumlah_pns_kelurahan']);

                // ===== Styling (mengikuti sheet contoh: Calibri 11, tanpa warna) =====
                $thin = Border::BORDER_THIN;

                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

                // Judul: bold & rata tengah
                foreach ([1, 2, 3] as $r) {
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                // Header: bold, rata tengah, border tipis
                $sheet->getStyle("A{$headerRow}:F{$headerRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$headerRow}:F{$headerRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getRowDimension($headerRow)->setRowHeight(20);

                // Semua sel tabel: border tipis, rata tengah vertikal
                $sheet->getStyle("A{$headerRow}:F{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);
                $sheet->getStyle("A{$headerRow}:F{$totalRow}")->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);

                // No & angka rata tengah, nama rata kiri
                $sheet->getStyle("A{$firstDataRow}:A{$lastDataRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$firstDataRow}:C{$lastDataRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("D{$firstDataRow}:F{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Kolom Jumlah PNS: bold
                $sheet->getStyle("F{$firstDataRow}:F{$lastDataRow}")->getFont()->setBold(true);

                // Subtotal per Kemantren: label di kolom A digabung A:C, bold
                foreach ($this->subtotalIndexes as $index) {
                    $row = $firstDataRow + $index;
                    $sheet->mergeCells("A{$row}:C{$row}");
                    $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setIndent(1);
                }

                // "Tidak Dikenali" (jika ada): label digabung A:E
                if ($this->tidakDikenaliIndex !== null) {
                    $row = $firstDataRow + $this->tidakDikenaliIndex;
                    $sheet->mergeCells("A{$row}:E{$row}");
                    $sheet->getStyle("A{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                        ->setIndent(1);
                }

                // Baris TOTAL: bold ukuran 13
                $sheet->getStyle("A{$totalRow}:F{$totalRow}")->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle("A{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                    ->setIndent(1);
                $sheet->getRowDimension($totalRow)->setRowHeight(18);

                // Lebar kolom
                foreach (['A' => 6, 'B' => 24, 'C' => 26, 'D' => 10, 'E' => 10, 'F' => 14] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                // Header tetap terlihat saat scroll & siap cetak
                $sheet->freezePane('A' . $firstDataRow);
                $sheet->getPageSetup()->setFitToPage(true);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
            },
        ];
    }
}