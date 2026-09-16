<?php

namespace App\Exports;

use App\Services\StatistikService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Export panel "ASN Perangkat Daerah Berdasarkan Jenis Kelamin"
 * (5.03.012) — AsnPerangkatDaerahJenisKelaminPanel.jsx. Sumber data:
 * StatistikService::statistikAsnPerangkatDaerahJenisKelamin().
 *
 * Panel ini menampilkan ENAM tabel sekaligus (ringkasan per kelompok,
 * Sekretariat Daerah & Bagian, Dinas, Badan Daerah, lembaga lain, dan
 * Kemantren), jadi sheet-nya disusun bersusun: satu blok per tabel,
 * dipisah baris kosong, urutan & label persis seperti di layar.
 *
 * Key & label perangkat daerah di bawah ini SENGAJA disalin dari panel
 * React (bukan diambil dari $pd[...]['label']) supaya isi file Excel
 * sama persis dengan yang dibaca user di layar — beberapa label di
 * service memang ditulis lebih panjang (mis. "Sekretariat Dewan
 * Perwakilan Rakyat Daerah" vs "Sekretariat DPRD" di panel).
 *
 * Sama seperti StafDinasSkpdExport, seluruh isi sheet dibangun di
 * array() karena posisi header tiap blok bergantung panjang blok
 * sebelumnya.
 */
class AsnPerangkatDaerahJenisKelaminExport implements FromArray, WithEvents
{
    protected array $data;
    protected array $pd;
    protected array $rows = [];

    protected array $barisJudulBlok = [];
    protected array $barisHeader = [];
    protected array $barisTotal = [];
    protected array $rentangBlok = [];

    protected static array $bagianList = [
        'administrasi_dan_keuangan' => 'Bagian Administrasi dan Keuangan',
        'administrasi_pembangunan' => 'Bagian Administrasi Pembangunan',
        'hukum' => 'Bagian Hukum',
        'kesejahteraan_rakyat' => 'Bagian Kesejahteraan Rakyat',
        'organisasi' => 'Bagian Organisasi',
        'pengadaan_barang_dan_jasa' => 'Bagian Pengadaan Barang dan Jasa',
        'perekonomian_dan_kerjasama' => 'Bagian Perekonomian dan Kerjasama',
        'tata_pemerintahan' => 'Bagian Tata Pemerintahan',
        'umum_dan_protokol' => 'Bagian Umum dan Protokol',
    ];

    protected static array $dinasList = [
        'kebudayaan' => 'Dinas Kebudayaan',
        'kependudukan_dan_pencatatan_sipil' => 'Dinas Kependudukan dan Pencatatan Sipil',
        'kesehatan' => 'Dinas Kesehatan',
        'kominfo_persandian' => 'Dinas Komunikasi Informatika dan Persandian',
        'lingkungan_hidup' => 'Dinas Lingkungan Hidup',
        'pariwisata' => 'Dinas Pariwisata',
        'pupr' => 'Dinas Pekerjaan Umum Perumahan dan Kawasan Permukiman',
        'pemadam_kebakaran' => 'Dinas Pemadam Kebakaran dan Penyelamatan',
        'p3akb' => 'Dinas Pemberdayaan Perempuan Perlindungan Anak dan Pengendalian Penduduk dan KB',
        'dpmptsp' => 'Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu',
        'pendidikan_pemuda_olahraga' => 'Dinas Pendidikan Pemuda dan Olahraga',
        'perdagangan' => 'Dinas Perdagangan',
        'perhubungan' => 'Dinas Perhubungan',
        'perindustrian_koperasi_ukm' => 'Dinas Perindustrian Koperasi Usaha Kecil dan Menengah',
        'perpustakaan_dan_kearsipan' => 'Dinas Perpustakaan dan Kearsipan',
        'pertanahan_dan_tata_ruang' => 'Dinas Pertanahan dan Tata Ruang',
        'pertanian_dan_pangan' => 'Dinas Pertanian dan Pangan',
        'sosial_nakertrans' => 'Dinas Sosial Tenaga Kerja dan Transmigrasi',
    ];

    protected static array $badanList = [
        'bkpsdm' => 'Badan Kepegawaian dan Pengembangan Sumber Daya Manusia',
        'kesbangpol' => 'Badan Kesatuan Bangsa dan Politik',
        'bpbd' => 'Badan Penanggulangan Bencana Daerah',
        'bpkad' => 'Badan Pengelola Keuangan dan Aset Daerah',
        'bappeda' => 'Badan Perencanaan Pembangunan Daerah',
    ];

    protected static array $lembagaLainList = [
        'satpol_pp' => 'Satuan Polisi Pamong Praja',
        'inspektorat' => 'Inspektorat',
        'setwan' => 'Sekretariat DPRD',
        'rsud' => 'RSUD Kota Yogyakarta',
    ];

    protected static array $kemantrenList = [
        'TEGALREJO',
        'JETIS',
        'GONDOKUSUMAN',
        'DANUREJAN',
        'GEDONGTENGEN',
        'NGAMPILAN',
        'WIROBRAJAN',
        'MANTRIJERON',
        'KRATON',
        'GONDOMANAN',
        'PAKUALAMAN',
        'MERGANGSAN',
        'UMBULHARJO',
        'KOTAGEDE',
    ];

    public function __construct()
    {
        $this->data = (new StatistikService())
            ->statistikAsnPerangkatDaerahJenisKelamin();
        $this->pd = $this->data['perangkat_daerah'] ?? [];

        $this->buildRows();
    }

    protected function toTitleCase(string $text): string
    {
        return ucwords(strtolower($text));
    }

    /** Ambil satu entri perangkat daerah sebagai baris {label, L, P, total}. */
    protected function entri(string $key, string $label): array
    {
        $item = $this->pd[$key] ?? [];

        return [
            'label' => $label,
            'laki_laki' => (int) ($item['laki_laki'] ?? 0),
            'perempuan' => (int) ($item['perempuan'] ?? 0),
            'total' => (int) ($item['total'] ?? 0),
        ];
    }

    /** Jumlahkan beberapa key perangkat daerah jadi satu baris ringkasan. */
    protected function jumlahKelompok(string $label, array $keys): array
    {
        $lakiLaki = 0;
        $perempuan = 0;

        foreach ($keys as $key) {
            $lakiLaki += (int) ($this->pd[$key]['laki_laki'] ?? 0);
            $perempuan += (int) ($this->pd[$key]['perempuan'] ?? 0);
        }

        return [
            'label' => $label,
            'laki_laki' => $lakiLaki,
            'perempuan' => $perempuan,
            'total' => $lakiLaki + $perempuan,
        ];
    }

    protected function tambahBaris(array $baris): int
    {
        $this->rows[] = $baris;

        return count($this->rows);
    }

    /**
     * Satu blok tabel L/P/Total dengan baris Total yang dijumlah otomatis
     * dari baris-barisnya — sama seperti komponen GenderTable di panel.
     */
    protected function tambahBlok(string $judul, string $labelKolom, array $dataRows): void
    {
        $this->barisJudulBlok[] = $this->tambahBaris([$judul]);

        $awal = $this->tambahBaris(['No', $labelKolom, 'Laki-laki', 'Perempuan', 'Jumlah']);
        $this->barisHeader[] = $awal;

        $no = 1;
        $totalLakiLaki = 0;
        $totalPerempuan = 0;
        $totalJumlah = 0;

        foreach ($dataRows as $row) {
            $this->tambahBaris([
                $no++,
                $row['label'],
                $row['laki_laki'],
                $row['perempuan'],
                $row['total'],
            ]);

            $totalLakiLaki += $row['laki_laki'];
            $totalPerempuan += $row['perempuan'];
            $totalJumlah += $row['total'];
        }

        // Label ditaruh di kolom A karena A:B di-merge saat styling.
        $akhir = $this->tambahBaris(['Total', '', $totalLakiLaki, $totalPerempuan, $totalJumlah]);
        $this->barisTotal[] = $akhir;

        $this->rentangBlok[] = [$awal, $akhir];

        $this->tambahBaris(['']);
    }

    protected function buildRows(): void
    {
        $this->tambahBaris(['REKAPITULASI JUMLAH ASN PERANGKAT DAERAH']);
        $this->tambahBaris(['DIPERINCI MENURUT PERANGKAT DAERAH DAN JENIS KELAMIN']);
        $this->tambahBaris(['']);

        // Blok ringkasan. "Lainnya / Belum Terpetakan" sengaja ikut
        // ditampilkan supaya baris Total blok ini sama persis dengan
        // jumlah ASN Pemerintah Kota Yogyakarta secara keseluruhan —
        // tidak ada angka yang dihitung tapi disembunyikan.
        $this->tambahBlok(
            'Perbandingan ASN per Kelompok Perangkat Daerah',
            'Kelompok Perangkat Daerah',
            [
                $this->jumlahKelompok('Sekretariat Daerah & Bagian', array_merge(
                    ['sekretariat_daerah'],
                    array_keys(self::$bagianList)
                )),
                $this->jumlahKelompok('Dinas', array_keys(self::$dinasList)),
                $this->jumlahKelompok('Badan Daerah', array_keys(self::$badanList)),
                $this->jumlahKelompok('Lembaga Lain', array_keys(self::$lembagaLainList)),
                $this->entri('kemantren', 'Kemantren'),
                $this->entri('lainnya', 'Lainnya / Belum Terpetakan'),
            ]
        );

        $sekretariatRows = [$this->entri('sekretariat_daerah', 'Sekretariat Daerah')];
        foreach (self::$bagianList as $key => $label) {
            $sekretariatRows[] = $this->entri($key, $label);
        }
        $this->tambahBlok('Sekretariat Daerah & Bagian', 'Unit', $sekretariatRows);

        $this->tambahBlok('Dinas', 'Dinas', array_map(
            fn($key, $label) => $this->entri($key, $label),
            array_keys(self::$dinasList),
            array_values(self::$dinasList)
        ));

        $this->tambahBlok('Badan Daerah', 'Badan', array_map(
            fn($key, $label) => $this->entri($key, $label),
            array_keys(self::$badanList),
            array_values(self::$badanList)
        ));

        $this->tambahBlok(
            'Satpol PP, Inspektorat, Sekretariat DPRD & RSUD',
            'Unit',
            array_map(
                fn($key, $label) => $this->entri($key, $label),
                array_keys(self::$lembagaLainList),
                array_values(self::$lembagaLainList)
            )
        );

        $kemantrenDetail = $this->pd['kemantren']['detail'] ?? [];
        $kemantrenRows = [];

        foreach (self::$kemantrenList as $nama) {
            $item = $kemantrenDetail[$nama] ?? [];

            $kemantrenRows[] = [
                'label' => 'Kemantren ' . $this->toTitleCase($nama),
                'laki_laki' => (int) ($item['laki_laki'] ?? 0),
                'perempuan' => (int) ($item['perempuan'] ?? 0),
                'total' => (int) ($item['total'] ?? 0),
            ];
        }

        $this->tambahBlok('Kemantren', 'Kemantren', $kemantrenRows);
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

                $sheet->mergeCells('A1:E1');
                $sheet->mergeCells('A2:E2');
                $sheet->getStyle('A1:A2')->getFont()->setBold(true);

                foreach ($this->barisJudulBlok as $baris) {
                    $sheet->mergeCells("A{$baris}:E{$baris}");
                    $sheet->getStyle("A{$baris}")->getFont()->setBold(true);
                }

                foreach ($this->barisHeader as $baris) {
                    $sheet->getStyle("A{$baris}:E{$baris}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$baris}:E{$baris}")->getAlignment()
                        ->setHorizontal('center');
                }

                foreach ($this->barisTotal as $baris) {
                    $sheet->getStyle("A{$baris}:E{$baris}")->getFont()->setBold(true);
                    $sheet->mergeCells("A{$baris}:B{$baris}");
                }

                foreach ($this->rentangBlok as [$awal, $akhir]) {
                    $sheet->getStyle("A{$awal}:E{$akhir}")->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle("C{$awal}:E{$akhir}")->getAlignment()
                        ->setHorizontal('center');
                }

                foreach (['A' => 6, 'B' => 56, 'C' => 12, 'D' => 12, 'E' => 12] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}
