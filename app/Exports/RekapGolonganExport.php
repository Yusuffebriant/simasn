<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Layout kolom di export ini meniru PERSIS format resmi
 * "data-asn-agustus-2026" (sheet "gol"):
 *
 *  C..Z   (24 kolom) : Pria  - golongan PNS & PPPK diselang-seling
 *  AA               : Sub Total Pria
 *  AB..AY (24 kolom) : Wanita - urutan sama seperti Pria
 *  AZ               : Sub Total Wanita
 *  BA               : TOTAL
 *  BB, BC           : kosong (spacer, sesuai referensi)
 *  BD..BH           : blok PNS (I, II, III, IV, Total)
 *  BI..BP           : blok PPPK detail (I/1a, III/1c, ... , Total)
 */
class RekapGolonganExport implements FromArray, WithEvents
{
    protected array $data;

    // Urutan tampil 24 kolom Pria/Wanita, sesuai template referensi.
    // Setiap key di sini adalah key yang sama persis dengan yang dipakai
    // RekapService::rekapGolongan() di $d['pria'] / $d['wanita'].
    protected array $golonganDisplayOrder = [
        'I/a', 'I', 'I/b', 'I/c', 'III', 'I/d',
        'II/a', 'V', 'II/b', 'II/c', 'VII', 'II/d',
        'III/a', 'IX', 'III/b', 'X', 'III/c', 'XI', 'III/d',
        'IV/a', 'IV/b', 'IV/c', 'IV/d', 'IV/e',
    ];

    // Urutan ini HARUS sama dengan urutan $pppkList di RekapService,
    // supaya array_values($d['pppk']) jatuh di label yang benar.
    protected array $pppkDetailLabel = [
        'I/1a', 'III/ 1c', 'V/2a', 'VII/2c', 'IX /3a', 'X/3b', 'XI/3c',
    ];

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
            $pria = array_map(fn ($k) => $d['pria'][$k] ?? 0, $this->golonganDisplayOrder);
            $wanita = array_map(fn ($k) => $d['wanita'][$k] ?? 0, $this->golonganDisplayOrder);

            $rows[] = array_merge(
                [$no++, $d['instansi']],
                $pria,
                [$d['jml_pria']],
                $wanita,
                [$d['jml_wanita']],
                [$d['jml_total']],
                ['', ''], // BB, BC: spacer kosong (sesuai referensi)
                array_values($d['pns_agg']),   // BD-BG: I,II,III,IV
                [$d['pns_total']],              // BH
                array_values($d['pppk']),       // BI-BO
                [$d['pppk_total']]              // BP
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

                $L = fn (int $i) => Coordinate::stringFromColumnIndex($i);

                // Judul
                $sheet->setCellValue('A1', 'REKAPITULASI JUMLAH ASN PEMERINTAH DAERAH/KABUPATEN/KOTA PEMERINTAH KOTA YOGYAKARTA');
                $sheet->setCellValue('A2', 'DIPERINCI MENURUT INSTANSI, GOLONGAN RUANG DAN JENIS KELAMIN');
                $sheet->setCellValue('A3', 'KEADAAN : ' . $this->formatPeriode($this->periode));
                $sheet->mergeCells('A1:BA1');
                $sheet->mergeCells('A2:BA2');
                $sheet->mergeCells('A3:BA3');

                // Header baris 5-7
                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'INSTANSI');
                $sheet->setCellValue('F5', 'PRIA');
                $sheet->setCellValue('AA5', 'Sub Total');
                $sheet->setCellValue('AE5', 'WANITA');
                $sheet->setCellValue('AZ5', 'Sub Total');
                $sheet->setCellValue('BA5', 'TOTAL');
                $sheet->setCellValue('BD5', 'PNS');
                $sheet->setCellValue('BK5', 'PPPK');

                $sheet->mergeCells('A5:A7');
                $sheet->mergeCells('B5:B7');
                $sheet->mergeCells('F5:Z6');   // label PRIA (persis posisi referensi)
                $sheet->mergeCells('AA5:AA7');
                $sheet->mergeCells('AE5:AY6'); // label WANITA (persis posisi referensi)
                $sheet->mergeCells('AZ5:AZ7');
                $sheet->mergeCells('BA5:BA7');
                $sheet->mergeCells('BD5:BH6');
                $sheet->mergeCells('BK5:BP6');

                // Sub-kolom golongan baris 7: Pria C..Z, Wanita AB..AY
                foreach ($this->golonganDisplayOrder as $i => $nama) {
                    $sheet->setCellValue($L(3 + $i) . '7', $nama);
                    $sheet->setCellValue($L(28 + $i) . '7', $nama);
                }

                // Blok PNS: BD..BG = I,II,III,IV ; BH = Total
                foreach ($this->pnsAggList as $i => $romawi) {
                    $sheet->setCellValue($L(56 + $i) . '7', $romawi);
                }
                $sheet->setCellValue('BH7', 'Total');

                // Blok PPPK detail: BI..BO ; BP = Total
                foreach ($this->pppkDetailLabel as $i => $label) {
                    $sheet->setCellValue($L(61 + $i) . '7', $label);
                }
                $sheet->setCellValue('BP7', 'Total');

                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                // SUM semua kolom angka: C..BA (Pria, Sub Total, Wanita, Sub Total, TOTAL)
                // dan BD..BP (PNS + PPPK). BB & BC (spacer) dilewati.
                $kolomAngka = array_merge(range(3, 53), range(56, 68));
                foreach ($kolomAngka as $colIdx) {
                    $col = $L($colIdx);
                    $sum = 0;
                    for ($r = $this->headerRows + 1; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }
                $sheet->getStyle("A{$totalRow}:BP{$totalRow}")->getFont()->setBold(true);

                // Styling
                $sheet->getStyle('A1:A3')->getFont()->setBold(true);
                $sheet->getStyle('A5:BP7')->getFont()->setBold(true);
                $sheet->getStyle('A5:BP7')->getAlignment()->setHorizontal('center')->setVertical('center');
                $sheet->getStyle("A5:BP{$totalRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }
}