<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class RekapPendidikanExport implements FromArray, WithEvents
{
    protected array $data;
    protected array $pendidikanList = ['SD', 'SLTP', 'SLTA', 'D I', 'D II', 'D III', 'D IV', 'S1', 'S2', 'S3', 'BELUM DIISI'];
    protected int $headerRows = 7;

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
                $sheet->mergeCells('A1:Z1');
                $sheet->mergeCells('A2:Z2');
                $sheet->mergeCells('A3:Z3');

                $sheet->setCellValue('A5', 'NO');
                $sheet->setCellValue('B5', 'INSTANSI');
                $sheet->setCellValue('C5', 'PRIA');
                $sheet->setCellValue('N5', 'JML');
                $sheet->setCellValue('O5', 'WANITA');
                $sheet->setCellValue('Z5', 'JML');
                $sheet->setCellValue('AA5', 'JML TOTAL');

                $sheet->mergeCells('A5:A7');
                $sheet->mergeCells('B5:B7');
                $sheet->mergeCells('C5:M5');
                $sheet->mergeCells('N5:N7');
                $sheet->mergeCells('O5:Y5');
                $sheet->mergeCells('Z5:Z7');
                $sheet->mergeCells('AA5:AA7');

                $kolomPria = ['C','D','E','F','G','H','I','J','K','L','M'];
                $kolomWanita = ['O','P','Q','R','S','T','U','V','W','X','Y'];
                foreach ($this->pendidikanList as $i => $nama) {
                    $sheet->setCellValue($kolomPria[$i] . '7', $nama);
                    $sheet->setCellValue($kolomWanita[$i] . '7', $nama);
                }

                $lastDataRow = $this->headerRows + count($this->data);
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                $sheet->mergeCells("A{$totalRow}:B{$totalRow}");

                // FIX: range('C', 'AA') tidak valid untuk kolom multi-huruf di PHP.
                // Gunakan konversi index kolom dari PhpSpreadsheet sebagai gantinya.
                $startCol = Coordinate::columnIndexFromString('C');
                $endCol   = Coordinate::columnIndexFromString('AA');

                for ($colIndex = $startCol; $colIndex <= $endCol; $colIndex++) {
                    $col = Coordinate::stringFromColumnIndex($colIndex);

                    $sum = 0;
                    for ($r = $this->headerRows + 1; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }
                $sheet->getStyle("A{$totalRow}:AA{$totalRow}")->getFont()->setBold(true);

                // ==== TAMBAHAN: Jumlah keseluruhan + keterangan ====
                $grandTotal = (int) $sheet->getCell('AA' . $totalRow)->getValue();
                $belumDiisiIdx = array_search('BELUM DIISI', $this->pendidikanList);
                $kolBelumDiisiPria = $kolomPria[$belumDiisiIdx] ?? null;
                $kolBelumDiisiWanita = $kolomWanita[$belumDiisiIdx] ?? null;
                $jmlBelumDiisi = 0;
                if ($kolBelumDiisiPria && $kolBelumDiisiWanita) {
                    $jmlBelumDiisi = (int) $sheet->getCell($kolBelumDiisiPria . $totalRow)->getValue()
                        + (int) $sheet->getCell($kolBelumDiisiWanita . $totalRow)->getValue();
                }

                $infoRow1 = $totalRow + 2;
                $infoRow2 = $totalRow + 3;

                $sheet->setCellValue('A' . $infoRow1, 'JUMLAH KESELURUHAN ASN: ' . $grandTotal . ' ORANG');
                $sheet->mergeCells("A{$infoRow1}:AA{$infoRow1}");
                $sheet->getStyle('A' . $infoRow1)->getFont()->setBold(true);

                $sheet->setCellValue(
                    'A' . $infoRow2,
                    'Keterangan: Kolom "BELUM DIISI" menampilkan pegawai yang data pendidikannya belum dilengkapi di sistem'
                    . ($jmlBelumDiisi > 0 ? ' (' . $jmlBelumDiisi . ' orang).' : '.')
                );
                $sheet->mergeCells("A{$infoRow2}:AA{$infoRow2}");
                $sheet->getStyle('A' . $infoRow2)->getFont()->setItalic(true)->setSize(9);
                // ==== END TAMBAHAN ====

                $sheet->getStyle('A1:A3')->getFont()->setBold(true);
                $sheet->getStyle('A5:AA7')->getFont()->setBold(true);
                $sheet->getStyle('A5:AA7')->getAlignment()->setHorizontal('center')->setVertical('center');
                $sheet->getStyle("A5:AA{$totalRow}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }
}