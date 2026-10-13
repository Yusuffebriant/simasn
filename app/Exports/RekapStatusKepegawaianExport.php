<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet "Status Kepegawaian":
 * No | Status Kepegawaian | Jumlah | Persentase
 *
 *   1 PNS
 *   2 PPPK
 *   3 PPPK Paruh Waktu
 *   4 Lainnya
 *        - NABAN
 *        - Pegawai Titipan
 *        - (status lain yang tidak dikenal saat import, kalau ada)
 *   TOTAL
 *
 * Baris rincian Lainnya (NABAN, Pegawai Titipan, dst.) TIDAK dijumlahkan
 * lagi ke Total karena sudah termasuk dalam baris Lainnya.
 *
 * Data memakai sumber yang sama dengan kartu Status Kepegawaian di
 * dashboard (RekapService::ringkasanStatusKepegawaian dan
 * rincianStatusLainnya). Kalau $ringkasan / $rincian tidak diberikan,
 * diambil langsung dari RekapService; export Dashboard mengirim data
 * yang sama dengan yang tampil di layar (termasuk arsip periode lama).
 *
 * Dipakai dengan dua cara:
 *   - sebagai sheet mandiri (export PPPK Paruh Waktu, rekap semua), lewat
 *     event AfterSheet di bawah;
 *   - sebagai sheet tambahan di dalam laporan Dashboard, dengan memanggil
 *     isiWorksheet() pada worksheet yang dibuat RekapDashboardExport.
 */
class RekapStatusKepegawaianExport implements FromArray, WithEvents, WithTitle
{
    protected array $data = [];         // tiap baris: [no, label, jumlah, persen]
    protected array $barisRincian = []; // indeks (0-based) baris rincian Lainnya
    protected int $total = 0;
    protected int $headerRows = 5;      // baris 1-3 judul, 4 kosong, 5 header kolom

    protected string $warnaBiru = 'DCE6F2';
    protected string $warnaOranye = 'FCD5B5';

    public function __construct(
        protected string $periode,
        ?array $ringkasan = null,
        ?array $rincian = null
    ) {
        $service = new RekapService();
        $ringkasan ??= $service->ringkasanStatusKepegawaian();
        $rincian ??= $service->rincianStatusLainnya();

        $pns = (int) ($ringkasan['PNS'] ?? 0);
        $pppk = (int) ($ringkasan['PPPK'] ?? 0);
        $pw = (int) ($ringkasan['PPPK_PW'] ?? 0);
        $lainnya = (int) ($ringkasan['LAINNYA'] ?? 0);

        $this->total = (int) ($ringkasan['TOTAL'] ?? ($pns + $pppk + $pw + $lainnya));

        $persen = fn (int $n) => $this->total > 0 ? round($n / $this->total * 100, 1) : 0;

        $this->data = [
            [1, 'PNS', $pns, $persen($pns)],
            [2, 'PPPK', $pppk, $persen($pppk)],
            [3, 'PPPK Paruh Waktu', $pw, $persen($pw)],
            [4, 'Lainnya', $lainnya, $persen($lainnya)],
        ];

        foreach ($rincian as $r) {
            $label = (string) ($r['label'] ?? '');
            if (str_starts_with($label, 'Lainnya (belum')) {
                $label = 'Belum terklasifikasi';
            }

            $jumlah = (int) ($r['total'] ?? 0);

            $this->barisRincian[] = count($this->data);
            $this->data[] = ['', '      - ' . $label, $jumlah, $persen($jumlah)];
        }
    }

    public function title(): string
    {
        return 'Status Kepegawaian';
    }

    /**
     * Sengaja kosong: seluruh isi sheet ditulis langsung oleh
     * isiWorksheet(), supaya cara yang sama bisa dipakai di sheet mandiri
     * maupun sebagai sheet tambahan di laporan Dashboard.
     */
    public function array(): array
    {
        return [];
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
                $this->isiWorksheet($event->sheet->getDelegate());
            },
        ];
    }

    /**
     * Menulis seluruh isi sheet (judul, header, data, total, catatan, dan
     * styling) langsung ke worksheet yang diberikan.
     */
    public function isiWorksheet(Worksheet $sheet): void
    {
        $firstDataRow = $this->headerRows + 1;
        $lastDataRow = $this->headerRows + count($this->data);
        $totalRow = $lastDataRow + 1;
        $catatanRow = $totalRow + 2;

        // ===== Judul =====
        $sheet->setCellValue(
            'A1',
            'REKAPITULASI ASN PEMERINTAH DAERAH KAB/KOTA PEMERINTAH KOTA YOGYAKARTA'
        );
        $sheet->setCellValue(
            'A2',
            'DATA STATUS KEPEGAWAIAN (PNS, PPPK, PPPK PARUH WAKTU, DAN LAINNYA)'
        );
        $sheet->setCellValue(
            'A3',
            'KEADAAN : ' . mb_strtoupper($this->formatPeriode($this->periode))
        );
        foreach ([1, 2, 3] as $r) {
            $sheet->mergeCells("A{$r}:D{$r}");
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            $sheet->getStyle("A{$r}")->getAlignment()
                ->setHorizontal('center')
                ->setVertical('center');
        }

        // ===== Header kolom =====
        $sheet->setCellValue('A5', 'No.');
        $sheet->setCellValue('B5', 'Status Kepegawaian');
        $sheet->setCellValue('C5', 'Jumlah');
        $sheet->setCellValue('D5', 'Persentase');

        // ===== Baris data =====
        foreach ($this->data as $i => $d) {
            $r = $firstDataRow + $i;
            $sheet->setCellValue('A' . $r, $d[0]);
            $sheet->setCellValue('B' . $r, $d[1]);
            $sheet->setCellValue('C' . $r, $d[2]);
            $sheet->setCellValue('D' . $r, $d[3]);
        }

        // ===== Baris TOTAL =====
        // Jumlah semua status; rincian Lainnya tidak ditambahkan lagi
        // karena sudah termasuk baris Lainnya.
        $sheet->setCellValue('A' . $totalRow, '');
        $sheet->setCellValue('B' . $totalRow, 'TOTAL');
        $sheet->setCellValue('C' . $totalRow, $this->total);
        $sheet->setCellValue('D' . $totalRow, $this->total > 0 ? 100 : 0);

        // ===== Catatan di bawah tabel =====
        $sheet->setCellValue(
            'A' . $catatanRow,
            'Catatan: "Lainnya" terdiri dari NABAN, Pegawai Titipan, dan status lain yang tidak '
            . 'termasuk PNS, PPPK, atau PPPK Paruh Waktu. Baris rincian Lainnya sudah termasuk '
            . 'dalam jumlah Lainnya dan tidak dijumlahkan lagi pada Total.'
        );
        $sheet->mergeCells("A{$catatanRow}:D{$catatanRow}");
        $sheet->getStyle("A{$catatanRow}")->getFont()->setItalic(true)->setSize(9);
        $sheet->getStyle("A{$catatanRow}")->getAlignment()->setWrapText(true)->setVertical('top');
        $sheet->getRowDimension($catatanRow)->setRowHeight(42);

        // ===== Styling =====
        $thin = Border::BORDER_THIN;
        $hair = Border::BORDER_HAIR;

        $warnai = function (string $range, string $rgb) use ($sheet) {
            $sheet->getStyle($range)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($rgb);
        };

        // Header tabel
        $sheet->getStyle('A5:D5')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A5:D5')->getAlignment()
            ->setHorizontal('center')
            ->setVertical('center')
            ->setWrapText(true);
        $sheet->getStyle('A5:D5')->getBorders()->getAllBorders()->setBorderStyle($thin);
        $warnai('A5:D5', $this->warnaBiru);

        // Border baris data
        for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
            $top = $r === $firstDataRow ? $thin : $hair;
            $bottom = $r === $lastDataRow ? $thin : $hair;
            $borders = $sheet->getStyle("A{$r}:D{$r}")->getBorders();
            $borders->getLeft()->setBorderStyle($thin);
            $borders->getRight()->setBorderStyle($thin);
            $borders->getVertical()->setBorderStyle($thin);
            $borders->getTop()->setBorderStyle($top);
            $borders->getBottom()->setBorderStyle($bottom);
        }

        // Nomor rata tengah
        $sheet->getStyle("A{$firstDataRow}:A{$lastDataRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Baris rincian Lainnya: huruf miring
        foreach ($this->barisRincian as $i) {
            $r = $firstDataRow + $i;
            $sheet->getStyle("B{$r}:D{$r}")->getFont()->setItalic(true);
        }

        // Kolom Jumlah (C): biru muda, bold untuk baris utama
        $warnai("C{$firstDataRow}:C{$lastDataRow}", $this->warnaBiru);
        foreach (array_keys($this->data) as $i) {
            if (!in_array($i, $this->barisRincian, true)) {
                $r = $firstDataRow + $i;
                $sheet->getStyle("C{$r}")->getFont()->setBold(true);
            }
        }

        // Format angka
        $sheet->getStyle("C{$firstDataRow}:C{$totalRow}")
            ->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("D{$firstDataRow}:D{$totalRow}")
            ->getNumberFormat()->setFormatCode('0.0"%"');

        // Baris TOTAL
        $sheet->getStyle("A{$totalRow}:D{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);
        $sheet->getStyle("A{$totalRow}:D{$totalRow}")->getFont()->setBold(true);
        $warnai("A{$totalRow}:D{$totalRow}", $this->warnaOranye);

        $sheet->getStyle("C{$firstDataRow}:D{$totalRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Lebar kolom
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(14);
    }
}