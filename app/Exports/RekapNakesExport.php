<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RekapNakesExport implements FromArray, WithEvents, WithTitle
{
    protected array $data;
    protected int $headerRows = 5; // baris 1-3 judul, 4 kosong, 5 header kolom

    // Warna disamakan dengan sheet rekap lain (agama, pend, jab, dst) di file data pegawai
    protected string $warnaBiru = 'DCE6F2';   // header tabel & kolom Jumlah
    protected string $warnaOranye = 'FCD5B5'; // baris TOTAL

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapNakes($periode);
    }

    public function title(): string
    {
        return $this->formatPeriode($this->periode); // contoh: "Juli 2026"
    }

    public function array(): array
    {
        $rows = [];
        $no = 1;
        foreach ($this->data as $d) {
            $rows[] = [$no++, $d['fasilitas'], $d['pria'], $d['wanita'], $d['jumlah'], $d['alamat']];
        }
        return $rows;
    }

    protected function formatPeriode(string $periode): string
    {
        $bulan = [
            '01' => 'Januari',
            '02' => 'Februari',
            '03' => 'Maret',
            '04' => 'April',
            '05' => 'Mei',
            '06' => 'Juni',
            '07' => 'Juli',
            '08' => 'Agustus',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember',
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

                // Judul 3 baris rata tengah (gaya sama seperti sheet rekap lain di file data pegawai)
                $sheet->setCellValue(
                    'A1',
                    'REKAPITULASI ASN PEMERINTAH DAERAH KAB/KOTA PEMERINTAH KOTA YOGYAKARTA'
                );
                $sheet->setCellValue(
                    'A2',
                    'DATA PEJABAT FUNGSIONAL NAKES, DIPERINCI MENURUT FASILITAS KESEHATAN DAN JENIS KELAMIN'
                );
                $sheet->setCellValue(
                    'A3',
                    'KEADAAN : ' . mb_strtoupper($this->formatPeriode($this->periode))
                );
                foreach ([1, 2, 3] as $r) {
                    $sheet->mergeCells("A{$r}:F{$r}");
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal('center')
                        ->setVertical('center');
                }

                $sheet->setCellValue('A5', 'No.');
                $sheet->setCellValue('B5', 'Fasilitas Kesehatan');
                $sheet->setCellValue('C5', 'Pria');
                $sheet->setCellValue('D5', 'Wanita');
                $sheet->setCellValue('E5', 'Jumlah');
                $sheet->setCellValue('F5', 'Alamat');

                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, '');
                $sheet->setCellValue('B' . $totalRow, 'TOTAL');

                foreach (['C', 'D', 'E'] as $col) {
                    $sum = 0;
                    for ($r = $this->headerRows + 1; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }

                $sheet->getStyle('A5:F5')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A5:F5')->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);

                // ===== Styling (mengikuti gaya sheet rekap di file data pegawai) =====
                $thin = Border::BORDER_THIN;
                $hair = Border::BORDER_HAIR;
                $firstDataRow = $this->headerRows + 1;

                $warnai = function (string $range, string $rgb) use ($sheet) {
                    $sheet->getStyle($range)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($rgb);
                };

                // Header tabel: border tipis + biru muda
                $sheet->getStyle('A5:F5')->getBorders()->getAllBorders()->setBorderStyle($thin);
                $warnai('A5:F5', $this->warnaBiru);

                // Baris data: garis kotak per baris (kiri/kanan tipis, atas/bawah halus)
                for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                    $top = $r === $firstDataRow ? $thin : $hair;
                    $bottom = $r === $lastDataRow ? $thin : $hair;
                    $borders = $sheet->getStyle("A{$r}:F{$r}")->getBorders();
                    $borders->getLeft()->setBorderStyle($thin);
                    $borders->getRight()->setBorderStyle($thin);
                    $borders->getVertical()->setBorderStyle($thin);
                    $borders->getTop()->setBorderStyle($top);
                    $borders->getBottom()->setBorderStyle($bottom);
                }

                // Nomor rata tengah
                $sheet->getStyle("A{$firstDataRow}:A{$lastDataRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_TOP);

                // Kolom Jumlah (E): biru muda + bold
                $warnai("E{$firstDataRow}:E{$lastDataRow}", $this->warnaBiru);
                $sheet->getStyle("E{$firstDataRow}:E{$lastDataRow}")->getFont()->setBold(true);

                // Baris TOTAL: bold, border tipis, oranye muda, angka rata tengah
                $sheet->getStyle("A{$totalRow}:F{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);
                $sheet->getStyle("A{$totalRow}:F{$totalRow}")->getFont()->setBold(true);
                $warnai("A{$totalRow}:F{$totalRow}", $this->warnaOranye);
                $sheet->getStyle("C{$totalRow}:E{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Lebar kolom sama seperti template excel yang dikirim user.
                $sheet->getColumnDimension('A')->setWidth(8);
                $sheet->getColumnDimension('B')->setWidth(43.5);
                $sheet->getColumnDimension('C')->setWidth(8.2);
                $sheet->getColumnDimension('D')->setWidth(10.2);
                $sheet->getColumnDimension('E')->setWidth(10.5);
                $sheet->getColumnDimension('F')->setWidth(54.7);

                $sheet->getStyle('B' . $firstDataRow . ':B' . $lastDataRow)->getAlignment()->setWrapText(true)->setVertical('top');
                $sheet->getStyle('F' . $firstDataRow . ':F' . $lastDataRow)->getAlignment()->setWrapText(true)->setVertical('top');
                $sheet->getStyle("C{$firstDataRow}:E{$lastDataRow}")->getAlignment()->setHorizontal('center');
            },
        ];
    }
}