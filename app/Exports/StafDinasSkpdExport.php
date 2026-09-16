<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export panel "Pegawai Berdasarkan Tingkat Pendidikan dan SKPD
 * (18 Dinas)" — SkpdDinasPanel.jsx. Beda dengan export statistik lain
 * yang cuma punya satu tabel, panel ini menampilkan EMPAT tabel sekaligus
 * dari empat/lima endpoint berbeda, jadi sheet-nya disusun bersusun:
 * satu blok per tabel, dipisah baris kosong, dengan urutan & isi persis
 * sama seperti di layar.
 *
 * Sumber data (semua dari StatistikService, scope 18 Dinas, tanpa
 * pemisahan gender):
 * - statistikStafDinasPendidikan()   -> Staf per tingkat pendidikan
 * - statistikStafDinasGolongan()     -> Staf per golongan
 * - statistikPejabatStrukturalDinas() -> Pejabat struktural per eselon
 * - statistikPejabatFungsionalDinas() + statistikPensiunanDinas()
 *   -> blok "Ringkasan Lainnya"
 *
 * Berbeda dari export lain di folder ini, seluruh isi sheet (termasuk
 * baris judul & header) dibangun langsung di array() — bukan lewat
 * insertNewRowBefore() di AfterSheet — karena posisi header tiap blok
 * bergantung pada panjang blok sebelumnya, jadi lebih aman kalau
 * indeksnya dicatat saat baris dibuat.
 */
class StafDinasSkpdExport implements FromArray, WithEvents
{
    protected array $rows = [];

    /** Nomor baris (1-based) yang perlu dicetak tebal sebagai judul blok. */
    protected array $barisJudulBlok = [];

    /** Nomor baris (1-based) yang berisi header kolom tiap blok. */
    protected array $barisHeader = [];

    /** Nomor baris (1-based) yang berisi baris Total tiap blok. */
    protected array $barisTotal = [];

    /** Rentang [awal, akhir] tiap blok tabel, untuk garis tabel. */
    protected array $rentangBlok = [];

    // HARUS sama persis (key & urutan) dengan PENDIDIKAN_STAF_LIST di
    // resources/js/pages/Statistik/SkpdDinasPanel.jsx — perhatikan daftar
    // ini BEDA dengan PENDIDIKAN_LIST yang dipakai panel ASN/PNS/PPPK
    // (di sini Diploma tidak dipecah I/II/III/IV).
    protected static array $pendidikanList = [
        'sd' => 'Tamat SD atau sederajat',
        'smp' => 'SMP dan sederajat',
        'sma' => 'SMA dan sederajat',
        'diploma' => 'Diploma',
        'strata_1' => 'Strata I',
        'strata_2' => 'Strata 2',
        'strata_3' => 'Strata 3',
    ];

    protected static array $golonganList = ['I', 'II', 'III', 'IV'];
    protected static array $eselonList = ['I', 'II', 'III', 'IV'];

    public function __construct(protected ?string $periode = null)
    {
        $service = new StatistikService();

        $pendidikan = $service->statistikStafDinasPendidikan($periode);
        $golongan = $service->statistikStafDinasGolongan($periode);
        $struktural = $service->statistikPejabatStrukturalDinas($periode);
        $fungsional = $service->statistikPejabatFungsionalDinas($periode);
        $pensiunan = $service->statistikPensiunanDinas();

        $this->buildRows($pendidikan, $golongan, $struktural, $fungsional, $pensiunan);
    }

    protected function tambahBaris(array $baris): int
    {
        $this->rows[] = $baris;

        return count($this->rows);
    }

    /**
     * Satu blok tabel: judul, header kolom, baris data, dan (opsional)
     * baris Total — lalu satu baris kosong sebagai pemisah.
     *
     * @param array<int, array{label: string, value: int}> $dataRows
     */
    protected function tambahBlok(
        string $judul,
        string $labelKolom,
        array $dataRows,
        ?int $total = null,
        string $labelTotal = 'Total'
    ): void {
        $this->barisJudulBlok[] = $this->tambahBaris([$judul]);

        $awal = $this->tambahBaris(['No', $labelKolom, 'Jumlah']);
        $this->barisHeader[] = $awal;
        $akhir = $awal;

        $no = 1;
        foreach ($dataRows as $row) {
            $akhir = $this->tambahBaris([$no++, $row['label'], (int) $row['value']]);
        }

        if ($total !== null) {
            // Label ditaruh di kolom A karena A:B di-merge saat styling —
            // kalau ditaruh di B nilainya ikut hilang waktu merge.
            $akhir = $this->tambahBaris([$labelTotal, '', $total]);
            $this->barisTotal[] = $akhir;
        }

        $this->rentangBlok[] = [$awal, $akhir];

        $this->tambahBaris(['']);
    }

    protected function buildRows(
        array $pendidikan,
        array $golongan,
        array $struktural,
        array $fungsional,
        array $pensiunan
    ): void {
        $this->tambahBaris(['REKAPITULASI PEGAWAI KANTOR DINAS DAERAH (18 DINAS)']);
        $this->tambahBaris(['DIPERINCI MENURUT TINGKAT PENDIDIKAN, GOLONGAN, DAN ESELON']);
        $this->tambahBaris(['']);

        $this->tambahBlok(
            'Staf Kantor Dinas Daerah Berdasarkan Tingkat Pendidikan',
            'Tingkat Pendidikan',
            array_map(
                fn($key, $label) => ['label' => $label, 'value' => $pendidikan[$key] ?? 0],
                array_keys(self::$pendidikanList),
                array_values(self::$pendidikanList)
            ),
            (int) ($pendidikan['jumlah_staf_dinas'] ?? 0)
        );

        $this->tambahBlok(
            'Staf Kantor Dinas Daerah Berdasarkan Golongan',
            'Golongan',
            array_map(
                fn($g) => [
                    'label' => 'Golongan ' . $g,
                    'value' => $golongan['golongan'][$g] ?? 0,
                ],
                self::$golonganList
            ),
            (int) ($golongan['jumlah_staf_dinas'] ?? 0)
        );

        $this->tambahBlok(
            'Pejabat Struktural Kantor Dinas Daerah Berdasarkan Eselon',
            'Eselon',
            array_map(
                fn($e) => [
                    'label' => 'Eselon ' . $e,
                    'value' => $struktural['eselon'][$e] ?? 0,
                ],
                self::$eselonList
            ),
            (int) ($struktural['jumlah_pejabat_struktural'] ?? 0)
        );

        // Tanpa baris Total — sama seperti tabel "Ringkasan Lainnya" di
        // panel: dua kategori berbeda jenis, tidak dijumlahkan.
        $this->tambahBlok(
            'Ringkasan Lainnya',
            'Kategori',
            [
                [
                    'label' => 'Pejabat Fungsional Kantor Dinas Daerah',
                    'value' => $fungsional['jumlah_pejabat_fungsional'] ?? 0,
                ],
                [
                    'label' => 'Pensiunan Kantor Dinas Daerah',
                    'value' => $pensiunan['jumlah_pensiunan'] ?? 0,
                ],
            ]
        );
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->mergeCells('A1:C1');
                $sheet->mergeCells('A2:C2');
                $sheet->getStyle('A1:A2')->getFont()->setBold(true);

                foreach ($this->barisJudulBlok as $baris) {
                    $sheet->mergeCells("A{$baris}:C{$baris}");
                    $sheet->getStyle("A{$baris}")->getFont()->setBold(true);
                }

                foreach ($this->barisHeader as $baris) {
                    $sheet->getStyle("A{$baris}:C{$baris}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$baris}:C{$baris}")->getAlignment()
                        ->setHorizontal('center');
                }

                foreach ($this->barisTotal as $baris) {
                    $sheet->getStyle("A{$baris}:C{$baris}")->getFont()->setBold(true);
                    $sheet->mergeCells("A{$baris}:B{$baris}");
                }

                foreach ($this->rentangBlok as [$awal, $akhir]) {
                    $sheet->getStyle("A{$awal}:C{$akhir}")->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle("C{$awal}:C{$akhir}")->getAlignment()
                        ->setHorizontal('center');
                }

                foreach (['A' => 6, 'B' => 46, 'C' => 12] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}
