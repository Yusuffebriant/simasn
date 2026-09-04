<?php

namespace App\Services;

use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RekapService
{
    public function rekapAgama(?string $periode = null): array
    {
        $agamaList = ['Islam', 'Kristen', 'Katholik', 'Hindu', 'Budha'];

        $rows = Pegawai::query()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->join('agama', 'agama.id', '=', 'pegawai.agama_id')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'agama.nama as agama_nama',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->groupBy(
                'instansi.id',
                'instansi.nama',
                'agama.nama',
                'pegawai.jenis_kelamin'
            )
            ->get();

        $perInstansi = [];

        foreach ($rows as $row) {
            $id = $row->instansi_id;

            if (!isset($perInstansi[$id])) {
                $perInstansi[$id] = [
                    'instansi' => $row->instansi_nama,
                    'pria' => array_fill_keys($agamaList, 0),
                    'wanita' => array_fill_keys($agamaList, 0),
                ];
            }

            $kelompok = $row->jenis_kelamin === 'L'
                ? 'pria'
                : 'wanita';

            if (in_array($row->agama_nama, $agamaList)) {
                $perInstansi[$id][$kelompok][$row->agama_nama]
                    = (int) $row->jumlah;
            }
        }

        foreach ($perInstansi as &$data) {
            $data['jml_pria'] = array_sum($data['pria']);
            $data['jml_wanita'] = array_sum($data['wanita']);
            $data['jml_total'] =
                $data['jml_pria'] + $data['jml_wanita'];
        }

        return array_values($perInstansi);
    }

    public function rekapPendidikan(?string $periode = null): array
    {
        // Sesuai laporan final: 10 kategori, tanpa kolom "BELUM DIISI".
        $pendidikanList = [
            'SD',
            'SLTP',
            'SLTA',
            'D I',
            'D II',
            'D III',
            'D IV',
            'S1',
            'S2',
            'S3',
        ];

        $rows = Pegawai::query()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->leftJoin('pendidikan', 'pendidikan.id', '=', 'pegawai.pendidikan_id')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'pendidikan.jenjang as pendidikan_nama',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->groupBy(
                'instansi.id',
                'instansi.nama',
                'pendidikan.jenjang',
                'pegawai.jenis_kelamin'
            )
            ->get();

        $perInstansi = [];

        foreach ($rows as $row) {
            $id = $row->instansi_id;

            if (!isset($perInstansi[$id])) {
                $perInstansi[$id] = [
                    'instansi' => $row->instansi_nama,
                    'pria' => array_fill_keys($pendidikanList, 0),
                    'wanita' => array_fill_keys($pendidikanList, 0),
                    'tidak_dikenali' => 0,
                ];
            }

            $pendidikanRaw = $row->pendidikan_nama
                ? strtoupper(trim($row->pendidikan_nama))
                : null;

            // Daftar varian teks di bawah dikonfirmasi dari data mentah
            // (kolom TINGKAT_PENDIDIKAN) per Agustus 2026.
            $pendidikan = $pendidikanRaw ? match ($pendidikanRaw) {
                'SD', 'SEKOLAH DASAR'                              => 'SD',
                'SMP', 'SLTP'                                      => 'SLTP',
                'SMA', 'SMK', 'SMA/SMK', 'SLTA', 'SLTA KEJURUAN'   => 'SLTA',
                'D1', 'D-1', 'D I', 'DIPLOMA I'                     => 'D I',
                'D2', 'D-2', 'D II', 'DIPLOMA II'                   => 'D II',
                'D3', 'D-3', 'D III', 'DIPLOMA III/SARJANA'         => 'D III',
                'D4', 'D-4', 'D IV', 'D4/S1', 'DIPLOMA IV'          => 'D IV',
                'S1', 'S-1', 'S-1/SARJANA', 'SARJANA'               => 'S1',
                'S2', 'S-2', 'S-2/MAGISTER'                         => 'S2',
                'S3', 'S-3', 'S-3/DOKTOR'                           => 'S3',
                default => null,
            } : null;

            $kelompok = $row->jenis_kelamin === 'L' ? 'pria' : 'wanita';
            $jumlah = (int) $row->jumlah;

            if ($pendidikan === null) {
                // Jenjang kosong ATAU varian teks baru yang belum ada di
                // mapping di atas. Tetap dihitung ke jml_total supaya total
                // pegawai tetap akurat, tapi tidak masuk kolom manapun.
                // Cek log untuk menemukan varian baru yang perlu ditambahkan.
                $perInstansi[$id]['tidak_dikenali'] += $jumlah;

                if ($pendidikanRaw !== null) {
                    Log::warning('RekapPendidikan: jenjang pendidikan tidak dikenali', [
                        'instansi_id' => $id,
                        'pendidikan_raw' => $row->pendidikan_nama,
                        'jumlah' => $jumlah,
                    ]);
                }

                continue;
            }

            $perInstansi[$id][$kelompok][$pendidikan] =
                ($perInstansi[$id][$kelompok][$pendidikan] ?? 0) + $jumlah;
        }

        foreach ($perInstansi as &$data) {
            $data['jml_pria'] = array_sum($data['pria']);
            $data['jml_wanita'] = array_sum($data['wanita']);
            $data['jml_total'] =
                $data['jml_pria'] + $data['jml_wanita'] + $data['tidak_dikenali'];
        }

        return array_values($perInstansi);
    }

    public function rekapGolongan(?string $periode = null): array
{
    $golonganList = [
        'I/a', 'I/b', 'I/c', 'I/d',
        'II/a', 'II/b', 'II/c', 'II/d',
        'III/a', 'III/b', 'III/c', 'III/d',
        'IV/a', 'IV/b', 'IV/c', 'IV/d',
        'IV/e',
        'BELUM DIISI',
    ];
    $pppkList = ['I', 'III', 'V', 'VII', 'IX', 'X', 'XI'];

    // Kolom pria/wanita mencakup golongan PNS + kode PPPK + BELUM DIISI,
    // supaya PPPK ikut menyumbang ke jml_total (sesuai format laporan
    // resmi: Sub Total Pria/Wanita = PNS + PPPK digabung).
    $kolomList = array_merge($golonganList, $pppkList);

    $rows = Pegawai::query()
        ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
        ->leftJoin('golongan_ruang', 'golongan_ruang.id', '=', 'pegawai.golongan_ruang_id') // diubah dari join: golongan_ruang_id nullable
        ->select(
            'instansi.id as instansi_id',
            'instansi.nama as instansi_nama',
            'golongan_ruang.kode as golongan_kode',
            'golongan_ruang.kelompok as golongan_kelompok',
            'pegawai.jenis_kelamin',
            DB::raw('COUNT(*) as jumlah')
        )
        ->where('pegawai.status_aktif', 'aktif')
        ->groupBy(
            'instansi.id',
            'instansi.nama',
            'golongan_ruang.kode',
            'golongan_ruang.kelompok',
            'pegawai.jenis_kelamin'
        )
        ->get();

    $perInstansi = [];

    foreach ($rows as $row) {
        $id = $row->instansi_id;

        if (!isset($perInstansi[$id])) {
            $perInstansi[$id] = [
                'instansi' => $row->instansi_nama,
                'pria' => array_fill_keys($kolomList, 0),
                'wanita' => array_fill_keys($kolomList, 0),
                'pppk' => array_fill_keys($pppkList, 0),
            ];
        }

        $jumlah = (int) $row->jumlah;
        $kelompok = $row->jenis_kelamin === 'L' ? 'pria' : 'wanita';

        // golongan_kode NULL berarti golongan_ruang_id pegawai NULL (belum diisi)
        if ($row->golongan_kode === null) {
            $perInstansi[$id][$kelompok]['BELUM DIISI'] =
                ($perInstansi[$id][$kelompok]['BELUM DIISI'] ?? 0) + $jumlah;
            continue;
        }

        $kode = trim($row->golongan_kode);

        if ($row->golongan_kelompok === 'PPPK') {
            if (!in_array($kode, $pppkList, true)) {
                continue;
            }

            // Masuk ke kolom Pria/Wanita utama supaya ikut jml_total...
            $perInstansi[$id][$kelompok][$kode] =
                ($perInstansi[$id][$kelompok][$kode] ?? 0) + $jumlah;

            // ...sekaligus tetap dicatat di ringkasan PPPK terpisah
            // (dipakai untuk pppk_total, bukan dipecah gender).
            $perInstansi[$id]['pppk'][$kode] =
                ($perInstansi[$id]['pppk'][$kode] ?? 0) + $jumlah;

            continue;
        }

        if (!in_array($kode, $golonganList, true)) {
            continue;
        }

        $perInstansi[$id][$kelompok][$kode] =
            ($perInstansi[$id][$kelompok][$kode] ?? 0) + $jumlah;
    }

    foreach ($perInstansi as &$data) {
        $data['jml_pria'] = array_sum($data['pria']);
        $data['jml_wanita'] = array_sum($data['wanita']);
        $data['jml_total'] = $data['jml_pria'] + $data['jml_wanita'];

        // Agregat PNS per romawi utama (I, II, III, IV) — HANYA dari kode
        // bergaya PNS (mengandung '/'), supaya kode PPPK ('I','III',dst
        // tanpa slash) dan 'BELUM DIISI' tidak ikut kehitung di sini.
        //
        // PENTING: gabungkan pria+wanita PER KEY (bukan array_map posisional),
        // karena array_map(fn, $a, $b) mereset key ke index angka 0,1,2,...
        // dan membuat str_contains($kode, '/') di bawah selalu false
        // (sehingga pns_agg selalu kosong/0).
        $gabungan = [];
        foreach ($data['pria'] as $kode => $jumlah) {
            $gabungan[$kode] = $jumlah + ($data['wanita'][$kode] ?? 0);
        }

        $pnsAgg = ['I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0];
        foreach ($gabungan as $kode => $jumlah) {
            if (!str_contains($kode, '/')) {
                continue;
            }
            $romawi = explode('/', $kode)[0];
            if (isset($pnsAgg[$romawi])) {
                $pnsAgg[$romawi] += $jumlah;
            }
        }
        $data['pns_agg'] = $pnsAgg;
        $data['pns_total'] = array_sum($pnsAgg);

        $data['pppk_total'] = array_sum($data['pppk']);
    }

    return array_values($perInstansi);
}

    public function rekapJabatan(?string $periode = null): array
    {
        // Klasifikasi Fungsional Umum vs Fungsional Tertentu memakai
        // pegawai.jenis_kedudukan langsung (nilai: 'JABATAN FUNGSIONAL',
        // 'JABATAN PELAKSANA', 'STRUKTURAL', dst) — BUKAN menebak dari teks
        // bebas pegawai.jabatan lewat regex. Baris Eselon tetap ditentukan
        // dari eselon.kode seperti sebelumnya (sudah akurat).
        $rows = Pegawai::query()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->leftJoin('eselon', 'eselon.id', '=', 'pegawai.eselon_id')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'pegawai.jenis_kedudukan',
                'eselon.kode as eselon_kode',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->groupBy(
                'instansi.id',
                'instansi.nama',
                'pegawai.jenis_kedudukan',
                'eselon.kode'
            )
            ->get();

        $eselonList = ['II A', 'II B', 'III A', 'III B', 'IV A', 'IV B'];
        $perInstansi = [];

        foreach ($rows as $row) {
            $id = $row->instansi_id;

            if (!isset($perInstansi[$id])) {
                $perInstansi[$id] = [
                    'instansi' => $row->instansi_nama,
                    'eselon' => array_fill_keys($eselonList, 0),
                    'fungsional_umum' => 0,
                    'fungsional_tertentu' => 0,
                ];
            }

            $jumlah = (int) $row->jumlah;
            $kodeEselon = strtoupper(trim((string) $row->eselon_kode));
            $kodeEselon = preg_replace('/\s+/', ' ', $kodeEselon);
            $jenisKedudukan = strtoupper(trim((string) $row->jenis_kedudukan));

            if (in_array($kodeEselon, $eselonList, true)) {
                $perInstansi[$id]['eselon'][$kodeEselon] += $jumlah;
            } elseif ($jenisKedudukan === 'FUNGSIONAL') {
                $perInstansi[$id]['fungsional_tertentu'] += $jumlah;
            } else {
                // 'PELAKSANA', 'STRUKTURAL' (yang lolos cek eselon di atas
                // karena data eselon-nya kosong/tidak baku), NULL, atau
                // varian lain masuk default ke Fungsional Umum supaya
                // JML TOTAL tetap konsisten dengan total pegawai aktif
                // (sesuai laporan resmi).
                $perInstansi[$id]['fungsional_umum'] += $jumlah;
            }
        }

        foreach ($perInstansi as &$data) {
            $data['jml_eselon'] = array_sum($data['eselon']);
            $data['jml_total'] = $data['jml_eselon']
                + $data['fungsional_umum']
                + $data['fungsional_tertentu'];
        }

        return array_values($perInstansi);
    }

public function rekapEselonGolonganGender(?string $periode = null): array
{
    $golonganList = ['III/a','III/b','III/c','III/d','IV/a','IV/b','IV/c','IV/d','IV/e'];
    $eselonList = ['II A', 'II B', 'III A', 'III B', 'IV A', 'IV B'];

    $rows = DB::table('eselon')
        ->leftJoin('pegawai', function ($join) {
            $join->on('pegawai.eselon_id', '=', 'eselon.id')
                 ->where('pegawai.status_aktif', '=', 'aktif');
        })
        ->leftJoin('golongan_ruang', 'golongan_ruang.id', '=', 'pegawai.golongan_ruang_id')
        ->select(
            'eselon.kode as eselon_kode',
            'golongan_ruang.kode as golongan_kode',
            'pegawai.jenis_kelamin',
            DB::raw('COUNT(pegawai.id) as jumlah')
        )
        ->whereIn('eselon.kode', $eselonList)
        ->groupBy('eselon.kode', 'golongan_ruang.kode', 'pegawai.jenis_kelamin')
        ->get();

    $perEselon = [];
    foreach ($eselonList as $kode) {
        $perEselon[$kode] = [
            'eselon' => $kode,
            'pria' => array_fill_keys($golonganList, 0),
            'wanita' => array_fill_keys($golonganList, 0),
        ];
    }

    foreach ($rows as $row) {
        if (!$row->golongan_kode || !in_array($row->golongan_kode, $golonganList) || !$row->jenis_kelamin) {
            continue;
        }
        $kelompok = $row->jenis_kelamin === 'L' ? 'pria' : 'wanita';
        $perEselon[$row->eselon_kode][$kelompok][$row->golongan_kode] = (int) $row->jumlah;
    }

    foreach ($perEselon as &$data) {
        $data['jml_pria'] = array_sum($data['pria']);
        $data['jml_wanita'] = array_sum($data['wanita']);
        $data['jml_total'] = $data['jml_pria'] + $data['jml_wanita'];
    }

    return array_values($perEselon);
}

    /**
     * Statistik Pejabat Struktural (Eselon II/III/IV) per jenis kelamin.
     *
     * Pejabat struktural ditentukan dari eselon.kode (bukan
     * pegawai.jenis_kedudukan) karena kolom jenis_kedudukan pada data
     * production saat ini masih NULL untuk pegawai lama (belum backfill).
     * eselon.kode sudah terbukti akurat di rekapJabatan()/rekapEselonGolonganGender().
     *
     * $periode belum dipakai untuk filter (tabel pegawai belum punya kolom
     * periode/tahun) — parameter dipertahankan untuk konsistensi dengan
     * method rekap* lain di service ini.
     */
    public function statistikPejabatStruktural(?string $periode = null): array
    {
        $eselonMap = [
            'II A' => 'II', 'II B' => 'II',
            'III A' => 'III', 'III B' => 'III',
            'IV A' => 'IV', 'IV B' => 'IV',
        ];

        $rows = Pegawai::query()
            ->join('eselon', 'eselon.id', '=', 'pegawai.eselon_id')
            ->select(
                'eselon.kode as eselon_kode',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->whereIn('eselon.kode', array_keys($eselonMap))
            ->groupBy('eselon.kode', 'pegawai.jenis_kelamin')
            ->get();

        $agregat = [
            'II' => ['laki_laki' => 0, 'perempuan' => 0],
            'III' => ['laki_laki' => 0, 'perempuan' => 0],
            'IV' => ['laki_laki' => 0, 'perempuan' => 0],
        ];

        foreach ($rows as $row) {
            $tingkat = $eselonMap[$row->eselon_kode] ?? null;

            if (!$tingkat) {
                continue;
            }

            $gender = $row->jenis_kelamin === 'L' ? 'laki_laki' : 'perempuan';
            $agregat[$tingkat][$gender] += (int) $row->jumlah;
        }

        $totalII = $agregat['II']['laki_laki'] + $agregat['II']['perempuan'];
        $totalIII = $agregat['III']['laki_laki'] + $agregat['III']['perempuan'];
        $totalIV = $agregat['IV']['laki_laki'] + $agregat['IV']['perempuan'];

        return [
            'jumlah_pejabat_struktural' => $totalII + $totalIII + $totalIV,
            'eselon_ii' => [
                'total' => $totalII,
                'laki_laki' => $agregat['II']['laki_laki'],
                'perempuan' => $agregat['II']['perempuan'],
            ],
            'eselon_iii' => [
                'total' => $totalIII,
                'laki_laki' => $agregat['III']['laki_laki'],
                'perempuan' => $agregat['III']['perempuan'],
            ],
            'eselon_iv' => [
                'total' => $totalIV,
                'laki_laki' => $agregat['IV']['laki_laki'],
                'perempuan' => $agregat['IV']['perempuan'],
            ],
        ];
    }

    /**
     * Statistik Pejabat Fungsional (Umum & Tertentu) per jenis kelamin.
     *
     * Sumber klasifikasi: pegawai.jenis_kedudukan
     *   'PELAKSANA'  => Fungsional Umum
     *   'FUNGSIONAL' => Fungsional Tertentu
     *   'STRUKTURAL' / NULL => sengaja TIDAK dihitung di sini (struktural
     *   sudah dihitung terpisah di statistikPejabatStruktural(); NULL berarti
     *   data belum diklasifikasi / belum backfill).
     *
     * PENTING: kolom jenis_kedudukan pada data production saat ini masih
     * NULL untuk pegawai lama sampai proses backfill/re-import selesai
     * dijalankan (lihat pembahasan sebelumnya). Method ini akan
     * mengembalikan 0 untuk fungsional_umum & fungsional_tertentu sampai
     * backfill itu selesai — ini BUKAN bug di query.
     *
     * Breakdown Dosen/Guru/Medis/Teknis: lihat method rumpunJabatanFungsional()
     * di bawah — berbasis pencocokan teks pegawai.jabatan yang sudah
     * divalidasi terhadap data nyata dan angka referensi BKPSDM.
     *
     * $periode belum dipakai untuk filter (tabel pegawai belum punya kolom
     * periode/tahun), dipertahankan untuk konsistensi dengan method
     * statistikPejabatStruktural() dan rekap* lainnya.
     */
    public function statistikPejabatFungsional(?string $periode = null): array
    {
        $rows = Pegawai::query()
            ->select(
                'jenis_kedudukan',
                'jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('status_aktif', 'aktif')
            ->groupBy('jenis_kedudukan', 'jenis_kelamin')
            ->get();

        $umum = ['laki_laki' => 0, 'perempuan' => 0];
        $tertentu = ['laki_laki' => 0, 'perempuan' => 0];

        foreach ($rows as $row) {
            $gender = $row->jenis_kelamin === 'L' ? 'laki_laki' : 'perempuan';
            $jumlah = (int) $row->jumlah;

            if ($row->jenis_kedudukan === 'PELAKSANA') {
                $umum[$gender] += $jumlah;
            } elseif ($row->jenis_kedudukan === 'FUNGSIONAL') {
                $tertentu[$gender] += $jumlah;
            }
            // 'STRUKTURAL' dan NULL sengaja diabaikan, lihat docblock.
        }

        $totalUmum = $umum['laki_laki'] + $umum['perempuan'];
        $totalTertentu = $tertentu['laki_laki'] + $tertentu['perempuan'];

        $rumpun = $this->rumpunJabatanFungsional();

        return [
            'jumlah_fungsional_umum' => $totalUmum,
            'fungsional_umum' => [
                'total' => $totalUmum,
                'laki_laki' => $umum['laki_laki'],
                'perempuan' => $umum['perempuan'],
            ],
            'jumlah_fungsional_tertentu' => $totalTertentu,
            'fungsional_tertentu' => [
                'total' => $totalTertentu,
                'laki_laki' => $tertentu['laki_laki'],
                'perempuan' => $tertentu['perempuan'],
            ],
            'fungsional_tertentu_laki_laki' => [
                'dosen' => $rumpun['dosen']['laki_laki'],
                'guru' => $rumpun['guru']['laki_laki'],
                'medis' => $rumpun['medis']['laki_laki'],
                'teknis' => $rumpun['teknis']['laki_laki'],
            ],
            'fungsional_tertentu_perempuan' => [
                'dosen' => $rumpun['dosen']['perempuan'],
                'guru' => $rumpun['guru']['perempuan'],
                'medis' => $rumpun['medis']['perempuan'],
                'teknis' => $rumpun['teknis']['perempuan'],
            ],
        ];
    }

    
    protected function rumpunJabatanFungsional(): array
    {
        $medisKeywords = [
            'DOKTER', 'PERAWAT', 'BIDAN', 'APOTEKER', 'PEREKAM MEDIS',
            'PRANATA LABORATORIUM KESEHATAN', 'NUTRISIONIS', 'EPIDEMIOLOG',
            'ADMINISTRATOR KESEHATAN', 'PENYULUH KESEHATAN', 'RADIOGRAFER',
            'TEKNISI TRANSFUSI DARAH', 'SANITARIAN', 'TERAPIS GIGI',
            'MEDIK VETERINER', 'PARAMEDIK VETERINER', 'PROMOSI KESEHATAN',
            'SANITASI LINGKUNGAN', 'FISIOTERAPIS', 'FISIKAWAN MEDIS',
            'PSIKOLOG KLINIS', 'TEKNISI ELEKTROMEDIS', 'TERAPIS WICARA',
            'PENATA ANESTESI', 'OKUPASI TERAPIS', 'PEMBIMBING KESEHATAN KERJA',
        ];

        $rows = Pegawai::query()
            ->select('jabatan', 'jenis_kelamin', DB::raw('COUNT(*) as jumlah'))
            ->where('status_aktif', 'aktif')
            ->where('jenis_kedudukan', 'FUNGSIONAL')
            ->groupBy('jabatan', 'jenis_kelamin')
            ->get();

        $rumpun = [
            'dosen' => ['laki_laki' => 0, 'perempuan' => 0],
            'guru' => ['laki_laki' => 0, 'perempuan' => 0],
            'medis' => ['laki_laki' => 0, 'perempuan' => 0],
            'teknis' => ['laki_laki' => 0, 'perempuan' => 0],
        ];

        foreach ($rows as $row) {
            $jabatan = strtoupper(trim((string) $row->jabatan));
            $gender = $row->jenis_kelamin === 'L' ? 'laki_laki' : 'perempuan';
            $jumlah = (int) $row->jumlah;

            if (str_starts_with($jabatan, 'GURU')) {
                $rumpun['guru'][$gender] += $jumlah;
            } elseif (str_contains($jabatan, 'DOSEN')) {
                $rumpun['dosen'][$gender] += $jumlah;
            } elseif ($this->containsAny($jabatan, $medisKeywords)) {
                $rumpun['medis'][$gender] += $jumlah;
            } else {
                $rumpun['teknis'][$gender] += $jumlah;
            }
        }

        return $rumpun;
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}