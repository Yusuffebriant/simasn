<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RekapJabatanExport implements FromArray, WithEvents
{
    protected array $data;

    protected array $eselonList = [
        'II A',
        'II B',
        'III A',
        'III B',
        'IV A',
        'IV B',
    ];

    protected int $headerRows = 6;

    // Warna disamakan dengan sheet "jab" di file data pegawai
    protected string $warnaAqua = 'B7DEE8';       // header ESELON & kolom JML (eselon)
    protected string $warnaBiru = 'B8CCE4';       // Fungsional Umum/ Jab. Pelaksana
    protected string $warnaOranye = 'FAC090';     // kolom JML TOTAL
    protected string $warnaOranyeMuda = 'FCD5B5'; // baris TOTAL

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapJabatan($periode);
    }

    public function array(): array
    {
        $rows = [];
        $no = 1;

        foreach ($this->data as $d) {
            $rows[] = array_merge(
                [$no++, $d['instansi']],
                array_values($d['eselon']),
                [$d['jml_eselon']],
                [$d['fungsional_umum']],
                [$d['fungsional_tertentu']],
                [$d['jml_total']]
            );
        }

        return $rows;
    }

    protected function formatPeriode(string $periode): string
    {
        $bulan = [
            '01' => 'JANUARI',
            '02' => 'FEBRUARI',
            '03' => 'MARET',
            '04' => 'APRIL',
            '05' => 'MEI',
            '06' => 'JUNI',
            '07' => 'JULI',
            '08' => 'AGUSTUS',
            '09' => 'SEPTEMBER',
            '10' => 'OKTOBER',
            '11' => 'NOVEMBER',
            '12' => 'DESEMBER',
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

                $sheet->setCellValue(
                    'A1',
                    'REKAPITULASI ASN PEMERINTAH DAERAH KAB/KOTA PEMERINTAH KOTA YOGYAKARTA'
                );

                $sheet->setCellValue(
                    'A2',
                    'DIPERINCI MENURUT JABATAN'
                );

                $sheet->setCellValue(
                    'A3',
                    'KEADAAN ' . $this->formatPeriode($this->periode)
                );

                $sheet->mergeCells('A1:L1');
                $sheet->mergeCells('A2:L2');
                $sheet->mergeCells('A3:L3');

                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'INSTANSI');
                $sheet->setCellValue('C5', 'ESELON');
                $sheet->setCellValue('I5', 'JML');
                $sheet->setCellValue('J5', 'Fungsional Umum/ Jab.Pelaksana');
                $sheet->setCellValue('K5', 'Fungsional Tertentu/ Jab. Fungsional');
                $sheet->setCellValue('L5', 'JML TOTAL');

                $sheet->mergeCells('A5:A6');
                $sheet->mergeCells('B5:B6');
                $sheet->mergeCells('C5:H5');
                $sheet->mergeCells('I5:I6');
                $sheet->mergeCells('J5:J6');
                $sheet->mergeCells('K5:K6');
                $sheet->mergeCells('L5:L6');

                $kolomEselon = ['C', 'D', 'E', 'F', 'G', 'H'];

                foreach ($this->eselonList as $i => $kode) {
                    $sheet->setCellValue(
                        $kolomEselon[$i] . '6',
                        $kode
                    );
                }

                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue(
                    'A' . $totalRow,
                    'TOTAL'
                );

                $sheet->mergeCells(
                    "A{$totalRow}:B{$totalRow}"
                );

                foreach (range('C', 'L') as $col) {
                    $sum = 0;

                    for (
                        $r = $this->headerRows + 1;
                        $r <= $lastDataRow;
                        $r++
                    ) {
                        $sum += (float) $sheet
                            ->getCell($col . $r)
                            ->getValue();
                    }

                    $sheet->setCellValue(
                        $col . $totalRow,
                        $sum
                    );
                }

                // ===== Styling (mengikuti sheet "jab" di file data pegawai) =====
                $thin = Border::BORDER_THIN;
                $hair = Border::BORDER_HAIR;
                $firstDataRow = $this->headerRows + 1;

                $warnai = function (string $range, string $rgb) use ($sheet) {
                    $sheet->getStyle($range)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($rgb);
                };

                // Judul: bold & rata tengah
                foreach ([1, 2, 3] as $r) {
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }

                // Header baris 5-6: bold, rata tengah, border tipis
                $sheet->getStyle('A5:L6')->getFont()->setBold(true);
                $sheet->getStyle('A5:L6')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle('A5:L6')->getBorders()->getAllBorders()->setBorderStyle($thin);

                // Warna header: ESELON aqua, Fungsional Umum biru, JML TOTAL oranye
                $warnai('C5:H5', $this->warnaAqua);
                $warnai('J5:J6', $this->warnaBiru);
                $warnai('L5:L6', $this->warnaOranye);

                // Baris data: garis kotak per baris (kiri/kanan tipis, atas/bawah halus)
                for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                    $top = $r === $firstDataRow ? $thin : $hair;
                    $bottom = $r === $lastDataRow ? $thin : $hair;
                    $borders = $sheet->getStyle("A{$r}:L{$r}")->getBorders();
                    $borders->getLeft()->setBorderStyle($thin);
                    $borders->getRight()->setBorderStyle($thin);
                    $borders->getVertical()->setBorderStyle($thin);
                    $borders->getTop()->setBorderStyle($top);
                    $borders->getBottom()->setBorderStyle($bottom);
                }

                // Angka & nomor rata tengah
                $sheet->getStyle("A{$firstDataRow}:A{$lastDataRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$firstDataRow}:L{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Kolom JML (I) aqua, Fungsional Umum (J) biru, JML TOTAL (L) oranye
                $warnai("I{$firstDataRow}:I{$lastDataRow}", $this->warnaAqua);
                $warnai("J{$firstDataRow}:J{$lastDataRow}", $this->warnaBiru);
                $warnai("L{$firstDataRow}:L{$lastDataRow}", $this->warnaOranye);

                // Baris TOTAL: bold ukuran 13, border tipis, oranye muda (JML TOTAL oranye lebih tua)
                $sheet->getStyle("A{$totalRow}:L{$totalRow}")->getFont()
                    ->setBold(true)
                    ->setSize(13);
                $sheet->getStyle("A{$totalRow}:L{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);
                $sheet->getStyle("A{$totalRow}:L{$totalRow}")->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $warnai("A{$totalRow}:K{$totalRow}", $this->warnaOranyeMuda);
                $warnai("L{$totalRow}", $this->warnaOranye);
                $sheet->getRowDimension($totalRow)->setRowHeight(17.4);

                // Lebar kolom: INSTANSI menyesuaikan nama terpanjang supaya tidak terpotong
                $terpanjang = 0;
                foreach ($this->data as $d) {
                    $terpanjang = max($terpanjang, mb_strlen((string) $d['instansi']));
                }
                $sheet->getColumnDimension('A')->setWidth(4.5);
                $sheet->getColumnDimension('B')->setWidth(min(113, max(40, $terpanjang * 1.1)));
                foreach (range('C', 'H') as $col) {
                    $sheet->getColumnDimension($col)->setWidth(7);
                }
                $sheet->getColumnDimension('I')->setWidth(11.66);
                $sheet->getColumnDimension('J')->setWidth(23.44);
                $sheet->getColumnDimension('K')->setWidth(18.66);
                $sheet->getColumnDimension('L')->setWidth(11);
            },
        ];
    }
}