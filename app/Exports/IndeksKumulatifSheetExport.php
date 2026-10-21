<?php

namespace App\Exports;

use App\Exports\Concerns\PrintsConsistently;
use App\Services\IndeksKumulatifService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Satu sheet = satu dimensi, berisi empat blok tabel berurutan:
 * Jumlah, Selisih per Bulan, Selisih Kumulatif, dan Indeks.
 */
class IndeksKumulatifSheetExport implements FromArray, WithTitle, WithEvents
{
    use PrintsConsistently;

    public const BULAN = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    ];

    private array $rows = [];

    /** Posisi tiap blok (nomor baris Excel, mulai 1) untuk styling. */
    private array $blok = [];

    private int $jumlahKolom;

    /**
     * @param  string[]  $periode  daftar YYYY-MM, Januari s.d. bulan terakhir
     * @param  array  $tabel  ['label', 'kategori' => [['nama','nilai'=>[]]], 'total' => []]
     */
    public function __construct(
        private string $tahun,
        private array $periode,
        private array $tabel
    ) {
        $this->jumlahKolom = 1 + count($periode);
        $this->susunBaris();
    }

    public function title(): string
    {
        // Batas nama sheet Excel: 31 karakter.
        return mb_substr(str_replace(['/', '\\', '?', '*', '[', ']', ':'], '', $this->tabel['label']), 0, 31);
    }

    public function array(): array
    {
        return $this->rows;
    }

    private function namaBulan(string $periode): string
    {
        return self::BULAN[substr($periode, 5, 2)] ?? $periode;
    }

    private function susunBaris(): void
    {
        $idxAwal = 0;
        foreach ($this->tabel['total'] as $i => $v) {
            if ($v !== null) {
                $idxAwal = $i;
                break;
            }
        }
        $awal = $this->namaBulan($this->periode[$idxAwal] ?? "{$this->tahun}-01");
        $akhir = $this->namaBulan(end($this->periode));

        $this->tambah(['INDEKS KUMULATIF - ' . strtoupper($this->tabel['label'])]);
        $this->tambah(["Jumlah ASN aktif (PNS + PPPK), {$this->namaBulan($this->periode[0])} s.d. {$akhir} {$this->tahun}"]);
        $this->tambah([]);

        $def = [
            ['jumlah', '1. JUMLAH ASN AKTIF PER BULAN', '#,##0'],
            ['selisih', '2. SELISIH PER BULAN (dibanding bulan sebelumnya)', '+#,##0;-#,##0;0'],
            ['kumulatif', "3. SELISIH KUMULATIF (dibanding {$awal})", '+#,##0;-#,##0;0'],
            ['indeks', "4. INDEKS KUMULATIF ({$awal} = 100)", '0.0'],
        ];

        $header = array_merge([$this->tabel['label']], array_map(
            fn ($p) => mb_substr($this->namaBulan($p), 0, 3),
            $this->periode
        ));

        foreach ($def as [$mode, $judul, $format]) {
            $baris = count($this->rows) + 1;
            $this->tambah([$judul]);
            $this->tambah($header);

            $awalData = count($this->rows) + 1;
            foreach ($this->tabel['kategori'] as $k) {
                $this->tambah(array_merge(
                    [$k['nama']],
                    array_map($this->sel(...), IndeksKumulatifService::hitungDeret($k['nilai'], $mode))
                ));
            }
            $akhirData = count($this->rows);

            $this->tambah(array_merge(
                ['Total'],
                array_map($this->sel(...), IndeksKumulatifService::hitungDeret($this->tabel['total'], $mode))
            ));

            $this->blok[] = [
                'judul' => $baris,
                'header' => $baris + 1,
                'awal' => $awalData,
                'akhir' => $akhirData,
                'total' => count($this->rows),
                'format' => $format,
            ];

            $this->tambah([]);
        }
    }

    /** Bulan tanpa data / tidak bisa dihitung ditulis "-". */
    private function sel(int|float|null $nilai): int|float|string
    {
        return $nilai ?? '-';
    }

    private function tambah(array $baris): void
    {
        $this->rows[] = $baris;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = Coordinate::stringFromColumnIndex($this->jumlahKolom);
                $lastRow = count($this->rows);
                $thin = Border::BORDER_THIN;

                $sheet->getColumnDimension('A')->setWidth(30);
                for ($c = 2; $c <= $this->jumlahKolom; $c++) {
                    $sheet->getColumnDimensionByColumn($c)->setWidth(12);
                }

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A2')->getFont()->setItalic(true);

                foreach ($this->blok as $b) {
                    $sheet->getStyle("A{$b['judul']}")->getFont()->setBold(true);

                    // Header kolom
                    $head = "A{$b['header']}:{$lastCol}{$b['header']}";
                    $sheet->getStyle($head)->getFont()->setBold(true);
                    $sheet->getStyle($head)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCE6F2');
                    $sheet->getStyle($head)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);

                    // Isi + total: border, angka rata kanan, format sesuai blok
                    $isi = "A{$b['header']}:{$lastCol}{$b['total']}";
                    $sheet->getStyle($isi)->getBorders()->getAllBorders()->setBorderStyle($thin);

                    $angka = "B{$b['awal']}:{$lastCol}{$b['total']}";
                    $sheet->getStyle($angka)->getNumberFormat()->setFormatCode($b['format']);
                    $sheet->getStyle($angka)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // Baris total
                    $tot = "A{$b['total']}:{$lastCol}{$b['total']}";
                    $sheet->getStyle($tot)->getFont()->setBold(true);
                    $sheet->getStyle($tot)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCD5B5');
                }

                $this->applyPrintSetup($sheet, "A1:{$lastCol}{$lastRow}", 0);
            },
        ];
    }
}