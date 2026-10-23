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

    /** Baris catatan di atas dan baris penjelasan tiap tabel (huruf miring abu-abu). */
    private int $catatan = 0;
    private array $ket = [];

    /** Rentang melewati pergantian tahun: label bulan wajib memuat tahun. */
    private bool $lintasTahun;

    /**
     * @param  string[]  $periode  daftar YYYY-MM berurutan
     * @param  array  $tabel  ['label', 'kategori' => [['nama','nilai'=>[]]], 'total' => []]
     * @param  int|null  $dasar  urutan periode dasar (0 = periode pertama); null = bulan pertama yang punya data
     */
    public function __construct(
        private string $tahun,
        private array $periode,
        private array $tabel,
        private ?int $dasar = null
    ) {
        $this->jumlahKolom = 1 + count($periode);
        $this->lintasTahun = count(array_unique(array_map(fn ($p) => substr($p, 0, 4), $periode))) > 1;
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

    /** Nama bulan; ditambah tahun kalau rentangnya lintas tahun. */
    private function labelBulan(string $periode): string
    {
        return $this->lintasTahun
            ? $this->namaBulan($periode) . ' ' . substr($periode, 0, 4)
            : $this->namaBulan($periode);
    }

    private function susunBaris(): void
    {
        $idxAwal = $this->dasar ?? 0;
        if ($this->dasar === null) {
            foreach ($this->tabel['total'] as $i => $v) {
                if ($v !== null) {
                    $idxAwal = $i;
                    break;
                }
            }
        }
        $awal = $this->labelBulan($this->periode[$idxAwal] ?? "{$this->tahun}-01");
        $akhir = $this->namaBulan(end($this->periode));

        // Siapa yang dihitung: ASN aktif (PNS + PPPK), kecuali dimensi Status
        // Kepegawaian yang ikut menghitung PPPK Paruh Waktu.
        $sebutan = $this->tabel['cakupan']['sebutan'] ?? IndeksKumulatifService::CAKUPAN_ASN['sebutan'];
        $rincian = $this->tabel['cakupan']['rincian'] ?? IndeksKumulatifService::CAKUPAN_ASN['rincian'];

        $this->tambah(['INDEKS KUMULATIF - ' . strtoupper($this->tabel['label'])]);
        $this->tambah($this->lintasTahun
            ? ["Jumlah {$sebutan} ({$rincian}), {$this->labelBulan($this->periode[0])} s.d. {$this->labelBulan(end($this->periode))}"]
            : ["Jumlah {$sebutan} ({$rincian}), {$this->namaBulan($this->periode[0])} s.d. {$akhir} {$this->tahun}"]);
        $this->tambah(['Cara membaca: lihat sheet Keterangan. Tanda "-" berarti data belum ada (bukan 0).']);
        $this->catatan = count($this->rows);
        $this->tambah([]);

        $def = [
            ['jumlah', '1. JUMLAH ' . strtoupper($sebutan) . ' PER BULAN', '#,##0',
                "Banyaknya {$sebutan} pada tiap bulan."],
            ['selisih', '2. SELISIH PER BULAN (dibanding bulan sebelumnya)', '[Color10]+#,##0;[Red]-#,##0;0',
                'Jumlah bulan ini dikurangi bulan sebelumnya. Hijau (+) bertambah, merah (-) berkurang.'],
            ['kumulatif', "3. SELISIH KUMULATIF (dibanding {$awal})", '[Color10]+#,##0;[Red]-#,##0;0',
                "Jumlah bulan ini dikurangi jumlah {$awal}. Menunjukkan total perubahan sejak awal."],
            ['indeks', "4. INDEKS KUMULATIF ({$awal} = 100)", '0.0',
                "Jumlah bulan ini dibagi jumlah {$awal}, dikali 100. Di atas 100 = lebih banyak dari awal, di bawah 100 = lebih sedikit (110 = naik 10%, 90 = turun 10%)."],
        ];

        $header = array_merge([$this->tabel['label']], array_map(
            fn ($p) => mb_substr($this->namaBulan($p), 0, 3) . ($this->lintasTahun ? ' ' . substr($p, 0, 4) : ''),
            $this->periode
        ));

        foreach ($def as [$mode, $judul, $format, $penjelasan]) {
            $baris = count($this->rows) + 1;
            $this->tambah([$judul]);
            $this->tambah([$penjelasan]);
            $this->ket[] = count($this->rows);
            $this->tambah($header);

            $awalData = count($this->rows) + 1;
            foreach ($this->tabel['kategori'] as $k) {
                $this->tambah(array_merge(
                    [$k['nama']],
                    array_map($this->sel(...), IndeksKumulatifService::hitungDeret($k['nilai'], $mode, $this->dasar))
                ));
            }
            $akhirData = count($this->rows);

            $this->tambah(array_merge(
                ['Total'],
                array_map($this->sel(...), IndeksKumulatifService::hitungDeret($this->tabel['total'], $mode, $this->dasar))
            ));

            $this->blok[] = [
                'judul' => $baris,
                'header' => $baris + 2,
                'awal' => $awalData,
                'akhir' => $akhirData,
                'total' => count($this->rows),
                'format' => $format,
            ];

            $this->tambah([]);
        }
    }

    /** Bulan tanpa data / tidak bisa dihitung ditulis "-" (BUKAN 0). */
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

                // Catatan dan penjelasan tiap tabel: miring, abu-abu, tanpa digabung
                // (teksnya meluber ke kanan melewati kolom kosong di sebelahnya).
                foreach (array_filter(array_merge([$this->catatan], $this->ket)) as $r) {
                    $sheet->getStyle("A{$r}")->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('595959');
                }

                // Kolom nama kategori tetap terlihat saat digeser ke kanan.
                $sheet->freezePane('B1');

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