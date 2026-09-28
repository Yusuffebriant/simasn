<?php

namespace App\Exports;

use App\Exports\Concerns\PrintsConsistently;
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
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title as ChartTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapDashboardExport implements Export, FromArray, WithCharts, WithEvents, WithTitle
{
    use Exportable;
    use PrintsConsistently;

    private const SECTION_FILL = 'FF006A4E';
    private const SECTION_TEXT = 'FFFFFFFF';
    private const CHART_HEIGHT_ROWS = 12;
    private const CARD_BODY_FILL = 'FFFFFFFF';

    private const CHART_SECTION_GAP_ROWS = 2;

    private const CHART_GAP_COLS = 1;
    private const SPLIT_CHART_WIDTH_COLS = 5;

    private const CARD_COLORS = [
        'FF006A4E',
        'FFD4A017',
        'FF3A5A78',
        'FFB42318',
        'FF0F6E6E',
        'FF172033',
    ];

    private const CHART_COLOR_PRIMARY = '006A4E';
    private const CHART_COLOR_LAKI = 'E57373';
    private const CHART_COLOR_PEREMPUAN = '64B5F6';
    /*
     * Aksen kuning/gold, sama seperti yang dipakai pada
     * CARD_COLORS — dipakai khusus untuk chart Pendidikan
     * agar warnanya senada dengan tema tabel/dashboard.
     */
    private const CHART_COLOR_ACCENT = 'D4A017';

    private const IKHTISAR_CARD_FILL = 'FFFBF6EC';
    private const IKHTISAR_LABEL_TEXT = 'FF5D6B59';
    private const IKHTISAR_LABEL_BORDER = 'FFD8D2C4';
    private const IKHTISAR_VALUE_TEXT = 'FF0D5C3A';
    private const IKHTISAR_VALUE_BORDER = 'FFBFBFBF';

    /*
     * Warna kotak judul "Badan Kepegawaian dan Pengembangan
     * Sumber Daya Manusia (BKPSDM)" pada kop surat — dipakai
     * ulang untuk judul kartu Total Pegawai / Jabatan
     * Struktural / JFU / JFT / rincian JFT.
     */
    private const CARD_TITLE_FILL = 'FFF0D79A';
    private const CARD_TITLE_TEXT = 'FF23301F';

    private const CARD_BORDER = 'FFBFBFBF';

    /*
     * Kolom terakhir laporan (dipakai untuk judul "LAPORAN
     * RINGKASAN SIMASN" pada kop surat, lihat drawLetterhead).
     * Section-section isi laporan (Data Pegawai, Generasi,
     * Golongan, Pendidikan, Usia, Agama, Unit Kerja) TIDAK lagi
     * ikut selebar ini — lihat SECTION_TITLE_LAST_COL/
     * SECTION_CONTENT_LAST_COL di bawah.
     */
    private const REPORT_LAST_COL = 'M';

    /*
     * Lebar SELURUH section (Data Pegawai, Generasi, Golongan,
     * Pendidikan, Usia, Agama, Unit Kerja) sekarang disamakan
     * lewat 2 patokan kolom tetap ini, bukan lagi rasio dari
     * REPORT_LAST_COL:
     * - Banner judul (pita hijau nama section) SELALU melebar
     *   sampai SECTION_TITLE_LAST_COL (kolom K), di semua section.
     * - Isi section (kartu Data Pegawai/Generasi, chart Golongan/
     *   Pendidikan, tabel Usia/Agama/Unit Kerja) melebar sampai
     *   SECTION_CONTENT_LAST_COL (kolom H).
     */
    private const SECTION_TITLE_LAST_COL = 'K';
    private const SECTION_CONTENT_LAST_COL = 'H';

    public function __construct(
        protected array $data,
        protected string $periode
    ) {}

    public function array(): array
    {
        return [];
    }

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
                $row = 1;

                /*
                 * Sheet tersembunyi untuk sumber data grafik.
                 */
                $dataSheet = $sheet->getParentOrThrow()->createSheet();
                $dataSheet->setTitle('Data Grafik (jangan diubah)');
                $dataSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

                $dataSheetTitle = $dataSheet->getTitle();
                $dataRow = 1;

                /*
                 * Kop laporan
                 */
                $row = $this->drawLetterhead(
                    $sheet,
                    $row,
                    self::REPORT_LAST_COL
                );

                /*
                 * =========================================================
                 * A. DATA PEGAWAI
                 * =========================================================
                 */

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

                foreach (
                    $this->data['jabatan']['jft_rincian'] ?? []
                    as $r
                ) {
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

                $row = $this->drawSectionHeader(
                    $sheet,
                    $row,
                    'A. Data Pegawai',
                    Coordinate::columnIndexFromString(
                        self::SECTION_TITLE_LAST_COL
                    )
                );

                $row = $this->drawEmployeeDataTable(
                    $sheet,
                    $row,
                    $cards
                );

                /*
                 * 2 baris kosong (bukan 1) antara Data Pegawai &
                 * Generasi, atas permintaan supaya jaraknya lebih
                 * lega dibanding jarak antar-section lain.
                 */
                $row += 2;

                /*
                 * =========================================================
                 * GENERASI
                 * =========================================================
                 */

                if (!empty($this->data['generasi'])) {
                    $row = $this->drawSectionHeader(
                        $sheet,
                        $row,
                        'B. Generasi',
                        Coordinate::columnIndexFromString(
                            self::SECTION_TITLE_LAST_COL
                        )
                    );

                    $row = $this->drawGenerasiCards(
                        $sheet,
                        $row,
                        $this->data['generasi']
                    );

                    $row++;
                }

                /*
                 * =========================================================
                 * GOLONGAN
                 * =========================================================
                 */

                /*
                 * Jarak 1-2 baris kosong sebelum judul section,
                 * supaya section sebelumnya (Generasi) tidak
                 * terlalu rapat dengan judul "C. Golongan".
                 */
                $row += 1;

                $row = $this->drawChartOnlySection(
                    $sheet,
                    $dataSheet,
                    $dataSheetTitle,
                    $dataRow,
                    $row,
                    'C. Golongan',
                    ['Golongan', 'Jumlah'],
                    array_map(
                        fn($r) => [
                            $this->golonganLabel($r['label']),
                            $r['jumlah']
                        ],
                        $this->data['golongan'] ?? []
                    ),
                    [1 => 'Jumlah'],
                    seriesColors: [
                        self::CHART_COLOR_PRIMARY
                    ],
                    /*
                     * Digeser 1 kolom ke kanan (B-H), sama
                     * seperti grafik Pendidikan, supaya posisi
                     * kedua chart konsisten satu sama lain.
                     * Border kiri/kanan chart otomatis ikut
                     * ditambahkan oleh drawChartOnlySection
                     * begitu $chartLeftCol bukan 'A'. Batas kanan
                     * (chartRightCol) memakai SECTION_CONTENT_LAST_COL
                     * (kolom H) supaya lebar chart-nya sama dengan
                     * isi section lain.
                     */
                    chartLeftCol: 'B',
                    chartRightCol: self::SECTION_CONTENT_LAST_COL
                );

                /*
                 * =========================================================
                 * PENDIDIKAN
                 * =========================================================
                 */

                /*
                 * Jarak 1-2 baris kosong sebelum judul "E. Pendidikan".
                 */
                $row += 1;

                /*
                 * Chart Pendidikan cukup tinggi (34 baris), jadi
                 * kalau dibiarkan Excel sering memotongnya di
                 * tengah lewat page break otomatis (garis putus-
                 * putus melintang di tengah chart saat print/
                 * print preview). Dipaksa mulai di halaman baru
                 * supaya seluruh section Pendidikan utuh dalam
                 * satu halaman, tidak terpotong.
                 */
                $sheet->setBreak(
                    "A{$row}",
                    Worksheet::BREAK_ROW
                );

                $row = $this->drawChartOnlySection(
                    $sheet,
                    $dataSheet,
                    $dataSheetTitle,
                    $dataRow,
                    $row,
                    'E. Pendidikan',
                    [
                        'Pendidikan',
                        'Pria',
                        'Wanita',
                        'Total'
                    ],
                    array_map(
                        fn($r) => [
                            /*
                             * Kategori pendidikan (SD, SLTP, SLTA, dst)
                             * diberi sedikit spasi di kanan-kiri supaya
                             * label sumbu-X tidak berhimpitan satu sama
                             * lain saat dirender di chart Excel (label
                             * jadi "renggang tipis", bukan menempel jadi
                             * satu). Jangan diberi terlalu banyak spasi
                             * karena bisa membuat label malah tidak
                             * center lagi terhadap bar-nya.
                             */
                            ' ' . $r['label'] . ' ',
                            $r['pria'],
                            $r['wanita'],
                            $r['jumlah']
                        ],
                        /*
                         * Data 'pendidikan' aslinya terurut SD -> S3
                         * (ascending). Tapi pada bar chart horizontal
                         * Excel, kategori pertama dalam data selalu
                         * digambar di BAWAH dan kategori terakhir di
                         * ATAS. Supaya SD tampil paling ATAS sesuai
                         * permintaan, urutan datanya dibalik
                         * (array_reverse) khusus untuk chart ini saja
                         * — tabel/rekap lain yang memakai
                         * $this->data['pendidikan'] tidak terpengaruh.
                         */
                        array_reverse(
                            $this->data['pendidikan'] ?? []
                        )
                    ),
                    [
                        1 => 'Pria',
                        2 => 'Wanita',
                    ],
                    seriesColors: [
                        /*
                         * Warna chart Pendidikan disesuaikan dengan
                         * tema warna tabel/dashboard (hijau utama &
                         * kuning/gold aksen pada CARD_COLORS/SECTION_FILL),
                         * bukan lagi merah/biru generik pria-wanita.
                         */
                        self::CHART_COLOR_PRIMARY,
                        self::CHART_COLOR_ACCENT
                    ],
                    /*
                     * Chart Pendidikan direbahkan (horizontal) dan
                     * bar Pria & Wanita dirapatkan lagi (spacer
                     * dimatikan) — tapi tetap 1 chart yang sama,
                     * tidak dipecah jadi dua.
                     */
                    addSeriesSpacer: false,
                    horizontal: true,
                    /*
                     * Chart Pendidikan digeser 1 kolom ke kanan
                     * (B-G, bukan lagi A-F) supaya tidak lagi
                     * mepet ke tepi kiri laporan seperti chart
                     * lain. Lebarnya (jumlah kolom) tetap sama
                     * persis dengan sebelumnya, cuma posisinya
                     * yang bergeser. Kolom A yang jadi kosong di
                     * baris-baris chart ini diberi border kiri
                     * (lihat drawChartOnlySection) yang sama
                     * dengan border kanan chart supaya terlihat
                     * seperti bingkai yang disengaja. Tinggi tetap
                     * 34 baris karena kategorinya banyak (SD s.d.
                     * S2) dan horizontal.
                     *
                     * TIDAK ikut digeser ke SECTION_CONTENT_LAST_COL
                     * (kolom H) seperti chart Golongan: kalau
                     * digeser, tabel pendamping di sebelah kanannya
                     * (sideTableHeadings, 4 kolom) jadi tidak muat
                     * lagi sebelum SECTION_TITLE_LAST_COL (kolom K)
                     * dan akan membuat judul section melebihi K.
                     * Chart+tabel pendamping ini dianggap SATU
                     * kesatuan "isi", dan kesatuan itu sendiri sudah
                     * pas berakhir di kolom K (sama dengan
                     * banner-nya) lewat kombinasi G (chart) + H:K
                     * (tabel).
                     */
                    chartLeftCol: 'B',
                    chartRightCol: 'G',
                    chartHeightRows: 34,
                    /*
                     * Tabel di samping chart Pendidikan, berisi
                     * data yang sama persis dengan chart (SD s.d.
                     * S2, urutan ascending seperti aslinya —
                     * tidak dibalik/diberi spasi seperti data
                     * khusus chart di atas) supaya chart mudah
                     * dibaca angka pastinya.
                     */
                    sideTableHeadings: [
                        'Pendidikan',
                        'Pria',
                        'Wanita',
                        'Total'
                    ],
                    sideTableRows: array_map(
                        fn($r) => [
                            $r['label'],
                            $r['pria'],
                            $r['wanita'],
                            $r['jumlah']
                        ],
                        $this->data['pendidikan'] ?? []
                    ),
                    /*
                     * Digeser 1 kolom ke kanan juga (H, bukan G)
                     * supaya tetap menempel pas di sebelah kanan
                     * chart Pendidikan yang berakhir di kolom G
                     * (lihat chartRightCol di atas), dan berakhir
                     * persis di SECTION_TITLE_LAST_COL (kolom K).
                     */
                    sideTableStartCol: 'H'
                );

                /*
                 * =========================================================
                 * USIA
                 * =========================================================
                 */

                if (!empty($this->data['usia'])) {
                    /*
                     * Jarak 1-2 baris kosong sebelum judul "F. Usia".
                     */
                    $row += 1;

                    $row = $this->drawTableSection(
                        $sheet,
                        $row,
                        'F. Usia',
                        [
                            'Usia',
                            'Pria',
                            'Wanita',
                            'Total'
                        ],
                        array_map(
                            fn($r) => [
                                $r['label'],
                                $r['pria'],
                                $r['wanita'],
                                $r['total']
                            ],
                            $this->data['usia']
                        )
                    );
                }

                /*
                 * =========================================================
                 * AGAMA
                 * =========================================================
                 */

                if (!empty($this->data['agama'])) {
                    /*
                     * Jarak 1-2 baris kosong sebelum judul "G. Agama".
                     */
                    $row += 1;

                    $row = $this->drawTableSection(
                        $sheet,
                        $row,
                        'G. Agama',
                        [
                            'Agama',
                            'Pria',
                            'Wanita',
                            'Total'
                        ],
                        array_map(
                            fn($r) => [
                                $r['label'],
                                $r['pria'],
                                $r['wanita'],
                                $r['total']
                            ],
                            $this->data['agama']
                        )
                    );
                }

                /*
                 * =========================================================
                 * UNIT KERJA
                 * =========================================================
                 */

                if (!empty($this->data['unit_kerja'])) {
                    $rows = [];
                    $no = 1;

                    foreach ($this->data['unit_kerja'] as $r) {
                        $rows[] = [
                            $no++,
                            $r['label'],
                            $r['pria'],
                            $r['wanita'],
                            $r['total']
                        ];
                    }

                    /*
                     * Jarak 1-2 baris kosong sebelum judul "H. Unit
                     * Kerja".
                     */
                    $row += 1;

                    $row = $this->drawTableSection(
                        $sheet,
                        $row,
                        'H. Unit Kerja',
                        [
                            'No',
                            'Unit Kerja',
                            'Pria',
                            'Wanita',
                            'Total'
                        ],
                        $rows,
                        wrapColIndex: 1
                    );
                }

                /*
                 * Lebar kolom. Dibuat lebih ramping (12, dari
                 * sebelumnya 18) supaya lebar laporan di layar
                 * Excel tidak terlalu besar — tidak mengubah
                 * hasil print karena fitToWidth(1) di bawah selalu
                 * menyusutkan laporan ke satu halaman berapa pun
                 * lebar kolomnya.
                 */
                foreach (range('A', 'M') as $col) {
                    $sheet
                        ->getColumnDimension($col)
                        ->setWidth(12);
                }

                /*
                 * =========================================================
                 * PENGATURAN PRINT
                 * =========================================================
                 * Sebelumnya tidak ada pengaturan print sama sekali, jadi
                 * Excel memakai setting bawaan (tanpa fit-to-page) — itu
                 * sebabnya hasil print/preview berantakan & berbeda-beda
                 * tergantung ukuran kertas/orientasi yang kebetulan aktif
                 * di komputer user.
                 *
                 * fitToWidth(1) + fitToHeight(0) membuat Excel SELALU
                 * menyusutkan/membesarkan seluruh lebar laporan supaya
                 * pas dalam SATU halaman, apa pun ukuran kertas &
                 * orientasi (portrait/landscape) yang dipilih user di
                 * dialog print — jadi kolom tidak akan pernah lagi
                 * terpotong ke halaman lain. Tinggi dibiarkan mengalir
                 * ke beberapa halaman karena laporannya panjang ke bawah.
                 *
                 * Kop surat (baris 1-7: Pemkot Yogyakarta, judul laporan,
                 * BKPSDM, Periode Data & Tanggal Cetak) diulang otomatis
                 * di tiap halaman lewat rowsToRepeatAtTop, supaya halaman
                 * ke-2/3/dst tetap ada konteksnya.
                 */
                $this->applyPrintSetup(
                    $sheet,
                    "A1:M{$row}",
                    7,
                    'landscape'
                );
            },
        ];
    }

    /*
     * =========================================================
     * BULAN
     * =========================================================
     */

    private const NAMA_BULAN = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember',
    ];

    private function periodeLabel(): string
    {
        if (
            !preg_match(
                '/^(\d{4})-(\d{2})$/',
                $this->periode,
                $m
            )
        ) {
            return $this->periode;
        }

        $bulan = (int) $m[2];

        return isset(self::NAMA_BULAN[$bulan])
            ? self::NAMA_BULAN[$bulan] . ' ' . $m[1]
            : $this->periode;
    }

    private function tanggalCetakLabel(): string
    {
        $now = new \DateTime('now');
        $bulan = (int) $now->format('n');

        return $now->format('d')
            . ' '
            . (self::NAMA_BULAN[$bulan] ?? $now->format('m'))
            . ' '
            . $now->format('Y');
    }

    /*
     * =========================================================
     * LETTERHEAD
     * =========================================================
     */

    private function drawLetterhead(
        $sheet,
        int $startRow,
        string $lastCol
    ): int {
        $row = $startRow;

        $sheet->setCellValue(
            "A{$row}",
            'PEMERINTAH KOTA YOGYAKARTA'
        );

        $sheet->mergeCells(
            "A{$row}:{$lastCol}{$row}"
        );

        $sheet
            ->getStyle("A{$row}")
            ->getFont()
            ->setBold(true)
            ->setSize(12)
            ->getColor()
            ->setARGB('FFFFFFFF');

        $sheet
            ->getStyle("A{$row}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FF08402A');

        $sheet
            ->getStyle("A{$row}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet
            ->getRowDimension($row)
            ->setRowHeight(20);

        $row++;

        $sheet->setCellValue(
            "A{$row}",
            'LAPORAN RINGKASAN SIMASN'
        );

        $sheet->mergeCells(
            "A{$row}:{$lastCol}{$row}"
        );

        $sheet
            ->getStyle("A{$row}")
            ->getFont()
            ->setBold(true)
            ->setSize(16)
            ->getColor()
            ->setARGB('FFFFFFFF');

        $sheet
            ->getStyle("A{$row}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FF0D5C3A');

        $sheet
            ->getStyle("A{$row}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet
            ->getRowDimension($row)
            ->setRowHeight(26);

        $row++;

        $sheet->setCellValue(
            "A{$row}",
            'Badan Kepegawaian dan Pengembangan Sumber Daya Manusia (BKPSDM)'
        );

        $sheet->mergeCells(
            "A{$row}:{$lastCol}{$row}"
        );

        $sheet
            ->getStyle("A{$row}")
            ->getFont()
            ->setSize(11)
            ->getColor()
            ->setARGB('FF23301F');

        $sheet
            ->getStyle("A{$row}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFF0D79A');

        $sheet
            ->getStyle("A{$row}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet
            ->getRowDimension($row)
            ->setRowHeight(16);

        $row++;
        $row++;

        $info = [
            [
                'Periode Data',
                $this->periodeLabel()
            ],
            [
                'Tanggal Cetak Laporan',
                $this->tanggalCetakLabel()
            ],
        ];

        $labelLastCol = 'B';
        $valueFirstCol = 'C';

        foreach ($info as [$label, $value]) {
            $sheet->setCellValue(
                "A{$row}",
                $label
            );

            $sheet->mergeCells(
                "A{$row}:{$labelLastCol}{$row}"
            );

            $sheet
                ->getStyle("A{$row}")
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setARGB('FF000000');

            $sheet
                ->getStyle("A{$row}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFFBF6EC');

            $sheet
                ->getStyle("A{$row}")
                ->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);

            $sheet
                ->getStyle("A{$row}:{$labelLastCol}{$row}")
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            $sheet->setCellValue(
                "{$valueFirstCol}{$row}",
                $value
            );

            $sheet->mergeCells(
                "{$valueFirstCol}{$row}:{$lastCol}{$row}"
            );

            $sheet
                ->getStyle("{$valueFirstCol}{$row}")
                ->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);

            $sheet
                ->getStyle(
                    "{$valueFirstCol}{$row}:{$lastCol}{$row}"
                )
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            $sheet
                ->getRowDimension($row)
                ->setRowHeight(18);

            $row++;
        }

        return $row + 1;
    }

    /*
     * =========================================================
     * GOLONGAN
     * =========================================================
     */

    private function golonganLabel(string $label): string
    {
        return $label === 'PPPK'
            ? 'PPPK'
            : 'Gol. ' . $label;
    }

    /*
     * =========================================================
     * CARD WIDTH
     * =========================================================
     * Dipakai untuk membagi rata SECTION_CONTENT_LAST_COL (kolom
     * H) ke sejumlah $count kartu berjajar (Data Pegawai, JFT
     * rincian, Generasi), supaya lebar totalnya selalu sama
     * dengan isi section lain (Golongan/Pendidikan/Usia/Agama/
     * Unit Kerja), berapa pun jumlah kartunya. Kolom ekstra sisa
     * pembagian (kalau $totalCols tidak habis dibagi $count)
     * dialokasikan ke kartu-kartu PALING KIRI, satu kolom ekstra
     * per kartu, supaya jumlah total kolom tetap persis
     * $totalCols.
     */
    private function distributeCardCols(
        int $count,
        int $totalCols,
        string $startCol = 'A'
    ): array {
        if ($count === 0) {
            return [];
        }

        $base = intdiv($totalCols, $count);
        $remainder = $totalCols % $count;

        $cursor =
            Coordinate::columnIndexFromString($startCol);

        $groups = [];

        for ($i = 0; $i < $count; $i++) {
            $width = $base + ($i < $remainder ? 1 : 0);

            $first = Coordinate::stringFromColumnIndex($cursor);
            $last = Coordinate::stringFromColumnIndex(
                $cursor + $width - 1
            );

            $groups[] = [$first, $last];

            $cursor += $width;
        }

        return $groups;
    }

    /*
     * =========================================================
     * SECTION HEADER
     * =========================================================
     */

    private function drawSectionHeader(
        $sheet,
        int $startRow,
        string $title,
        int $spanCols
    ): int {
        $lastCol = Coordinate::stringFromColumnIndex(
            $spanCols
        );

        $sheet->setCellValue(
            "A{$startRow}",
            $title
        );

        $sheet->mergeCells(
            "A{$startRow}:{$lastCol}{$startRow}"
        );

        $sheet
            ->getStyle("A{$startRow}")
            ->getFont()
            ->setBold(true)
            ->getColor()
            ->setARGB(self::SECTION_TEXT);

        $sheet
            ->getStyle("A{$startRow}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB(self::SECTION_FILL);

        /*
         * +1: langsung ke baris berikutnya, tanpa baris kosong
         * antara judul section dan isinya (kartu/tabel). Section
         * Golongan & Pendidikan (drawChartOnlySection) TETAP
         * punya 1 baris kosong antara judul dan chart — itu
         * sengaja dipertahankan, tidak lewat fungsi ini.
         */
        return $startRow + 1;
    }

    /*
     * =========================================================
     * A. DATA PEGAWAI
     * =========================================================
     *
     * Satu kartu per kategori (gender tidak lagi dipecah jadi
     * kotak sendiri): banner judul hijau (SECTION_FILL), lalu
     * satu kotak krem (IKHTISAR_CARD_FILL) berisi angka total
     * besar (IKHTISAR_VALUE_TEXT), dengan rincian gender
     * ditulis sebagai satu baris kecil di bawah angka —
     * mengikuti pola yang sudah dipakai drawGenerasiCards
     * (baris "Pria: x   Wanita: y").
     *
     * Struktur:
     *
     * Total Pegawai
     *
     * Jabatan Struktural | JFU | JFT
     *
     * JFT-Pendidikan | JFT-Kesehatan | JFT-Teknis
     *
     */

    private function drawEmployeeDataTable(
        $sheet,
        int $startRow,
        array $cards
    ): int {
        $byLabel = [];

        foreach ($cards as $card) {
            $byLabel[$card['label']] = $card;
        }

        $default = fn(string $label) => [
            'label' => $label,
            'total' => 0,
            'pria' => 0,
            'wanita' => 0,
        ];

        $total = $byLabel['Total Pegawai']
            ?? $default('Total Pegawai');

        $struktural = $byLabel['Jabatan Struktural']
            ?? $default('Jabatan Struktural');

        $jfu = $byLabel['JFU']
            ?? $default('JFU');

        $jft = $byLabel['JFT']
            ?? $default('JFT');

        $pendidikan = $byLabel['JFT - Pendidikan']
            ?? $default('JFT - Pendidikan');

        $kesehatan = $byLabel['JFT - Kesehatan']
            ?? $default('JFT - Kesehatan');

        $teknis = $byLabel['JFT - Teknis']
            ?? $default('JFT - Teknis');

        /*
         * Tiga kelompok kolom, bersinggungan langsung tanpa
         * kolom pemisah, dibagi rata sampai SECTION_CONTENT_LAST_COL
         * (kolom H) supaya lebarnya sama dengan isi section lain.
         */
        $groups = $this->distributeCardCols(
            3,
            Coordinate::columnIndexFromString(
                self::SECTION_CONTENT_LAST_COL
            )
        );

        /*
         * =====================================================
         * TOTAL PEGAWAI — satu kartu lebar (A sampai
         * SECTION_CONTENT_LAST_COL)
         * =====================================================
         */

        $row = $this->drawIkhtisarCard(
            $sheet,
            'A',
            self::SECTION_CONTENT_LAST_COL,
            $startRow,
            $total,
            20
        );

        $row += 1;

        /*
         * =====================================================
         * JABATAN STRUKTURAL / JFU / JFT
         * =====================================================
         */

        $mainCards = [
            $struktural,
            $jfu,
            $jft,
        ];

        $mainRowEnd = $row;

        foreach ($mainCards as $i => $card) {
            [$firstCol, $lastCol] = $groups[$i];

            $mainRowEnd = $this->drawIkhtisarCard(
                $sheet,
                $firstCol,
                $lastCol,
                $row,
                $card,
                16
            );
        }

        $row = $mainRowEnd + 1;

        /*
         * =====================================================
         * JFT PENDIDIKAN / KESEHATAN / TEKNIS
         * =====================================================
         */

        $jftCards = [
            $pendidikan,
            $kesehatan,
            $teknis,
        ];

        $jftRowEnd = $row;

        foreach ($jftCards as $i => $card) {
            [$firstCol, $lastCol] = $groups[$i];

            $jftRowEnd = $this->drawIkhtisarCard(
                $sheet,
                $firstCol,
                $lastCol,
                $row,
                $card,
                16
            );
        }

        $row = $jftRowEnd;

        return $row + 1;
    }

    /*
     * =========================================================
     * Satu kartu gaya IKHTISAR: banner judul hijau, lalu kotak
     * krem berisi angka total besar (sel ANGKA asli, format
     * #,##0) dengan rincian "Pria: x   Wanita: y" sebagai satu
     * baris kecil menyatu di bawahnya (satu kartu, bukan kotak
     * terpisah per gender).
     * =========================================================
     */

    private function drawIkhtisarCard(
        $sheet,
        string $firstCol,
        string $lastCol,
        int $startRow,
        array $card,
        float $valueFontSize
    ): int {
        $titleRow = $startRow;
        $valueRow = $startRow + 1;
        $subRow = $startRow + 2;

        $titleRange = $firstCol === $lastCol
            ? "{$firstCol}{$titleRow}"
            : "{$firstCol}{$titleRow}:{$lastCol}{$titleRow}";

        $valueRange = $firstCol === $lastCol
            ? "{$firstCol}{$valueRow}"
            : "{$firstCol}{$valueRow}:{$lastCol}{$valueRow}";

        $subRange = $firstCol === $lastCol
            ? "{$firstCol}{$subRow}"
            : "{$firstCol}{$subRow}:{$lastCol}{$subRow}";

        if ($firstCol !== $lastCol) {
            $sheet->mergeCells($titleRange);
            $sheet->mergeCells($valueRange);
            $sheet->mergeCells($subRange);
        }

        /*
         * Judul kartu — warna disamakan dengan kotak judul
         * BKPSDM pada kop surat (CARD_TITLE_FILL/TEXT).
         */
        $sheet->setCellValue(
            "{$firstCol}{$titleRow}",
            $card['label']
        );

        $sheet
            ->getStyle($titleRange)
            ->getFont()
            ->setBold(true)
            ->setSize($firstCol === 'A' && $lastCol === 'K' ? 13 : 10)
            ->getColor()
            ->setARGB(self::CARD_TITLE_TEXT);

        $sheet
            ->getStyle($titleRange)
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB(self::CARD_TITLE_FILL);

        $sheet
            ->getStyle($titleRange)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $sheet
            ->getStyle($titleRange)
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()
            ->setARGB(self::CARD_BORDER);

        $sheet
            ->getRowDimension($titleRow)
            ->setRowHeight($firstCol === 'A' && $lastCol === 'K' ? 22 : 26);

        /*
         * Angka total — sel ANGKA asli (bukan teks), krem +
         * hijau tua, border tipis atas/kiri/kanan (menyatu ke
         * baris gender di bawahnya).
         */
        $sheet->setCellValue(
            "{$firstCol}{$valueRow}",
            (int) ($card['total'] ?? 0)
        );

        $sheet
            ->getStyle($valueRange)
            ->getFont()
            ->setBold(true)
            ->setSize($valueFontSize)
            ->getColor()
            ->setARGB(self::IKHTISAR_VALUE_TEXT);

        $sheet
            ->getStyle($valueRange)
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB(self::IKHTISAR_CARD_FILL);

        $sheet
            ->getStyle($valueRange)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet
            ->getStyle($valueRange)
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        foreach (['getTop', 'getLeft', 'getRight'] as $side) {
            $sheet
                ->getStyle($valueRange)
                ->getBorders()
                ->{$side}()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()
                ->setARGB(self::CARD_BORDER);
        }

        /*
         * Rincian gender — satu baris kecil "Pria: x  Wanita: y"
         * menyatu di bawah angka (border kiri/kanan/bawah
         * menutup kotak).
         */
        $sheet->setCellValue(
            "{$firstCol}{$subRow}",
            'Pria: ' . number_format((int) ($card['pria'] ?? 0), 0, ',', '.') .
                '   Wanita: ' . number_format((int) ($card['wanita'] ?? 0), 0, ',', '.')
        );

        $sheet
            ->getStyle($subRange)
            ->getFont()
            ->setBold(false)
            ->setSize(8)
            ->getColor()
            ->setARGB(self::IKHTISAR_LABEL_TEXT);

        $sheet
            ->getStyle($subRange)
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB(self::IKHTISAR_CARD_FILL);

        $sheet
            ->getStyle($subRange)
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        foreach (['getLeft', 'getRight', 'getBottom'] as $side) {
            $sheet
                ->getStyle($subRange)
                ->getBorders()
                ->{$side}()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()
                ->setARGB(self::CARD_BORDER);
        }

        $sheet
            ->getRowDimension($valueRow)
            ->setRowHeight($valueFontSize >= 20 ? 28 : 22);

        $sheet
            ->getRowDimension($subRow)
            ->setRowHeight(14);

        return $subRow;
    }

    /*
     * =========================================================
     * GENERASI
     * =========================================================
     */

    private const GENERASI_RENTANG_TAHUN = [
        'Baby Boomer' => '1946 - 1964',
        'Generasi X' => '1965 - 1980',
        'Generasi Y' => '1981 - 1996',
        'Generasi Z' => '1997 - 2012',
    ];

    private function drawGenerasiCards(
        $sheet,
        int $startRow,
        array $rows
    ): int {
        /*
         * Gaya kartu disamakan persis dengan kartu "A. Data
         * Pegawai" (drawIkhtisarCard): banner judul warna
         * BKPSDM, kotak krem angka total, baris gender
         * "Pria: x  Wanita: y", border hitam. Lebar tiap kartu
         * dibagi rata sampai SECTION_CONTENT_LAST_COL (kolom H)
         * supaya total lebar kartu Generasi sama dengan isi
         * section lain (Golongan/Pendidikan/Usia/Agama/Unit
         * Kerja), berapa pun jumlah kategori generasinya.
         */
        $groups = $this->distributeCardCols(
            count($rows),
            Coordinate::columnIndexFromString(
                self::SECTION_CONTENT_LAST_COL
            )
        );

        $lastRow = $startRow;

        foreach ($rows as $i => $r) {
            $rentangTahun =
                self::GENERASI_RENTANG_TAHUN[$r['label']] ?? null;

            $label = $rentangTahun !== null
                ? $r['label'] . "\n" . $rentangTahun
                : $r['label'];

            $card = [
                'label' => $label,
                'total' => $r['total'] ?? 0,
                'pria' => $r['pria'] ?? 0,
                'wanita' => $r['wanita'] ?? 0,
            ];

            [$firstCol, $lastCol] = $groups[$i];

            $lastRow = $this->drawIkhtisarCard(
                $sheet,
                $firstCol,
                $lastCol,
                $startRow,
                $card,
                14
            );
        }

        return $lastRow + 1;
    }

    /*
     * =========================================================
     * TABLE
     * =========================================================
     */

    /*
     * Tema tabel (Pendidikan, Usia, Agama, Unit Kerja) disamakan
     * dengan tema kartu "A. Data Pegawai" & "B. Generasi"
     * (CARD_TITLE_FILL/TEXT, IKHTISAR_CARD_FILL/VALUE_TEXT,
     * CARD_BORDER) supaya seluruh laporan konsisten satu tema
     * hijau-emas/krem, bukan lagi abu-abu generik.
     */
    private const TABLE_HEADER_BG = self::CARD_TITLE_FILL;
    private const TABLE_HEADER_TEXT = self::CARD_TITLE_TEXT;
    private const TABLE_ROW_BORDER = self::CARD_BORDER;
    private const TABLE_STRIPE_BG = self::IKHTISAR_CARD_FILL;
    /*
     * Teks label tabel (Pendidikan/Usia/Agama/Unit Kerja) diubah
     * jadi hitam polos (dulu ikut warna hijau tema
     * IKHTISAR_VALUE_TEXT), sama seperti kolom angkanya
     * (TABLE_NUMBER_TEXT) yang memang sudah hitam.
     */
    private const TABLE_LABEL_TEXT = 'FF000000';
    private const TABLE_TOTAL_BG = self::CARD_TITLE_FILL;
    private const TABLE_TOTAL_BORDER = self::SECTION_FILL;

    /*
     * Baris "Total" (keterangan + angka jumlahnya) dan setiap
     * angka (Pria/Wanita/Total) di baris data tabel dibuat
     * hitam polos — bukan lagi warna hijau IKHTISAR_VALUE_TEXT
     * yang dipakai untuk teks label (Usia/Agama/Unit Kerja/
     * Pendidikan) — supaya angka lebih jelas terbaca dan tidak
     * disangka teks tema.
     */
    private const TABLE_NUMBER_TEXT = 'FF000000';

    private const TABLE_NUMERIC_HEADINGS = [
        'Pria',
        'Wanita',
        'Total'
    ];

    private function drawTableSection(
        $sheet,
        int $startRow,
        string $title,
        array $headings,
        array $rows,
        ?int $wrapColIndex = null,
        string $startCol = 'A',
        bool $skipTitle = false
    ): int {
        $tableTopRow = $startRow;

        $colCount = count($headings);

        $baseColIndex =
            Coordinate::columnIndexFromString(
                $startCol
            );

        /*
         * Lebar ISI tabel (header kolom + data) sekarang selalu
         * sampai SECTION_CONTENT_LAST_COL (kolom H) — sama dengan
         * isi section Data Pegawai/Generasi/Golongan/Pendidikan,
         * bukan lagi rasio dari lebar judul laporan. Ini HANYA
         * berlaku untuk tabel berdiri sendiri yang mulai dari
         * kolom A (Usia, Agama, Unit Kerja) — bukan untuk tabel
         * pendamping di samping chart (mis. tabel Pendidikan yang
         * mulai dari kolom H), karena lebar tabel pendamping itu
         * memang sengaja mengikuti sisa ruang di samping chart-nya.
         *
         * Kolom ekstra yang didapat dari pelebaran ini
         * SELURUHNYA dialokasikan ke satu kolom "label" (bukan
         * dibagi rata ke semua kolom), supaya kolom angka
         * (Pria/Wanita/Total/No) tetap ramping dan mudah dibaca,
         * sementara kolom teksnya yang melebar mengisi sisa
         * ruang. Kolom yang dipilih adalah $wrapColIndex kalau
         * ada (kolom yang memang sudah didesain untuk teks
         * panjang, mis. "Unit Kerja"), atau kalau tidak ada,
         * kolom pertama yang bukan "No" dan bukan kolom angka
         * (mis. "Usia", "Agama").
         *
         * Judul tabel (banner hijau) TIDAK memakai lebar ini —
         * judul selalu dibuat sampai SECTION_TITLE_LAST_COL (kolom
         * K, lihat $titleLastColIndex di bawah), supaya banner
         * judul tetap terlihat lebih lebar daripada isi tabel di
         * bawahnya, sesuai permintaan.
         */

        $tableContentLastColIndex =
            Coordinate::columnIndexFromString(
                self::SECTION_CONTENT_LAST_COL
            );

        $naturalLastColIndex =
            $baseColIndex + $colCount - 1;

        $extraCols =
            $startCol === 'A'
            ? max(
                0,
                $tableContentLastColIndex - $naturalLastColIndex
            )
            : 0;

        $stretchIndex = null;

        if ($extraCols > 0) {
            $stretchIndex = $wrapColIndex;

            if ($stretchIndex === null) {
                foreach ($headings as $i => $label) {
                    if (
                        $label !== 'No' &&
                        !in_array(
                            $label,
                            self::TABLE_NUMERIC_HEADINGS,
                            true
                        )
                    ) {
                        $stretchIndex = $i;

                        break;
                    }
                }
            }

            $stretchIndex = $stretchIndex ?? 0;
        }

        /*
         * $colStart / $colEnd: indeks kolom fisik (awal & akhir)
         * untuk tiap kolom logis $i. Sama-sama sejumlah $colCount,
         * tapi kolom ke-$stretchIndex punya $colEnd > $colStart
         * (artinya kolom itu adalah gabungan/merge beberapa
         * kolom fisik). Kolom lain tetap $colEnd === $colStart
         * (satu kolom fisik saja), persis seperti sebelum revisi
         * ini.
         */

        $colStart = [];
        $colEnd = [];
        $cursor = $baseColIndex;

        foreach ($headings as $i => $label) {
            $colStart[$i] = $cursor;

            $colEnd[$i] =
                $i === $stretchIndex
                ? $cursor + $extraCols
                : $cursor;

            $cursor = $colEnd[$i] + 1;
        }

        $colLetter = array_map(
            fn($idx) => Coordinate::stringFromColumnIndex($idx),
            $colStart
        );

        $colLetterEnd = array_map(
            fn($idx) => Coordinate::stringFromColumnIndex($idx),
            $colEnd
        );

        $lastCol = $colLetterEnd[$colCount - 1];

        $numericColIndexes = [];

        foreach ($headings as $i => $label) {
            if (
                in_array(
                    $label,
                    self::TABLE_NUMERIC_HEADINGS,
                    true
                )
            ) {
                $numericColIndexes[] = $i;
            }
        }

        $firstNumericIndex =
            $numericColIndexes[0] ?? $colCount;

        /*
         * Header section. Bisa dilewati ($skipTitle) kalau tabel
         * ini adalah tabel pendamping chart yang sudah berbagi
         * satu judul dengan chart di sebelahnya (mis. tabel
         * Pendidikan berbagi judul "Pendidikan" dengan grafiknya).
         */

        if (!$skipTitle) {
            /*
             * Lebar judul (banner hijau) dihitung terpisah dari
             * lebar isi tabel: selalu sampai SECTION_TITLE_LAST_COL
             * (kolom K), bukan ikut $lastCol isi tabel yang cuma
             * sampai kolom H. Dijaga tidak pernah lebih sempit
             * dari isi tabel (max(...)), seandainya isi tabel
             * kebetulan sudah lebih lebar dari kolom K itu sendiri
             * (mis. kolom heading banyak).
             */
            $titleLastColIndex =
                $startCol === 'A'
                ? max(
                    Coordinate::columnIndexFromString($lastCol),
                    Coordinate::columnIndexFromString(
                        self::SECTION_TITLE_LAST_COL
                    )
                )
                : Coordinate::columnIndexFromString($lastCol);

            $titleLastCol =
                Coordinate::stringFromColumnIndex(
                    $titleLastColIndex
                );

            $sheet->setCellValue(
                "{$startCol}{$startRow}",
                $title
            );

            $sheet->mergeCells(
                "{$startCol}{$startRow}:{$titleLastCol}{$startRow}"
            );

            $sheet
                ->getStyle("{$startCol}{$startRow}")
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setARGB(self::SECTION_TEXT);

            $sheet
                ->getStyle("{$startCol}{$startRow}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB(self::SECTION_FILL);

            /*
             * Tinggi baris judul TIDAK diset lagi secara eksplisit
             * (dulu 22) — dibiarkan ikut tinggi default baris,
             * sama seperti judul "A. Data Pegawai"/"B. Generasi"
             * (drawSectionHeader) yang juga tidak pernah diset
             * tingginya — supaya ukuran kotak judul semua section
             * (Golongan/Pendidikan/Usia/Agama/Unit Kerja) sama
             * persis dengan Data Pegawai & Generasi.
             */

            $startRow++;

            /*
             * Tidak ada lagi baris kosong antara judul tabel &
             * isinya (header kolom + data) — header kolom
             * langsung di baris berikutnya setelah judul. Section
             * Golongan & Pendidikan (drawChartOnlySection) TETAP
             * punya 1 baris kosong antara judul dan chart-nya,
             * karena itu tidak lewat drawTableSection ini (kecuali
             * tabel pendamping chart-nya, yang sudah $skipTitle
             * true dan memang sengaja sejajar dengan bagian atas
             * chart tanpa judul sendiri).
             */
        }

        /*
         * Header kolom
         */

        $headerRow = $startRow;

        foreach ($headings as $i => $label) {
            $col = $colLetter[$i];

            $sheet->setCellValue(
                "{$col}{$headerRow}",
                $label
            );

            if ($colLetterEnd[$i] !== $col) {
                $sheet->mergeCells(
                    "{$col}{$headerRow}:{$colLetterEnd[$i]}{$headerRow}"
                );
            }
        }

        $sheet
            ->getStyle(
                "{$startCol}{$headerRow}:{$lastCol}{$headerRow}"
            )
            ->getFont()
            ->setBold(true)
            ->setSize(10)
            ->getColor()
            ->setARGB(
                self::TABLE_HEADER_TEXT
            );

        $sheet
            ->getStyle(
                "{$startCol}{$headerRow}:{$lastCol}{$headerRow}"
            )
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB(
                self::TABLE_HEADER_BG
            );

        $sheet
            ->getStyle(
                "{$startCol}{$headerRow}:{$lastCol}{$headerRow}"
            )
            ->getBorders()
            ->getBottom()
            ->setBorderStyle(
                Border::BORDER_MEDIUM
            )
            ->getColor()
            ->setARGB(
                self::TABLE_TOTAL_BORDER
            );

        $sheet
            ->getRowDimension($headerRow)
            ->setRowHeight(20);

        $this->applyTableColumnAlignment(
            $sheet,
            "{$headerRow}",
            $headings,
            $numericColIndexes,
            $firstNumericIndex,
            $colLetter
        );

        $startRow++;

        /*
         * Data
         */

        $dataStartRow = $startRow;

        foreach ($rows as $rIndex => $rowData) {
            foreach ($rowData as $i => $value) {
                $col = $colLetter[$i];

                $sheet->setCellValue(
                    "{$col}{$startRow}",
                    $value
                );

                if ($colLetterEnd[$i] !== $col) {
                    $sheet->mergeCells(
                        "{$col}{$startRow}:{$colLetterEnd[$i]}{$startRow}"
                    );
                }
            }

            $rowRange =
                "{$startCol}{$startRow}:{$lastCol}{$startRow}";

            $sheet
                ->getStyle($rowRange)
                ->getFont()
                ->setSize(10)
                ->getColor()
                ->setARGB(
                    self::TABLE_LABEL_TEXT
                );

            $sheet
                ->getStyle($rowRange)
                ->getBorders()
                ->getBottom()
                ->setBorderStyle(
                    Border::BORDER_THIN
                )
                ->getColor()
                ->setARGB(
                    self::TABLE_ROW_BORDER
                );

            if ($rIndex % 2 === 1) {
                $sheet
                    ->getStyle($rowRange)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB(
                        self::TABLE_STRIPE_BG
                    );
            }

            $this->applyTableColumnAlignment(
                $sheet,
                (string) $startRow,
                $headings,
                $numericColIndexes,
                $firstNumericIndex,
                $colLetter
            );

            /*
             * Kolom angka (Pria/Wanita/Total) di baris ini
             * ditimpa jadi hitam (lihat TABLE_NUMBER_TEXT),
             * menimpa warna hijau yang barusan diset untuk
             * seluruh baris di atas — kolom label (mis. Usia,
             * Agama) tetap warna tema seperti semula.
             */
            foreach ($numericColIndexes as $i) {
                $sheet
                    ->getStyle(
                        "{$colLetter[$i]}{$startRow}"
                    )
                    ->getFont()
                    ->getColor()
                    ->setARGB(self::TABLE_NUMBER_TEXT);
            }

            foreach ($headings as $i => $label) {
                if ($label === 'Total') {
                    $sheet
                        ->getStyle(
                            "{$colLetter[$i]}{$startRow}"
                        )
                        ->getFont()
                        ->setBold(true);
                }
            }

            $sheet
                ->getRowDimension($startRow)
                ->setRowHeight(
                    $wrapColIndex !== null
                        ? 30
                        : 20
                );

            $startRow++;
        }

        $lastDataRow = $startRow - 1;

        if (
            $wrapColIndex !== null &&
            $lastDataRow >= $dataStartRow
        ) {
            $wrapCol = $colLetter[$wrapColIndex];

            $sheet
                ->getStyle(
                    "{$wrapCol}{$headerRow}:{$wrapCol}{$lastDataRow}"
                )
                ->getAlignment()
                ->setWrapText(true);
        }

        /*
         * Total
         */

        if (
            $lastDataRow >= $dataStartRow &&
            !empty($numericColIndexes)
        ) {
            $totalRow = $startRow;

            $labelEndIndex =
                max(
                    0,
                    $firstNumericIndex - 1
                );

            $labelEndCol = $colLetterEnd[$labelEndIndex];

            $sheet->setCellValue(
                "{$startCol}{$totalRow}",
                'Total'
            );

            if ($labelEndIndex > 0 || $labelEndCol !== $startCol) {
                $sheet->mergeCells(
                    "{$startCol}{$totalRow}:{$labelEndCol}{$totalRow}"
                );
            }

            foreach ($numericColIndexes as $i) {
                $sum = 0;

                foreach ($rows as $rowData) {
                    $sum += (float) (
                        $rowData[$i] ?? 0
                    );
                }

                $sheet->setCellValue(
                    "{$colLetter[$i]}{$totalRow}",
                    $sum
                );
            }

            $totalRange =
                "{$startCol}{$totalRow}:{$lastCol}{$totalRow}";

            $sheet
                ->getStyle($totalRange)
                ->getFont()
                ->setBold(true)
                ->setSize(10)
                ->getColor()
                ->setARGB(
                    self::TABLE_NUMBER_TEXT
                );

            $sheet
                ->getStyle($totalRange)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB(
                    self::TABLE_TOTAL_BG
                );

            $sheet
                ->getStyle($totalRange)
                ->getBorders()
                ->getTop()
                ->setBorderStyle(
                    Border::BORDER_MEDIUM
                )
                ->getColor()
                ->setARGB(
                    self::TABLE_TOTAL_BORDER
                );

            $this->applyTableColumnAlignment(
                $sheet,
                (string) $totalRow,
                $headings,
                $numericColIndexes,
                $firstNumericIndex,
                $colLetter
            );

            $sheet
                ->getRowDimension($totalRow)
                ->setRowHeight(22);

            $startRow++;
        }

        /*
         * Format angka
         */

        if (
            !empty($numericColIndexes) &&
            $startRow - 1 >= $headerRow + 1
        ) {
            foreach ($numericColIndexes as $i) {
                $col = $colLetter[$i];

                $sheet
                    ->getStyle(
                        "{$col}" .
                            ($headerRow + 1) .
                            ":{$col}" .
                            ($startRow - 1)
                    )
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
            }
        }

        /*
         * Border pinggiran tabel (outline), dari baris judul
         * (atau header kalau $skipTitle) sampai baris terakhir
         * yang dipakai (data / Total), mengelilingi seluruh
         * lebar tabel ($startCol s.d. $lastCol). Hanya sisi
         * TOP/BOTTOM/LEFT/RIGHT terluar yang digambar (bukan
         * grid tiap sel) supaya tidak bentrok dengan garis
         * dalam tabel (header, antar baris, Total) yang sudah
         * ada.
         */

        $tableBottomRow = $startRow - 1;

        if ($tableBottomRow >= $tableTopRow) {
            $outline =
                $sheet
                ->getStyle(
                    "{$startCol}{$tableTopRow}:{$lastCol}{$tableBottomRow}"
                )
                ->getBorders();

            $outline
                ->getOutline()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()
                ->setARGB(self::CARD_BORDER);
        }

        return $startRow + 1;
    }

    private function applyTableColumnAlignment(
        $sheet,
        string $row,
        array $headings,
        array $numericColIndexes,
        int $firstNumericIndex,
        array $colLetter
    ): void {
        foreach ($headings as $i => $label) {
            $cell =
                "{$colLetter[$i]}{$row}";

            if ($label === 'No') {
                $sheet
                    ->getStyle($cell)
                    ->getAlignment()
                    ->setHorizontal(
                        Alignment::HORIZONTAL_CENTER
                    );
            } elseif ($label === 'Total') {
                $sheet
                    ->getStyle($cell)
                    ->getAlignment()
                    ->setHorizontal(
                        Alignment::HORIZONTAL_RIGHT
                    );
            } elseif (
                in_array(
                    $label,
                    ['Pria', 'Wanita'],
                    true
                )
            ) {
                $sheet
                    ->getStyle($cell)
                    ->getAlignment()
                    ->setHorizontal(
                        Alignment::HORIZONTAL_CENTER
                    );
            } elseif (
                $i < $firstNumericIndex
            ) {
                $sheet
                    ->getStyle($cell)
                    ->getAlignment()
                    ->setHorizontal(
                        Alignment::HORIZONTAL_LEFT
                    );
            }
        }
    }

    /*
     * =========================================================
     * CHART ONLY
     * =========================================================
     */

    private function drawChartOnlySection(
        $sheet,
        $dataSheet,
        string $dataSheetTitle,
        int &$dataRow,
        int $startRow,
        string $title,
        array $headings,
        array $rows,
        array $seriesCols,
        ?array $seriesColors = null,
        bool $addSeriesSpacer = false,
        bool $horizontal = false,
        string $chartLeftCol = 'A',
        string $chartRightCol = 'F',
        ?int $chartHeightRows = null,
        ?array $sideTableHeadings = null,
        ?array $sideTableRows = null,
        ?string $sideTableStartCol = null
    ): int {
        /*
         * Posisi tabel pendamping (kalau ada) dihitung dulu,
         * lepas dari lebar judul — tabel pendamping tetap
         * mengikuti sisa ruang di samping chart, walaupun
         * judulnya sendiri sekarang tidak lagi ikut melebar
         * mengikuti tabel ini (lihat $titleLastCol di bawah).
         */
        $sideTableCol = $sideTableStartCol;

        if (
            $sideTableHeadings !== null &&
            $sideTableRows !== null
        ) {
            $sideTableCol =
                $sideTableCol ??
                Coordinate::stringFromColumnIndex(
                    Coordinate::columnIndexFromString(
                        $chartRightCol
                    ) + self::CHART_GAP_COLS
                );
        }

        /*
         * Judul section (banner hijau) SELALU melebar sampai
         * SECTION_TITLE_LAST_COL (kolom K), sama dengan semua
         * section lain (Data Pegawai, Generasi, Usia, Agama, Unit
         * Kerja) — bukan lagi ikut lebar chart/tabel pendamping.
         * Dijaga tidak pernah lebih sempit dari isi chart/tabel
         * pendamping itu sendiri (max(...)), seandainya suatu saat
         * isinya kebetulan lebih lebar dari kolom K.
         */
        $naturalLastColIndex =
            Coordinate::columnIndexFromString($chartRightCol);

        if (
            $sideTableHeadings !== null &&
            $sideTableRows !== null
        ) {
            $naturalLastColIndex = max(
                $naturalLastColIndex,
                Coordinate::columnIndexFromString($sideTableCol)
                    + count($sideTableHeadings) - 1
            );
        }

        $titleLastCol =
            Coordinate::stringFromColumnIndex(
                max(
                    $naturalLastColIndex,
                    Coordinate::columnIndexFromString(
                        self::SECTION_TITLE_LAST_COL
                    )
                )
            );

        $sheet->setCellValue(
            "A{$startRow}",
            $title
        );

        $sheet->mergeCells(
            "A{$startRow}:{$titleLastCol}{$startRow}"
        );

        $sheet
            ->getStyle("A{$startRow}")
            ->getFont()
            ->setBold(true)
            ->getColor()
            ->setARGB(
                self::SECTION_TEXT
            );

        $sheet
            ->getStyle("A{$startRow}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB(
                self::SECTION_FILL
            );

        /*
         * Tinggi baris judul TIDAK diset lagi secara eksplisit
         * (dulu 22) — dibiarkan ikut tinggi default baris, sama
         * seperti judul "A. Data Pegawai"/"B. Generasi", supaya
         * ukuran kotak judul Golongan & Pendidikan sama persis
         * dengan Data Pegawai & Generasi.
         */

        $nextRow = $startRow + 1;

        /*
         * Data source chart.
         */

        $headerRow = $dataRow;

        foreach ($headings as $i => $label) {
            $col =
                Coordinate::stringFromColumnIndex(
                    $i + 1
                );

            $dataSheet->setCellValue(
                "{$col}{$headerRow}",
                $label
            );
        }

        $dataRow++;

        $dataStartRow = $dataRow;

        foreach ($rows as $rowData) {
            foreach ($rowData as $i => $value) {
                $col =
                    Coordinate::stringFromColumnIndex(
                        $i + 1
                    );

                $dataSheet->setCellValue(
                    "{$col}{$dataRow}",
                    $value
                );
            }

            $dataRow++;
        }

        $dataEndRow = $dataRow - 1;

        $dataRow += 2;

        if ($dataEndRow >= $dataStartRow) {
            $series = [];

            foreach ($seriesCols as $colIndex => $seriesName) {
                $col =
                    Coordinate::stringFromColumnIndex(
                        $colIndex + 1
                    );

                $series[] = [
                    'labelRef' =>
                    "'{$dataSheetTitle}'!\${$col}\${$headerRow}",

                    'valueRange' =>
                    "'{$dataSheetTitle}'!\${$col}\${$dataStartRow}:\${$col}\${$dataEndRow}",
                ];
            }

            /*
             * Trik "spacer series": PhpSpreadsheet SELALU menulis
             * bar-bar dalam satu kategori chart clustered saling
             * menempel (tidak ada opsi utk atur jarak/overlap-nya
             * seperti "Gap Width" antar-seri di Excel asli). Supaya
             * Pria & Wanita punya sedikit jarak TANPA memecah jadi
             * dua chart terpisah, disisipkan 1 seri tak terlihat di
             * antara keduanya: kolom sumbernya sengaja dibiarkan
             * kosong (bukan 0) supaya tidak ada bar/label "0" yang
             * ikut kelihatan, dan warnanya disamakan putih supaya
             * menyatu dengan latar chart.
             */
            if ($addSeriesSpacer && count($series) === 2) {
                $spacerCol = Coordinate::stringFromColumnIndex(
                    count($headings) + 1
                );

                $series = [
                    $series[0],
                    [
                        'labelRef' =>
                        "'{$dataSheetTitle}'!\${$spacerCol}\${$headerRow}",

                        'valueRange' =>
                        "'{$dataSheetTitle}'!\${$spacerCol}\${$dataStartRow}:\${$spacerCol}\${$dataEndRow}",
                    ],
                    $series[1],
                ];

                if ($seriesColors !== null && count($seriesColors) === 2) {
                    $seriesColors = [
                        $seriesColors[0],
                        'FFFFFF',
                        $seriesColors[1],
                    ];
                }
            }

            $categoryRange =
                "'{$dataSheetTitle}'!\$A\${$dataStartRow}:\$A\${$dataEndRow}";

            $categoryCount =
                $dataEndRow - $dataStartRow + 1;

            $chartTopRow =
                $nextRow + 1;

            $chartBottomRow =
                $chartTopRow +
                ($chartHeightRows ?? self::CHART_HEIGHT_ROWS);

            /*
             * Judul di dalam kotak grafik dibuat lebih ringkas
             * daripada judul section (banner hijau) di atasnya:
             * awalan "C. "/"E. " dibuang supaya grafik Golongan
             * & Pendidikan cukup menampilkan "Golongan" /
             * "Pendidikan" saja, tanpa mengubah banner section
             * itu sendiri yang masih pakai $title asli.
             */
            $chartTitle =
                preg_replace(
                    '/^[A-Z]\.\s*/',
                    '',
                    $title
                );

            $this->addBarChart(
                $sheet,
                'chart_' .
                    preg_replace(
                        '/[^a-z0-9]+/i',
                        '_',
                        strtolower($title)
                    ) .
                    '_' .
                    $headerRow,
                $chartTitle,
                $categoryRange,
                $categoryCount,
                $series,
                $chartLeftCol . $chartTopRow,
                $chartRightCol . $chartBottomRow,
                showDataLabels: true,
                seriesColors: $seriesColors,
                horizontal: $horizontal
            );

            /*
             * Catatan: border "bingkai" kiri/kanan yang dulu
             * ditambahkan di sini untuk chart yang digeser ke
             * kanan (mis. Golongan & Pendidikan, $chartLeftCol
             * !== 'A') sudah DIHAPUS — border itu tampil sebagai
             * garis vertikal yang mengambang/terpisah dari chart
             * (tidak nyatu dengan kotak chart-nya sendiri), jadi
             * terlihat seperti garis nyasar, bukan bingkai yang
             * rapi.
             */

            $nextRow = $chartBottomRow;

            /*
             * Tabel pendamping di samping chart (opsional).
             * Judulnya sudah menyatu dengan judul chart di atas
             * (baris $startRow, terbentang sampai $titleLastCol),
             * jadi tabel di sini langsung mulai dari baris kolom
             * header ($chartTopRow, sejajar dengan bagian atas
             * chart) tanpa judul sendiri (skipTitle: true). Tinggi
             * akhir section mengikuti yang lebih panjang di antara
             * chart & tabel.
             */
            if (
                $sideTableHeadings !== null &&
                $sideTableRows !== null
            ) {
                $sideTableNextRow =
                    $this->drawTableSection(
                        $sheet,
                        $chartTopRow,
                        $title,
                        $sideTableHeadings,
                        $sideTableRows,
                        null,
                        $sideTableCol,
                        true
                    );

                $nextRow = max(
                    $nextRow,
                    $sideTableNextRow - 1
                );
            }
        }

        return $nextRow +
            self::CHART_SECTION_GAP_ROWS;
    }

    /*
     * =========================================================
     * BAR CHART
     * =========================================================
     */

    private function addBarChart(
        $sheet,
        string $chartId,
        string $title,
        string $categoryRange,
        int $categoryCount,
        array $series,
        string $topLeftCell,
        string $bottomRightCell,
        bool $showDataLabels = false,
        ?array $seriesColors = null,
        bool $horizontal = false
    ): void {
        $categoryLabels = [
            new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                $categoryRange,
                null,
                $categoryCount
            ),
        ];

        $seriesLabels = [];
        $seriesValues = [];

        foreach ($series as $i => $s) {
            $seriesLabels[] =
                new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_STRING,
                    $s['labelRef'],
                    null,
                    1
                );

            $valueSeries =
                new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_NUMBER,
                    $s['valueRange'],
                    null,
                    $categoryCount
                );

            if ($showDataLabels) {
                $valueSeries->setLabelLayout(
                    new Layout([
                        'showVal' => true,
                        'showCatName' => false,
                        'showSerName' => false,
                        'showPercent' => false,
                        'showLegendKey' => false,
                    ])
                );
            }

            if (isset($seriesColors[$i])) {
                $valueSeries->setFillColor(
                    $seriesColors[$i]
                );
            }

            $seriesValues[] =
                $valueSeries;
        }

        $dataSeries =
            new DataSeries(
                DataSeries::TYPE_BARCHART,
                DataSeries::GROUPING_CLUSTERED,
                range(
                    0,
                    count($seriesValues) - 1
                ),
                $seriesLabels,
                $categoryLabels,
                $seriesValues
            );

        /*
         * "bar" = chart tidur/horizontal (kategori di sumbu-Y,
         * nilai di sumbu-X); "col" = chart berdiri/vertical
         * (bawaan). Dipakai supaya chart tertentu (mis. Pendidikan)
         * bisa direbahkan tanpa mengubah chart lain yang tetap
         * berdiri.
         */
        $dataSeries->setPlotDirection(
            $horizontal
                ? DataSeries::DIRECTION_BAR
                : DataSeries::DIRECTION_COL
        );

        $plotArea =
            new PlotArea(
                null,
                [$dataSeries]
            );

        $legend =
            new Legend(
                Legend::POSITION_BOTTOM,
                null,
                false
            );

        $chartTitle =
            new ChartTitle($title);

        $chart =
            new Chart(
                $chartId,
                $chartTitle,
                $legend,
                $plotArea
            );

        $chart->setTopLeftPosition(
            $topLeftCell
        );

        $chart->setBottomRightPosition(
            $bottomRightCell
        );

        $sheet->addChart($chart);
    }
}
