<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title as ChartTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export Excel untuk halaman Home Dashboard (resources/js/pages/home/home.jsx).
 *
 * Tetap 1 SHEET, ditumpuk dari atas ke bawah, tapi tiap section dibuat
 * dengan bentuk yang paling pas untuk datanya:
 *
 * - Ringkasan (Total Pegawai, Struktural, JFU, JFT)  -> kartu KPI
 * - Generasi                                          -> kartu per generasi
 * - Golongan                                          -> grafik batang
 * - Pendidikan                                        -> grafik batang (Pria vs Wanita)
 * - Usia, Masa Kerja Pangkat, Agama, Unit Kerja        -> tetap tabel
 *   (rinciannya banyak baris & beberapa kolom, jadi tabel masih paling
 *   mudah dibaca dibanding kartu/grafik untuk data sebanyak itu)
 *
 * Catatan teknis: chart Excel WAJIB merujuk ke sel yang berisi angka, jadi
 * untuk Golongan & Pendidikan tetap ada tabel sumber data kecil di kolom
 * A-D, lalu grafiknya digambar TEPAT DI BAWAH tabel itu (bukan di samping
 * kanan) supaya seluruh laporan tetap satu alur baca dari atas ke bawah,
 * tanpa area kosong besar di kolom A-E yang isinya baru kelihatan kalau
 * scroll ke kanan. Tabel sumbernya sengaja tetap ditampilkan (bukan
 * disembunyikan) supaya pembaca yang butuh angka pastinya tidak perlu klik
 * grafik satu per satu.
 *
 * Sumber datanya sama persis dengan RekapController::dashboardJson(), yaitu
 * RekapService::rekapDashboard().
 */
class RekapDashboardExport implements Export, FromArray, WithCharts, WithEvents, WithTitle
{
    use Exportable;

    private const SECTION_FILL = 'FF1F3864';
    private const SECTION_TEXT = 'FFFFFFFF';
    private const CHART_HEIGHT_ROWS = 12;
    private const CARD_BODY_FILL = 'FFFFFFFF';

    /** Palet warna aksen kartu, dipakai bergantian (rotate) per kartu. */
    private const CARD_COLORS = [
        'FF4472C4', // biru
        'FF2E9E5B', // hijau
        'FFED7D31', // oranye
        'FFC00000', // merah
        'FF7030A0', // ungu
        'FF1F9AA0', // teal
    ];

    public function __construct(protected array $data, protected string $periode) {}

    /**
     * Data sebenarnya ditulis manual ke sheet lewat AfterSheet (lihat
     * registerEvents), karena layout laporan ini butuh kartu + tabel +
     * grafik dalam satu sheet. Method ini wajib ada untuk paket Excel-nya,
     * tapi sengaja dikosongkan.
     */
    public function array(): array
    {
        return [];
    }

    /**
     * Wajib diimplementasikan supaya writer Excel-nya menyalakan mode
     * "sertakan chart" saat menyimpan file (lihat Maatwebsite\Excel\
     * Factories\WriterFactory::includesCharts()). Chart-nya sendiri
     * ditambahkan langsung ke sheet di dalam AfterSheet (lihat
     * addBarChart()), sesudah data sumbernya ditulis, jadi di sini cukup
     * dikosongkan.
     */
    public function charts(): array
    {
        return [];
    }

    public function title(): string
    {
        return 'Ringkasan Dashboard';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheetTitle = $sheet->getTitle();
                $row = 1;

                // ==== Judul laporan ====
                $sheet->setCellValue("A{$row}", 'LAPORAN RINGKASAN DASHBOARD SIMASN');
                $sheet->mergeCells("A{$row}:E{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
                $row++;

                $sheet->setCellValue("A{$row}", 'Periode: ' . $this->periode);
                $sheet->mergeCells("A{$row}:E{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
                $row += 2;

                // ==== Kartu KPI Ringkasan ====
                // Tiap kartu sekarang menyertakan rincian Pria/Wanita (baris
                // kecil di bawah angka total) — sebelumnya cuma angka total
                // polos. JFT ditambah 3 kartu rincian (Pendidikan/Kesehatan/
                // Teknis) supaya sama seperti tampilan Home Dashboard.
                $cards = [
                    [
                        'label' => 'Total Pegawai',
                        'total' => $this->data['total']['total'] ?? 0,
                        'pria' => $this->data['total']['pria'] ?? 0,
                        'wanita' => $this->data['total']['wanita'] ?? 0,
                    ],
                    [
                        'label' => 'Jabatan Struktural',
                        'total' => $this->data['jabatan']['struktural']['total'] ?? 0,
                        'pria' => $this->data['jabatan']['struktural']['pria'] ?? 0,
                        'wanita' => $this->data['jabatan']['struktural']['wanita'] ?? 0,
                    ],
                    [
                        'label' => 'JFU',
                        'total' => $this->data['jabatan']['jfu']['total'] ?? 0,
                        'pria' => $this->data['jabatan']['jfu']['pria'] ?? 0,
                        'wanita' => $this->data['jabatan']['jfu']['wanita'] ?? 0,
                    ],
                    [
                        'label' => 'JFT',
                        'total' => $this->data['jabatan']['jft']['total'] ?? 0,
                        'pria' => $this->data['jabatan']['jft']['pria'] ?? 0,
                        'wanita' => $this->data['jabatan']['jft']['wanita'] ?? 0,
                    ],
                ];

                foreach ($this->data['jabatan']['jft_rincian'] ?? [] as $r) {
                    $labelRincian = match ($r['label']) {
                        'pendidikan' => 'JFT - Pendidikan',
                        'kesehatan' => 'JFT - Kesehatan',
                        'teknis' => 'JFT - Teknis',
                        default => 'JFT - ' . ucfirst($r['label']),
                    };

                    $cards[] = [
                        'label' => $labelRincian,
                        'total' => $r['total'] ?? 0,
                        'pria' => $r['pria'] ?? 0,
                        'wanita' => $r['wanita'] ?? 0,
                    ];
                }

                $row = $this->drawSectionHeader($sheet, $row, 'Ringkasan', count($cards));
                $row = $this->drawKpiCards($sheet, $row, $cards);
                $row++;

                // ==== Generasi: kartu per generasi ====
                if (!empty($this->data['generasi'])) {
                    // lebar header bar mengikuti JUMLAH kartu generasi yang
                    // sebenarnya (dulu di-hardcode 5 -> bisa tidak sinkron
                    // dengan jumlah kartu, jadi kelihatan berantakan)
                    $row = $this->drawSectionHeader($sheet, $row, 'Generasi', count($this->data['generasi']));
                    $row = $this->drawGenerasiCards($sheet, $row, $this->data['generasi']);
                    $row++;
                }

                // ==== Golongan: grafik batang ====
                $row = $this->drawChartTableSection(
                    $sheet,
                    $sheetTitle,
                    $row,
                    'Golongan',
                    ['Golongan', 'Jumlah'],
                    array_map(fn($r) => [$r['label'], $r['jumlah']], $this->data['golongan'] ?? []),
                    [1 => 'Jumlah'] // kolom B (index 1) jadi 1 series
                );

                // ==== Pendidikan: grafik batang Pria vs Wanita ====
                $row = $this->drawChartTableSection(
                    $sheet,
                    $sheetTitle,
                    $row,
                    'Pendidikan',
                    ['Pendidikan', 'Pria', 'Wanita', 'Total'],
                    array_map(fn($r) => [$r['label'], $r['pria'], $r['wanita'], $r['jumlah']], $this->data['pendidikan'] ?? []),
                    [1 => 'Pria', 2 => 'Wanita'] // kolom B & C jadi 2 series
                );

                // ==== Section tabel biasa ====
                if (!empty($this->data['usia'])) {
                    $row = $this->drawTableSection(
                        $sheet,
                        $row,
                        'Usia',
                        ['Usia', 'Pria', 'Wanita', 'Total'],
                        array_map(fn($r) => [$r['label'], $r['pria'], $r['wanita'], $r['total']], $this->data['usia'])
                    );
                }

                if (!empty($this->data['masa_kerja_pangkat'])) {
                    $row = $this->drawTableSection(
                        $sheet,
                        $row,
                        'Masa Kerja Pangkat',
                        ['Masa Kerja', 'Pria', 'Wanita', 'Total'],
                        array_map(fn($r) => [$r['label'], $r['pria'], $r['wanita'], $r['total']], $this->data['masa_kerja_pangkat'])
                    );
                }

                if (!empty($this->data['agama'])) {
                    $row = $this->drawTableSection(
                        $sheet,
                        $row,
                        'Agama',
                        ['Agama', 'Pria', 'Wanita', 'Total'],
                        array_map(fn($r) => [$r['label'], $r['pria'], $r['wanita'], $r['total']], $this->data['agama'])
                    );
                }

                if (!empty($this->data['unit_kerja'])) {
                    $rows = [];
                    $no = 1;
                    foreach ($this->data['unit_kerja'] as $r) {
                        $rows[] = [$no++, $r['label'], $r['pria'], $r['wanita'], $r['total']];
                    }
                    $row = $this->drawTableSection(
                        $sheet,
                        $row,
                        'Unit Kerja',
                        ['No', 'Unit Kerja', 'Pria', 'Wanita', 'Total'],
                        $rows,
                        wrapColIndex: 1 // nama unit kerja bisa panjang, dibungkus (wrap) bukan melebarkan kolom
                    );
                }

                // Lebar kolom dibuat TETAP (bukan auto-size dari seluruh isi
                // sheet) supaya kartu KPI/Generasi tampil sebagai kotak yang
                // proporsional & seragam, tidak ikut melebar mengikuti teks
                // terpanjang di tabel-tabel di bawahnya. Rentangnya sampai
                // kolom G (bukan E) karena kartu Ringkasan sekarang ada 7
                // (Total, Struktural, JFU, JFT, JFT-Pendidikan,
                // JFT-Kesehatan, JFT-Teknis).
                foreach (range('A', 'G') as $col) {
                    $sheet->getColumnDimension($col)->setWidth(18);
                }
            },
        ];
    }

    /**
     * Header section: baris judul dengan background biru tua, teks putih,
     * dipakai di depan Generasi (Golongan & Pendidikan sudah punya header
     * sendiri lewat drawChartTableSection).
     */
    private function drawSectionHeader($sheet, int $startRow, string $title, int $spanCols): int
    {
        $lastCol = Coordinate::stringFromColumnIndex($spanCols);
        $sheet->setCellValue("A{$startRow}", $title);
        $sheet->mergeCells("A{$startRow}:{$lastCol}{$startRow}");
        $sheet->getStyle("A{$startRow}")->getFont()->setBold(true)->getColor()->setARGB(self::SECTION_TEXT);
        $sheet->getStyle("A{$startRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::SECTION_FILL);

        return $startRow + 1;
    }

    /**
     * Gambar baris kartu KPI ala dashboard admin: tiap kartu = 1 kolom
     * lebar x 2 baris tinggi, dengan HEADER berwarna solid (label, teks
     * putih) dan BODY putih di bawahnya (angka besar) — bukan cuma sel
     * tabel yang diberi warna latar. Tiap kartu pakai warna aksen
     * berbeda (bergantian dari self::CARD_COLORS), dan seluruh kartu
     * diberi border warna aksen yang sama supaya terlihat sebagai satu
     * kotak yang menyatu.
     *
     * @return int baris berikutnya yang masih kosong setelah kartu
     */
    private function drawKpiCards($sheet, int $startRow, array $cards): int
    {
        $headerRow = $startRow;
        $valueRow = $startRow + 1;
        $subRow = $startRow + 2;
        $col = 'A';

        foreach ($cards as $i => $card) {
            $accent = self::CARD_COLORS[$i % count(self::CARD_COLORS)];
            $headerCell = "{$col}{$headerRow}";
            $valueCell = "{$col}{$valueRow}";
            $subCell = "{$col}{$subRow}";
            // Baris rincian L/P cuma digambar kalau kartunya memang bawa
            // data pria/wanita (semua kartu Ringkasan sekarang begitu,
            // tapi dijaga tetap opsional supaya pemanggil lama yang belum
            // sempat dilengkapi gender tidak error).
            $adaGender = array_key_exists('pria', $card) && array_key_exists('wanita', $card);
            $range = $adaGender
                ? "{$col}{$headerRow}:{$col}{$subRow}"
                : "{$col}{$headerRow}:{$col}{$valueRow}";

            $sheet->setCellValue($headerCell, $card['label']);
            $sheet->setCellValue($valueCell, $card['total']);
            if ($adaGender) {
                $sheet->setCellValue($subCell, 'L: ' . $card['pria'] . '  P: ' . $card['wanita']);
            }

            // Header: strip warna solid, teks putih bold, rata tengah
            $sheet->getStyle($headerCell)->getFont()->setBold(true)->setSize(9)
                ->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($headerCell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle($headerCell)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($accent);

            // Body: putih, angka besar bold warna aksen
            $sheet->getStyle($valueCell)->getFont()->setBold(true)->setSize(20)
                ->getColor()->setARGB($accent);
            $sheet->getStyle($valueCell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle($valueCell)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CARD_BODY_FILL);

            if ($adaGender) {
                // Baris kecil "L: x  P: y" di bawah angka, gaya sama
                // seperti kartu Generasi (drawGenerasiCards).
                $sheet->getStyle($subCell)->getFont()->setSize(8)->getColor()->setARGB('FF808080');
                $sheet->getStyle($subCell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($subCell)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF7F7F7');

                $sheet->getRowDimension($subRow)->setRowHeight(14);
            }

            // Border kotak mengelilingi seluruh kartu, warna senada aksen
            $sheet->getStyle($range)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB($accent);

            $sheet->getRowDimension($headerRow)->setRowHeight(20);
            $sheet->getRowDimension($valueRow)->setRowHeight(34);

            $col++;
        }

        return ($cards && array_key_exists('pria', $cards[0]) && array_key_exists('wanita', $cards[0]))
            ? $subRow + 1
            : $valueRow + 1;
    }

    /**
     * Gambar kartu per generasi ala dashboard admin: header berwarna
     * (nama generasi), body putih (angka total besar), dan baris kecil
     * "L: x  P: y" di bawahnya dengan latar abu muda. 1 kartu = 1 kolom
     * x 3 baris, warna aksen bergantian dari self::CARD_COLORS.
     *
     * @return int baris berikutnya yang masih kosong setelah kartu
     */
    private function drawGenerasiCards($sheet, int $startRow, array $rows): int
    {
        $headerRow = $startRow;
        $valueRow = $startRow + 1;
        $subRow = $startRow + 2;
        $col = 'A';

        foreach ($rows as $i => $r) {
            $accent = self::CARD_COLORS[$i % count(self::CARD_COLORS)];
            $headerCell = "{$col}{$headerRow}";
            $valueCell = "{$col}{$valueRow}";
            $subCell = "{$col}{$subRow}";
            $range = "{$col}{$headerRow}:{$col}{$subRow}";

            $sheet->setCellValue($headerCell, $r['label']);
            $sheet->setCellValue($valueCell, $r['total']);
            $sheet->setCellValue($subCell, 'L: ' . $r['pria'] . '  P: ' . $r['wanita']);

            $sheet->getStyle($headerCell)->getFont()->setBold(true)->setSize(9)
                ->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($headerCell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle($headerCell)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($accent);

            $sheet->getStyle($valueCell)->getFont()->setBold(true)->setSize(16)
                ->getColor()->setARGB($accent);
            $sheet->getStyle($valueCell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($valueCell)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::CARD_BODY_FILL);

            $sheet->getStyle($subCell)->getFont()->setSize(8)->getColor()->setARGB('FF808080');
            $sheet->getStyle($subCell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($subCell)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF7F7F7');

            $sheet->getStyle($range)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB($accent);

            $sheet->getRowDimension($headerRow)->setRowHeight(18);
            $sheet->getRowDimension($valueRow)->setRowHeight(28);
            $sheet->getRowDimension($subRow)->setRowHeight(14);

            $col++;
        }

        return $subRow + 1;
    }

    /**
     * Gambar satu section tabel biasa: header section (background biru tua,
     * teks putih), heading kolom bold, lalu baris data, semuanya diberi
     * border tipis. Dipakai untuk section yang tetap berbentuk tabel
     * (Usia, Masa Kerja Pangkat, Agama, Unit Kerja).
     *
     * @param int|null $wrapColIndex index kolom (0 = A) yang teksnya perlu
     *                                dibungkus (wrap) alih-alih melebarkan
     *                                kolom — dipakai untuk nama Unit Kerja
     *                                yang bisa panjang, supaya lebar kolom
     *                                tetap seragam dengan kartu di atasnya
     * @return int baris berikutnya yang masih kosong setelah section ini
     */
    private function drawTableSection(
        $sheet,
        int $startRow,
        string $title,
        array $headings,
        array $rows,
        ?int $wrapColIndex = null
    ): int {
        $lastCol = Coordinate::stringFromColumnIndex(count($headings));

        $sheet->setCellValue("A{$startRow}", $title);
        $sheet->mergeCells("A{$startRow}:{$lastCol}{$startRow}");
        $sheet->getStyle("A{$startRow}")->getFont()->setBold(true)->getColor()->setARGB(self::SECTION_TEXT);
        $sheet->getStyle("A{$startRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::SECTION_FILL);
        $startRow++;

        $headerRow = $startRow;
        foreach ($headings as $i => $label) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFont()->setBold(true);
        $startRow++;

        foreach ($rows as $rowData) {
            foreach ($rowData as $i => $value) {
                $col = Coordinate::stringFromColumnIndex($i + 1);
                $sheet->setCellValue("{$col}{$startRow}", $value);
            }
            $startRow++;
        }

        $lastDataRow = $startRow - 1;
        if ($lastDataRow >= $headerRow) {
            $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastDataRow}")
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            if ($wrapColIndex !== null) {
                $wrapCol = Coordinate::stringFromColumnIndex($wrapColIndex + 1);
                $sheet->getStyle("{$wrapCol}{$headerRow}:{$wrapCol}{$lastDataRow}")
                    ->getAlignment()->setWrapText(true);
                for ($r = $headerRow; $r <= $lastDataRow; $r++) {
                    $sheet->getRowDimension($r)->setRowHeight(30);
                }
            }
        }

        return $startRow + 1;
    }

    /**
     * Gambar section yang punya tabel sumber data kecil (kolom A-D) DIIKUTI
     * grafik batang TEPAT DI BAWAHNYA (bukan di samping kanan) yang
     * datanya diambil dari tabel tersebut. Dipakai untuk Golongan &
     * Pendidikan.
     *
     * Grafik sengaja diletakkan di bawah (satu alur vertikal dengan
     * section lain), bukan di kolom sebelah kanan — supaya seluruh
     * laporan tetap satu urutan baca dari atas ke bawah tanpa ada area
     * kosong besar di kolom A-E yang isinya baru kelihatan kalau scroll
     * ke kanan.
     *
     * @param array<int,string> $seriesCols index kolom data (0 = kolom A) => nama series,
     *                                       contoh [1 => 'Jumlah'] artinya kolom B jadi 1 series
     *                                       bernama sesuai heading kolom B
     * @return int baris berikutnya yang masih kosong (sudah memperhitungkan tinggi grafik)
     */
    private function drawChartTableSection(
        $sheet,
        string $sheetTitle,
        int $startRow,
        string $title,
        array $headings,
        array $rows,
        array $seriesCols
    ): int {
        $lastCol = Coordinate::stringFromColumnIndex(count($headings));

        $sheet->setCellValue("A{$startRow}", $title);
        $sheet->mergeCells("A{$startRow}:{$lastCol}{$startRow}");
        $sheet->getStyle("A{$startRow}")->getFont()->setBold(true)->getColor()->setARGB(self::SECTION_TEXT);
        $sheet->getStyle("A{$startRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::SECTION_FILL);
        $startRow++;

        $headerRow = $startRow;
        foreach ($headings as $i => $label) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->getFont()->setBold(true);
        $startRow++;

        $dataStartRow = $startRow;
        foreach ($rows as $rowData) {
            foreach ($rowData as $i => $value) {
                $col = Coordinate::stringFromColumnIndex($i + 1);
                $sheet->setCellValue("{$col}{$startRow}", $value);
            }
            $startRow++;
        }
        $dataEndRow = $startRow - 1;

        if ($dataEndRow >= $headerRow) {
            $sheet->getStyle("A{$headerRow}:{$lastCol}{$dataEndRow}")
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        $nextRow = $dataEndRow + 1;

        if ($dataEndRow >= $dataStartRow) {
            $series = [];
            foreach ($seriesCols as $colIndex => $seriesName) {
                $col = Coordinate::stringFromColumnIndex($colIndex + 1);
                $series[] = [
                    'labelRef' => "'{$sheetTitle}'!\${$col}\${$headerRow}",
                    'valueRange' => "'{$sheetTitle}'!\${$col}\${$dataStartRow}:\${$col}\${$dataEndRow}",
                ];
            }

            $categoryRange = "'{$sheetTitle}'!\$A\${$dataStartRow}:\$A\${$dataEndRow}";
            $categoryCount = $dataEndRow - $dataStartRow + 1;

            // 1 baris jarak antara tabel & grafik, grafik selebar A-F
            $chartTopRow = $nextRow + 1;
            $chartBottomRow = $chartTopRow + self::CHART_HEIGHT_ROWS;

            $this->addBarChart(
                $sheet,
                'chart_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($title)) . '_' . $headerRow,
                $title,
                $categoryRange,
                $categoryCount,
                $series,
                'A' . $chartTopRow,
                'F' . $chartBottomRow
            );

            $nextRow = $chartBottomRow;
        }

        return $nextRow + 2;
    }

    /**
     * Bikin & pasang 1 grafik batang ke sheet, merujuk ke sel-sel yang
     * sudah ditulis sebelumnya (bukan data statis) supaya kalau ada yang
     * buka & edit file-nya, grafik ikut menyesuaikan.
     *
     * @param array<int,array{labelRef:string,valueRange:string}> $series
     */
    private function addBarChart(
        $sheet,
        string $chartId,
        string $title,
        string $categoryRange,
        int $categoryCount,
        array $series,
        string $topLeftCell,
        string $bottomRightCell
    ): void {
        $categoryLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $categoryRange, null, $categoryCount),
        ];

        $seriesLabels = [];
        $seriesValues = [];
        foreach ($series as $s) {
            $seriesLabels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $s['labelRef'], null, 1);
            $seriesValues[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $s['valueRange'], null, $categoryCount);
        }

        $dataSeries = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            range(0, count($seriesValues) - 1),
            $seriesLabels,
            $categoryLabels,
            $seriesValues
        );
        $dataSeries->setPlotDirection(DataSeries::DIRECTION_COL);

        $plotArea = new PlotArea(null, [$dataSeries]);
        $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
        $chartTitle = new ChartTitle($title);

        $chart = new Chart($chartId, $chartTitle, $legend, $plotArea);
        $chart->setTopLeftPosition($topLeftCell);
        $chart->setBottomRightPosition($bottomRightCell);

        $sheet->addChart($chart);
    }
}
