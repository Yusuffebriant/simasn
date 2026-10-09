<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RekapPendidikanExport implements FromArray, WithEvents
{
    protected array $data;
    protected array $pendidikanList = ['SD', 'SLTP', 'SLTA', 'D I', 'D II', 'D III', 'D IV', 'S1', 'S2', 'S3'];
    protected int $headerRows = 7;

    // Warna disamakan dengan sheet "pend" di file data pegawai
    protected string $warnaBiru = 'DCE6F2';       // nama pendidikan & kolom JML TOTAL
    protected string $warnaTosca = 'DBEEF4';      // kolom JML pria & JML wanita
    protected string $warnaOranye = 'FDEADA';     // baris TOTAL

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapPendidikan($periode);
    }

    public function array(): array
    {
        $rows = [];
        $no = 1;
        foreach ($this->data as $d) {
            $rows[] = array_merge(
                [$no++, $d['instansi']],
                array_values($d['pria']),
                [$d['jml_pria']],
                array_values($d['wanita']),
                [$d['jml_wanita']],
                [$d['jml_total']]
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

                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH ASN PEMERINTAH DAERAH/KABUPATEN/KOTA PEMERINTAH KOTA YOGYAKARTA');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT PENDIDIKAN DAN JENIS KELAMIN');
                $sheet->setCellValue('A3', 'KEADAAN : ' . $this->formatPeriode($this->periode));
                $sheet->mergeCells('A1:Y1');
                $sheet->mergeCells('A2:Y2');
                $sheet->mergeCells('A3:Y3');

                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'INSTANSI');
                $sheet->setCellValue('C5', 'PRIA');
                $sheet->setCellValue('M5', 'JML');
                $sheet->setCellValue('N5', 'WANITA');
                $sheet->setCellValue('X5', 'JML');
                $sheet->setCellValue('Y5', 'JML TOTAL');

                $sheet->mergeCells('A5:A7');
                $sheet->mergeCells('B5:B7');
                $sheet->mergeCells('C5:L5');
                $sheet->mergeCells('M5:M7');
                $sheet->mergeCells('N5:W5');
                $sheet->mergeCells('X5:X7');
                $sheet->mergeCells('Y5:Y7');

                $kolomPria = ['C','D','E','F','G','H','I','J','K','L'];
                $kolomWanita = ['N','O','P','Q','R','S','T','U','V','W'];
                foreach ($this->pendidikanList as $i => $nama) {
                    $sheet->setCellValue($kolomPria[$i] . '7', $nama);
                    $sheet->setCellValue($kolomWanita[$i] . '7', $nama);
                }

                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                foreach (range('C', 'Y') as $col) {
                    $sum = 0;
                    for ($r = $this->headerRows + 1; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }

                // ===== Styling (mengikuti sheet "pend" di file data pegawai) =====
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

                // Header baris 5-7: bold, rata tengah, border tipis
                $sheet->getStyle('A5:Y7')->getFont()->setBold(true);
                $sheet->getStyle('A5:Y7')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getStyle('A5:Y7')->getBorders()->getAllBorders()->setBorderStyle($thin);

                // Nama pendidikan (baris 7, blok pria C:L & wanita N:W): biru muda
                $warnai('C7:L7', $this->warnaBiru);
                $warnai('N7:W7', $this->warnaBiru);

                // Baris data: garis kotak per baris (kiri/kanan tipis, atas/bawah halus)
                for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                    $top = $r === $firstDataRow ? $thin : $hair;
                    $bottom = $r === $lastDataRow ? $thin : $hair;
                    $borders = $sheet->getStyle("A{$r}:Y{$r}")->getBorders();
                    $borders->getLeft()->setBorderStyle($thin);
                    $borders->getRight()->setBorderStyle($thin);
                    $borders->getVertical()->setBorderStyle($thin);
                    $borders->getTop()->setBorderStyle($top);
                    $borders->getBottom()->setBorderStyle($bottom);
                }

                // Kolom JML pria (M) & JML wanita (X): tosca muda; JML TOTAL (Y): biru muda. Semua bold
                foreach (['M' => $this->warnaTosca, 'X' => $this->warnaTosca, 'Y' => $this->warnaBiru] as $col => $warna) {
                    $warnai("{$col}{$firstDataRow}:{$col}{$lastDataRow}", $warna);
                    $sheet->getStyle("{$col}{$firstDataRow}:{$col}{$lastDataRow}")->getFont()->setBold(true);
                }

                // Baris TOTAL: bold, border tipis, angka berwarna oranye muda
                $sheet->getStyle("A{$totalRow}:Y{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$totalRow}:Y{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);
                $warnai("C{$totalRow}:Y{$totalRow}", $this->warnaOranye);

                // ===== Rekap Pendidikan (blok AA:AK): total gabungan Pria+Wanita per instansi =====
                // Baris-barisnya sejajar dengan tabel utama (satu baris = satu instansi),
                // AK = jumlah semua jenjang per instansi, baris TOTAL di paling bawah.
                $kolomPria = ['C','D','E','F','G','H','I','J','K','L'];
                $kolomWanita = ['N','O','P','Q','R','S','T','U','V','W'];
                $kolomRekap = ['AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ'];

                $sheet->setCellValue('AA5', 'Rekap Pendidikan');
                $sheet->mergeCells('AA5:AK6');
                foreach ($this->pendidikanList as $i => $nama) {
                    $sheet->setCellValue($kolomRekap[$i] . '7', $nama);
                }
                $sheet->setCellValue('AK7', 'JML');

                $totalKolom = array_fill(0, count($kolomRekap), 0.0);
                $totalSemua = 0.0;
                for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                    $jmlBaris = 0.0;
                    foreach ($this->pendidikanList as $i => $nama) {
                        $gabungan = (float) $sheet->getCell($kolomPria[$i] . $r)->getValue()
                            + (float) $sheet->getCell($kolomWanita[$i] . $r)->getValue();
                        $sheet->setCellValue($kolomRekap[$i] . $r, $gabungan);
                        $jmlBaris += $gabungan;
                        $totalKolom[$i] += $gabungan;
                    }
                    $sheet->setCellValue('AK' . $r, $jmlBaris);
                    $totalSemua += $jmlBaris;
                }
                foreach ($this->pendidikanList as $i => $nama) {
                    $sheet->setCellValue($kolomRekap[$i] . $totalRow, $totalKolom[$i]);
                }
                $sheet->setCellValue('AK' . $totalRow, $totalSemua);

                // Header (judul + nama jenjang): bold, tengah, border tipis
                $sheet->getStyle('AA5:AK7')->getFont()->setBold(true);
                $sheet->getStyle('AA5:AK7')->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('AA5:AK7')->getBorders()->getAllBorders()->setBorderStyle($thin);
                $warnai('AA7:AK7', $this->warnaBiru);

                // Badan tabel: tanpa warna (kecuali kolom JML biru muda, sama dengan JML TOTAL
                // tabel utama), garis tegak tipis & garis halus antar-baris
                $warnai("AK{$firstDataRow}:AK{$lastDataRow}", $this->warnaBiru);
                $sheet->getStyle("AA{$firstDataRow}:AK{$lastDataRow}")->applyFromArray(['borders' => [
                    'outline' => ['borderStyle' => $thin],
                    'vertical' => ['borderStyle' => $thin],
                    'horizontal' => ['borderStyle' => $hair],
                ]]);
                $sheet->getStyle("AA{$firstDataRow}:AK{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("AK{$firstDataRow}:AK{$lastDataRow}")->getFont()->setBold(true);

                // Baris TOTAL: oranye (sama dengan baris TOTAL tabel utama), kolom JML oranye lebih tua
                $warnai("AA{$totalRow}:AJ{$totalRow}", $this->warnaOranye);
                $warnai("AK{$totalRow}", 'FCD5B5');
                $sheet->getStyle("AA{$totalRow}:AK{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("AA{$totalRow}:AK{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);

                foreach ($kolomRekap as $col) {
                    $sheet->getColumnDimension($col)->setWidth(7);
                }
                $sheet->getColumnDimension('Z')->setWidth(3);
                $sheet->getColumnDimension('AK')->setWidth(9);

                // Lebar kolom
                $sheet->getColumnDimension('A')->setWidth(3.44);
                $sheet->getColumnDimension('B')->setWidth(80);
                foreach (array_merge(range('C', 'L'), range('N', 'W')) as $col) {
                    $sheet->getColumnDimension($col)->setWidth(6.5);
                }
                foreach (['M', 'X'] as $col) {
                    $sheet->getColumnDimension($col)->setWidth(9);
                }
                $sheet->getColumnDimension('Y')->setWidth(10);

                // Bekukan judul & header kolom supaya tetap terlihat saat scroll ke bawah
                $sheet->freezePane('A' . ($this->headerRows + 1));
            },
        ];
    }
}