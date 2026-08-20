<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RekapAgamaExport implements FromArray, WithEvents
{
    protected array $data;
    protected array $agamaList = ['Islam', 'Kristen', 'Katholik', 'Hindu', 'Budha'];
    protected int $headerRows = 7; // baris 1-7 dipakai judul+header, data mulai baris 8

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapAgama($periode);
    }

    /**
     * Cuma data mentah, TANPA header. Header ditulis manual di registerEvents()
     * supaya posisinya pasti, tidak tergantung baris kosong yang bisa ke-skip.
     */
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

                // Geser semua data yang sudah tertulis turun sejumlah $headerRows,
                // supaya baris 1..headerRows kosong dan siap ditulis header manual.
                $sheet->insertNewRowBefore(1, $this->headerRows);

                // Judul
                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH ASN PEMERINTAH DAERAH/KABUPATEN/KOTA PEMERINTAH KOTA YOGYAKARTA');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT AGAMA DAN JENIS KELAMIN');
                $sheet->setCellValue('A3', 'KEADAAN : ' . $this->formatPeriode($this->periode));
                $sheet->mergeCells('A1:O1');
                $sheet->mergeCells('A2:O2');
                $sheet->mergeCells('A3:O3');

                // Header baris 5: NO, INSTANSI, PRIA, JML, WANITA, JML, JML TOTAL
                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'INSTANSI');
                $sheet->setCellValue('C5', 'PRIA');
                $sheet->setCellValue('H5', 'JML');
                $sheet->setCellValue('I5', 'WANITA');
                $sheet->setCellValue('N5', 'JML');
                $sheet->setCellValue('O5', 'JML TOTAL');

                $sheet->mergeCells('A5:A7');
                $sheet->mergeCells('B5:B7');
                $sheet->mergeCells('C5:G5');
                $sheet->mergeCells('H5:H7');
                $sheet->mergeCells('I5:M5');
                $sheet->mergeCells('N5:N7');
                $sheet->mergeCells('O5:O7');

                // Baris 7: nama agama, dua kali (blok Pria & blok Wanita)
                $kolomPria = ['C', 'D', 'E', 'F', 'G'];
                $kolomWanita = ['I', 'J', 'K', 'L', 'M'];
                foreach ($this->agamaList as $i => $nama) {
                    $sheet->setCellValue($kolomPria[$i] . '7', $nama);
                    $sheet->setCellValue($kolomWanita[$i] . '7', $nama);
                }

                // Styling
                $lastRow = $this->headerRows + count($this->data);
                $sheet->getStyle('A1:A3')->getFont()->setBold(true);
                $sheet->getStyle('A5:O7')->getFont()->setBold(true);
                $sheet->getStyle('A5:O7')->getAlignment()->setHorizontal('center')->setVertical('center');
                $sheet->getStyle("A5:O{$lastRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }
}