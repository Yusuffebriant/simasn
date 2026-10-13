<?php

namespace App\Exports;

use App\Services\RekapParuhWaktuTambahanService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Sheet "Persentase Paruh Waktu per Instansi":
 * No | Perangkat Daerah | PPPK Paruh Waktu | Total Pegawai | Persentase.
 *
 * Datanya dari RekapParuhWaktuTambahanService::rekapPersentaseInstansi(),
 * sumber yang sama dengan tabel di layar. Gaya (judul 3 baris, warna,
 * border) disamakan dengan RekapParuhWaktuExport.
 *
 * Berbeda dengan sheet lain, baris TOTAL di sini tidak menjumlahkan
 * persentase: persentase total = total Paruh Waktu / total pegawai.
 */
class RekapParuhWaktuPersenExport implements FromArray, WithEvents, WithTitle
{
    protected array $data;
    protected array $tanpaPw = [];
    protected int $headerRows = 5; // baris 1-3 judul, 4 kosong, 5 header kolom
    protected int $jumlahBaris;

    protected string $warnaBiru = 'DCE6F2';
    protected string $warnaOranye = 'FCD5B5';

    public function __construct(protected string $periode)
    {
        $service = new RekapParuhWaktuTambahanService();
        $this->data = $service->rekapPersentaseInstansi($periode);
        $this->tanpaPw = $service->rekapInstansiTanpaParuhWaktu($periode);

        // Minimal 1 baris supaya border/total tidak error saat belum ada data.
        $this->jumlahBaris = max(1, count($this->data));
    }

    public function title(): string
    {
        return $this->formatPeriode($this->periode);
    }

    public function array(): array
    {
        if (count($this->data) === 0) {
            return [[1, '(belum ada data PPPK paruh waktu)', 0, 0, 0]];
        }

        $rows = [];
        $no = 1;
        foreach ($this->data as $d) {
            $rows[] = [$no++, $d['instansi'], $d['paruh_waktu'], $d['total_pegawai'], $d['persen']];
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

                $sheet->setCellValue(
                    'A1',
                    'REKAPITULASI ASN PEMERINTAH DAERAH KAB/KOTA PEMERINTAH KOTA YOGYAKARTA'
                );
                $sheet->setCellValue(
                    'A2',
                    'DATA PPPK PARUH WAKTU, PERSENTASE TERHADAP SELURUH PEGAWAI PER PERANGKAT DAERAH'
                );
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
                $sheet->setCellValue('B5', 'Perangkat Daerah');
                $sheet->setCellValue('C5', 'PPPK Paruh Waktu');
                $sheet->setCellValue('D5', 'Total Pegawai');
                $sheet->setCellValue('E5', 'Persentase');

                $firstDataRow = $this->headerRows + 1;
                $lastDataRow = $this->headerRows + $this->jumlahBaris;
                $totalRow = $lastDataRow + 1;

                $sumPw = 0;
                $sumTotal = 0;
                for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                    $sumPw += (float) $sheet->getCell('C' . $r)->getValue();
                    $sumTotal += (float) $sheet->getCell('D' . $r)->getValue();
                }

                $sheet->setCellValue('A' . $totalRow, '');
                $sheet->setCellValue('B' . $totalRow, 'TOTAL');
                $sheet->setCellValue('C' . $totalRow, $sumPw);
                $sheet->setCellValue('D' . $totalRow, $sumTotal);
                $sheet->setCellValue(
                    'E' . $totalRow,
                    $sumTotal > 0 ? round($sumPw / $sumTotal * 100, 1) : 0
                );

                // Catatan di bawah tabel
                $fmt = fn ($n) => number_format((int) $n, 0, ',', '.');
                $totalTanpaPw = array_sum(array_column($this->tanpaPw, 'jumlah'));
                $totalSeluruh = $sumTotal + $totalTanpaPw;

                $catatan = [
                    'Catatan: Total Pegawai mencakup semua status (PNS, PPPK, PPPK Paruh Waktu, Lainnya) '
                    . 'pada perangkat daerah yang memiliki PPPK Paruh Waktu.',
                    $fmt($sumTotal) . ' adalah jumlah seluruh pegawai aktif (PNS, PPPK, PPPK Paruh Waktu, dan Lainnya) '
                    . 'yang bekerja di ' . $fmt(count($this->data)) . ' instansi yang punya PPPK Paruh Waktu. '
                    . 'Angka itu menjadi penyebut untuk menghitung persentase tiap instansi.',
                ];

                if ($totalTanpaPw > 0) {
                    $rinci = implode(', ', array_map(
                        fn ($r) => $r['instansi'] . ': ' . $fmt($r['jumlah']) . ' orang',
                        $this->tanpaPw
                    ));
                    $catatan[] = 'Angkanya bukan ' . $fmt($totalSeluruh) . ' karena ' . $fmt($totalTanpaPw)
                        . ' pegawai tidak ikut terhitung, yaitu pegawai di instansi yang tidak punya PPPK Paruh Waktu ('
                        . $rinci . '). ' . $fmt($sumTotal) . ' + ' . $fmt($totalTanpaPw) . ' = ' . $fmt($totalSeluruh) . '.';
                }

                $catatan[] = 'Persentase pada baris TOTAL (' . number_format($sumTotal > 0 ? $sumPw / $sumTotal * 100 : 0, 1, ',', '.')
                    . '%) dihitung dari ' . $fmt($sumPw) . ' dibagi ' . $fmt($sumTotal)
                    . ', bukan dibagi ' . $fmt($totalSeluruh) . '. Jika dibandingkan dengan seluruh pegawai ('
                    . $fmt($totalSeluruh) . ') hasilnya '
                    . number_format($totalSeluruh > 0 ? $sumPw / $totalSeluruh * 100 : 0, 1, ',', '.') . '%.';

                $catatanRow = $totalRow + 2;
                foreach ($catatan as $teks) {
                    $sheet->setCellValue('A' . $catatanRow, $teks);
                    $sheet->mergeCells("A{$catatanRow}:E{$catatanRow}");
                    $sheet->getStyle("A{$catatanRow}")->getFont()->setItalic(true)->setSize(9);
                    $sheet->getStyle("A{$catatanRow}")->getAlignment()->setWrapText(true)->setVertical('top');
                    $sheet->getRowDimension($catatanRow)->setRowHeight(44);
                    $catatanRow++;
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

                // Kolom Persentase (E): biru muda + bold + format persen
                $warnai("E{$firstDataRow}:E{$lastDataRow}", $this->warnaBiru);
                $sheet->getStyle("E{$firstDataRow}:E{$lastDataRow}")->getFont()->setBold(true);
                $sheet->getStyle("E{$firstDataRow}:E{$totalRow}")
                    ->getNumberFormat()->setFormatCode('0.0"%"');

                // Baris TOTAL
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getFont()->setBold(true);
                $warnai("A{$totalRow}:E{$totalRow}", $this->warnaOranye);
                $sheet->getStyle("C{$totalRow}:E{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Lebar kolom
                $sheet->getColumnDimension('A')->setWidth(8);
                $sheet->getColumnDimension('B')->setWidth(55);
                $sheet->getColumnDimension('C')->setWidth(14);
                $sheet->getColumnDimension('D')->setWidth(14);
                $sheet->getColumnDimension('E')->setWidth(14);

                $sheet->getStyle("B{$firstDataRow}:B{$lastDataRow}")->getAlignment()
                    ->setWrapText(true)
                    ->setVertical('top');
                $sheet->getStyle("C{$firstDataRow}:E{$lastDataRow}")->getAlignment()
                    ->setHorizontal('center');
            },
        ];
    }
}