<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RekapAgamaExport implements FromArray, WithEvents
{
    protected array $data;
    protected array $agamaList = ['Islam', 'Kristen', 'Katholik', 'Hindu', 'Budha'];
    protected int $headerRows = 7;

    // Warna disamakan dengan sheet "agama" di file data pegawai
    protected string $warnaBiru = 'DCE6F2';   // nama agama & kolom JML
    protected string $warnaOranye = 'FCD5B5'; // baris TOTAL

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapAgama($periode);
    }

    public function array(): array
    {
        $rows = [];
        $no = 1;
        foreach ($this->data as $d) {
            // Total gabungan pria+wanita per agama, untuk blok kolom P-U
            $totalPerAgama = [];
            foreach ($this->agamaList as $agama) {
                $totalPerAgama[] = ($d['pria'][$agama] ?? 0) + ($d['wanita'][$agama] ?? 0);
            }

            $rows[] = array_merge(
                [$no++, $d['instansi']],
                array_values($d['pria']),
                [$d['jml_pria']],
                array_values($d['wanita']),
                [$d['jml_wanita']],
                [$d['jml_total']],
                [$d['instansi']],   // kolom P: instansi diulang
                $totalPerAgama       // kolom Q-U: total per agama
            );
        }
        return $rows;
    }

    protected function formatPeriode(string $periode): string
    {
        $bulan = [
            '01'=>'JANUARI','02'=>'FEBRUARI','03'=>'MARET','04'=>'APRIL',
            '05'=>'MEI','06'=>'JUNI','07'=>'JULI','08'=>'AGUSTUS',
            '09'=>'SEPTEMBER','10'=>'OKTOBER','11'=>'NOVEMBER','12'=>'DESEMBER',
        ];
        [$tahun, $bln] = explode('-', $periode);
        return ($bulan[$bln] ?? $bln) . ' ' . $tahun;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->insertNewRowBefore(1, $this->headerRows);

                // Judul
                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH ASN PEMERINTAH DAERAH/KABUPATEN/KOTA PEMERINTAH KOTA YOGYAKARTA');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT AGAMA DAN JENIS KELAMIN');
                $sheet->setCellValue('A3', 'KEADAAN : ' . $this->formatPeriode($this->periode));
                $sheet->mergeCells('A1:O1');
                $sheet->mergeCells('A2:O2');
                $sheet->mergeCells('A3:O3');

                // Header utama baris 5-7
                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'INSTANSI');
                $sheet->setCellValue('C5', 'PRIA');
                $sheet->setCellValue('H5', 'JML');
                $sheet->setCellValue('I5', 'WANITA');
                $sheet->setCellValue('N5', 'JML');
                $sheet->setCellValue('O5', 'JML TOTAL');
                $sheet->setCellValue('P5', 'INSTANSI'); // header blok tambahan (nama instansi diulang)
                $sheet->setCellValue('Q5', 'Total');    // label blok total per agama

                $sheet->mergeCells('A5:A7');
                $sheet->mergeCells('B5:B7');
                $sheet->mergeCells('C5:G5');
                $sheet->mergeCells('H5:H7');
                $sheet->mergeCells('I5:M5');
                $sheet->mergeCells('N5:N7');
                $sheet->mergeCells('O5:O7');
                $sheet->mergeCells('P5:P7');
                $sheet->mergeCells('Q5:U6');

                $kolomPria = ['C', 'D', 'E', 'F', 'G'];
                $kolomWanita = ['I', 'J', 'K', 'L', 'M'];
                $kolomTotal = ['Q', 'R', 'S', 'T', 'U'];
                foreach ($this->agamaList as $i => $nama) {
                    $sheet->setCellValue($kolomPria[$i] . '7', $nama);
                    $sheet->setCellValue($kolomWanita[$i] . '7', $nama);
                    $sheet->setCellValue($kolomTotal[$i] . '7', $nama);
                }

                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;

                // Baris footer TOTAL — jumlah semua instansi
                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                foreach (range('C', 'O') as $col) {
                    $sum = 0;
                    for ($r = $this->headerRows + 1; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }
                foreach (range('Q', 'U') as $col) {
                    $sum = 0;
                    for ($r = $this->headerRows + 1; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }

                // ===== Styling (mengikuti sheet "agama" di file data pegawai) =====
                $thin = Border::BORDER_THIN;
                $hair = Border::BORDER_HAIR;
                $firstDataRow = $this->headerRows + 1;

                // Judul: bold & rata tengah (merge A:O)
                foreach ([1, 2, 3] as $r) {
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                // Header baris 5-7: bold, tengah, border tipis
                $sheet->getStyle('A5:O7')->getFont()->setBold(true);
                $sheet->getStyle('A5:O7')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle('A5:O7')->getBorders()->getAllBorders()->setBorderStyle($thin);

                // Blok tambahan P-U (nama instansi + total per agama): header sama seperti tabel utama
                $sheet->getStyle('P5:U7')->getFont()->setBold(true);
                $sheet->getStyle('P5:U7')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle('P5:U7')->getBorders()->getAllBorders()->setBorderStyle($thin);

                // Nama agama (baris 7) berwarna biru muda
                foreach (['C7:G7', 'I7:M7', 'Q7:U7'] as $range) {
                    $sheet->getStyle($range)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($this->warnaBiru);
                }

                // Baris data: garis kiri/kanan tipis, garis antar-baris tipis-halus (hair)
                for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                    $borders = $sheet->getStyle("A{$r}:O{$r}")->getBorders();
                    $borders->getLeft()->setBorderStyle($thin);
                    $borders->getRight()->setBorderStyle($thin);
                    $borders->getVertical()->setBorderStyle($thin);
                    $borders->getTop()->setBorderStyle($r === $firstDataRow ? $thin : $hair);
                    $borders->getBottom()->setBorderStyle($r === $lastDataRow ? $thin : $hair);
                }

                // Blok P-U: nama instansi (P) dan total per agama (Q-U) ikut bergaris
                $sheet->getStyle("P{$firstDataRow}:U{$lastDataRow}")->applyFromArray(['borders' => [
                    'outline' => ['borderStyle' => $thin],
                    'vertical' => ['borderStyle' => $thin],
                    'horizontal' => ['borderStyle' => $hair],
                ]]);
                $sheet->getStyle("Q{$firstDataRow}:U{$lastDataRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Kolom JML pria (H), JML wanita (N), JML total (O): biru muda + bold
                foreach (['H', 'N', 'O'] as $col) {
                    $range = "{$col}{$firstDataRow}:{$col}{$lastDataRow}";
                    $sheet->getStyle($range)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($this->warnaBiru);
                    $sheet->getStyle($range)->getFont()->setBold(true);
                }

                // Baris TOTAL: bold, border tipis, angka berwarna oranye muda
                $sheet->getStyle("A{$totalRow}:O{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$totalRow}:O{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);
                $sheet->setCellValue("P{$totalRow}", 'TOTAL');
                $sheet->getStyle("P{$totalRow}:U{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("P{$totalRow}:U{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);
                foreach (["C{$totalRow}:O{$totalRow}", "Q{$totalRow}:U{$totalRow}"] as $range) {
                    $sheet->getStyle($range)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($this->warnaOranye);
                }

                // Lebar kolom
                $lebar = [
                    'A' => 3.44, 'B' => 113.11, 'C' => 7, 'D' => 9.33, 'E' => 10.55, 'F' => 7, 'G' => 7,
                    'H' => 11.66, 'I' => 7, 'J' => 9.33, 'K' => 10.55, 'L' => 7, 'M' => 7, 'N' => 11.66,
                    'O' => 11, 'P' => 113.11, 'Q' => 10.5, 'R' => 10.5, 'S' => 10.5, 'T' => 10.5, 'U' => 10.5,
                ];
                foreach ($lebar as $col => $w) {
                    $sheet->getColumnDimension($col)->setWidth($w);
                }
            },
        ];
    }
}