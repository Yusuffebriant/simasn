<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RekapGolonganExport implements FromArray, WithEvents
{
    protected array $data;

    protected array $golonganList = [
        'I/a', 'I/b', 'I/c', 'I/d',
        'II/a', 'II/b', 'II/c', 'II/d',
        'III/a', 'III/b', 'III/c', 'III/d',
        'IV/a', 'IV/b', 'IV/c', 'IV/d',
        'IV/e',
    ];
    protected array $pppkList = ['I', 'III', 'V', 'VII', 'IX', 'X', 'XI'];
    protected array $pnsAggList = ['I', 'II', 'III', 'IV'];

    protected int $headerRows = 7;

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())->rekapGolongan($periode);
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
                [$d['jml_total']],
                ['', $d['instansi']], // AN: spacer, AO: instansi diulang
                array_values($d['pns_agg']),
                [$d['pns_total']],
                array_values($d['pppk']),
                [$d['pppk_total']]
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
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT GOLONGAN RUANG DAN JENIS KELAMIN');
                $sheet->setCellValue('A3', 'KEADAAN : ' . $this->formatPeriode($this->periode));
                $sheet->mergeCells('A1:BB1');
                $sheet->mergeCells('A2:BB2');
                $sheet->mergeCells('A3:BB3');

                // Header utama baris 5-7 (blok pertama, sudah ada sebelumnya)
                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'INSTANSI');
                $sheet->setCellValue('C5', 'PRIA');
                $sheet->setCellValue('T5', 'JML');
                $sheet->setCellValue('U5', 'WANITA');
                $sheet->setCellValue('AL5', 'JML');
                $sheet->setCellValue('AM5', 'JML TOTAL');

                $sheet->mergeCells('A5:A7');
                $sheet->mergeCells('B5:B7');
                $sheet->mergeCells('C5:S5');
                $sheet->mergeCells('T5:T7');
                $sheet->mergeCells('U5:AK5');
                $sheet->mergeCells('AL5:AL7');
                $sheet->mergeCells('AM5:AM7');

                $kolomPria = ['C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S'];
                $kolomWanita = ['U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK'];
                foreach ($this->golonganList as $i => $nama) {
                    $sheet->setCellValue($kolomPria[$i] . '7', $nama);
                    $sheet->setCellValue($kolomWanita[$i] . '7', $nama);
                }

                // Blok kedua: AO = instansi, AP-AT = PNS (I,II,III,IV,Total), AU-BB = PPPK
                $sheet->setCellValue('AO5', 'INSTANSI');
                $sheet->setCellValue('AP5', 'PNS');
                $sheet->setCellValue('AU5', 'PPPK');
                $sheet->mergeCells('AO5:AO7');
                $sheet->mergeCells('AP5:AT5');
                $sheet->mergeCells('AU5:BB5');

                $kolomPns = ['AP','AQ','AR','AS'];
                foreach ($this->pnsAggList as $i => $romawi) {
                    $sheet->setCellValue($kolomPns[$i] . '7', $romawi);
                }
                $sheet->setCellValue('AT7', 'Total');

                $kolomPppk = ['AU','AV','AW','AX','AY','AZ','BA'];
                foreach ($this->pppkList as $i => $kode) {
                    $sheet->setCellValue($kolomPppk[$i] . '7', $kode);
                }
                $sheet->setCellValue('BB7', 'Total');

                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                // SUM semua kolom angka: C..AM dan AP..BB (lewati AN spacer & AO instansi teks)
                $kolomAngka = array_merge(
                    range(Coordinate::columnIndexFromString('C'), Coordinate::columnIndexFromString('AM')),
                    range(Coordinate::columnIndexFromString('AP'), Coordinate::columnIndexFromString('BB'))
                );
                foreach ($kolomAngka as $colIdx) {
                    $col = Coordinate::stringFromColumnIndex($colIdx);
                    $sum = 0;
                    for ($r = $this->headerRows + 1; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }
                $sheet->getStyle("A{$totalRow}:BB{$totalRow}")->getFont()->setBold(true);

                // Styling
                $sheet->getStyle('A1:A3')->getFont()->setBold(true);
                $sheet->getStyle('A5:BB7')->getFont()->setBold(true);
                $sheet->getStyle('A5:BB7')->getAlignment()->setHorizontal('center')->setVertical('center');
                $sheet->getStyle("A5:BB{$totalRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }
}