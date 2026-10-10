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

/**
 * Rekap PPPK Paruh Waktu, dipisah pria / wanita / jumlah. Satu class untuk
 * tiga sudut pandang, dipilih lewat $jenis:
 *
 *   'instansi'      (default) per perangkat daerah
 *   'pendidikan'              per tingkat pendidikan
 *   'jenis_jabatan'           per jenis jabatan (pelaksana/fungsional/struktural)
 *
 * Data diambil dari RekapService — sumber yang sama dengan endpoint JSON
 * /rekap/paruh-waktu, jadi angka di Excel = angka di sistem.
 *
 * Gaya (warna, border, judul 3 baris) disamakan dengan RekapNakesExport.
 */
class RekapParuhWaktuExport implements FromArray, WithEvents, WithTitle
{
    protected array $data;
    protected int $headerRows = 5; // baris 1-3 judul, 4 kosong, 5 header kolom
    protected int $jumlahBaris;

    protected string $warnaBiru = 'DCE6F2';   // header tabel & kolom Jumlah
    protected string $warnaOranye = 'FCD5B5'; // baris TOTAL

    public function __construct(protected string $periode, protected string $jenis = 'instansi')
    {
        $service = new RekapService();

        $this->data = match ($jenis) {
            'pendidikan' => $service->rekapParuhWaktuPendidikan($periode),
            'jenis_jabatan' => $service->rekapParuhWaktuJenisJabatan($periode),
            default => $service->rekapParuhWaktu($periode),
        };

        // Minimal 1 baris supaya border/total tidak error saat belum ada data.
        $this->jumlahBaris = max(1, count($this->data));
    }

    public function title(): string
    {
        return $this->formatPeriode($this->periode); // contoh: "Oktober 2026"
    }

    public function array(): array
    {
        if (count($this->data) === 0) {
            return [[1, '(belum ada data PPPK paruh waktu)', 0, 0, 0]];
        }

        $rows = [];
        $no = 1;
        foreach ($this->data as $d) {
            $rows[] = [$no++, $d['instansi'] ?? $d['kategori'], $d['pria'], $d['wanita'], $d['jumlah']];
        }

        return $rows;
    }

    protected function subjudul(): string
    {
        return match ($this->jenis) {
            'pendidikan' => 'DATA PPPK PARUH WAKTU, DIPERINCI MENURUT TINGKAT PENDIDIKAN DAN JENIS KELAMIN',
            'jenis_jabatan' => 'DATA PPPK PARUH WAKTU, DIPERINCI MENURUT JENIS JABATAN DAN JENIS KELAMIN',
            default => 'DATA PPPK PARUH WAKTU, DIPERINCI MENURUT PERANGKAT DAERAH DAN JENIS KELAMIN',
        };
    }

    protected function labelKolom(): string
    {
        return match ($this->jenis) {
            'pendidikan' => 'Tingkat Pendidikan',
            'jenis_jabatan' => 'Jenis Jabatan',
            default => 'Perangkat Daerah',
        };
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

                $sheet->setCellValue(
                    'A1',
                    'REKAPITULASI ASN PEMERINTAH DAERAH KAB/KOTA PEMERINTAH KOTA YOGYAKARTA'
                );
                $sheet->setCellValue('A2', $this->subjudul());
                $sheet->setCellValue(
                    'A3',
                    'KEADAAN : ' . mb_strtoupper($this->formatPeriode($this->periode))
                );
                foreach ([1, 2, 3] as $r) {
                    $sheet->mergeCells("A{$r}:E{$r}");
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal('center')
                        ->setVertical('center');
                }

                $sheet->setCellValue('A5', 'No.');
                $sheet->setCellValue('B5', $this->labelKolom());
                $sheet->setCellValue('C5', 'Pria');
                $sheet->setCellValue('D5', 'Wanita');
                $sheet->setCellValue('E5', 'Jumlah');

                $firstDataRow = $this->headerRows + 1;
                $lastDataRow = $this->headerRows + $this->jumlahBaris;
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, '');
                $sheet->setCellValue('B' . $totalRow, 'TOTAL');

                foreach (['C', 'D', 'E'] as $col) {
                    $sum = 0;
                    for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }

                $sheet->getStyle('A5:E5')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A5:E5')->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center')
                    ->setWrapText(true);

                // ===== Styling =====
                $thin = Border::BORDER_THIN;
                $hair = Border::BORDER_HAIR;

                $warnai = function (string $range, string $rgb) use ($sheet) {
                    $sheet->getStyle($range)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($rgb);
                };

                // Header tabel
                $sheet->getStyle('A5:E5')->getBorders()->getAllBorders()->setBorderStyle($thin);
                $warnai('A5:E5', $this->warnaBiru);

                // Baris data
                for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                    $top = $r === $firstDataRow ? $thin : $hair;
                    $bottom = $r === $lastDataRow ? $thin : $hair;
                    $borders = $sheet->getStyle("A{$r}:E{$r}")->getBorders();
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

                // Baris TOTAL
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getFont()->setBold(true);
                $warnai("A{$totalRow}:E{$totalRow}", $this->warnaOranye);
                $sheet->getStyle("C{$totalRow}:E{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Lebar kolom
                $sheet->getColumnDimension('A')->setWidth(8);
                $sheet->getColumnDimension('B')->setWidth(55);
                $sheet->getColumnDimension('C')->setWidth(10);
                $sheet->getColumnDimension('D')->setWidth(10);
                $sheet->getColumnDimension('E')->setWidth(12);

                $sheet->getStyle("B{$firstDataRow}:B{$lastDataRow}")->getAlignment()
                    ->setWrapText(true)
                    ->setVertical('top');
                $sheet->getStyle("C{$firstDataRow}:E{$lastDataRow}")->getAlignment()
                    ->setHorizontal('center');
            },
        ];
    }
}