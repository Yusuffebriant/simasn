<?php

namespace App\Services;

use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StatistikService
{

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
                'auditor' => $rumpun['auditor']['laki_laki'],
                'p2upd' => $rumpun['p2upd']['laki_laki'],
                'medis' => $rumpun['medis']['laki_laki'],
                'teknis' => $rumpun['teknis']['laki_laki'],
            ],
            'fungsional_tertentu_perempuan' => [
                'dosen' => $rumpun['dosen']['perempuan'],
                'guru' => $rumpun['guru']['perempuan'],
                'auditor' => $rumpun['auditor']['perempuan'],
                'p2upd' => $rumpun['p2upd']['perempuan'],
                'medis' => $rumpun['medis']['perempuan'],
                'teknis' => $rumpun['teknis']['perempuan'],
            ],
        ];
    }

/**
     * Statistik Pensiunan PNS per golongan (I/II/III/IV) dan jenis kelamin,
     * untuk TAHUN tertentu.
     *
     * Pensiunan ditentukan dari RANGE pegawai.tanggal_pensiun pada tahun yang
     * diminta ([$tahun-01-01, $tahun+1-01-01)) — BUKAN dari status_aktif,
     * created_at, atau updated_at. Perbandingan pakai '>=' dan '<' (bukan
     * YEAR(tanggal_pensiun)) supaya index pada kolom tanggal_pensiun tetap
     * bisa dipakai database.
     *
     * Hanya PNS yang dihitung (status_kepegawaian = 'PNS'); PPPK tidak
     * memakai skema pensiun yang sama dan tidak relevan untuk laporan ini.
     * Baris dengan tanggal_pensiun NULL otomatis tidak ikut (whereNotNull).
     *
     * Golongan diambil dari golongan_ruang.kode dengan mengambil angka
     * romawi sebelum tanda '/' (mis. 'III/a' -> 'III'), sama seperti
     * pendekatan pnsAgg pada rekapGolongan(). Dipakai LEFT JOIN (bukan JOIN
     * biasa) supaya pegawai yang golongannya kosong/tidak valid TETAP masuk
     * hitungan 'jumlah_pensiunan_pns', hanya saja tidak dipaksakan masuk ke
     * salah satu bucket Golongan I-IV.
     *
     * Query di-GROUP BY di level database (kode golongan + jenis kelamin),
     * jadi loop PHP di bawah hanya jalan atas baris hasil agregat (belasan
     * baris), bukan looping seluruh tabel pegawai.
     */
    public function statistikPensiunanPNS(?int $tahun = null): array
    {
        $tahun = $tahun ?? (int) now()->year;

        $awal = sprintf('%04d-01-01', $tahun);
        $akhir = sprintf('%04d-01-01', $tahun + 1);

        $rows = Pegawai::query()
            ->leftJoin('golongan_ruang', 'golongan_ruang.id', '=', 'pegawai.golongan_ruang_id')
            ->select(
                'golongan_ruang.kode as golongan_kode',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_kepegawaian', 'PNS')
            ->whereNotNull('pegawai.tanggal_pensiun')
            ->where('pegawai.tanggal_pensiun', '>=', $awal)
            ->where('pegawai.tanggal_pensiun', '<', $akhir)
            ->groupBy('golongan_ruang.kode', 'pegawai.jenis_kelamin')
            ->get();

        $agregat = [
            'I' => ['laki_laki' => 0, 'perempuan' => 0],
            'II' => ['laki_laki' => 0, 'perempuan' => 0],
            'III' => ['laki_laki' => 0, 'perempuan' => 0],
            'IV' => ['laki_laki' => 0, 'perempuan' => 0],
        ];

        $totalLakiLaki = 0;
        $totalPerempuan = 0;

        foreach ($rows as $row) {
            $gender = $row->jenis_kelamin === 'L' ? 'laki_laki' : 'perempuan';
            $jumlah = (int) $row->jumlah;

            // Total tetap dihitung di sini SEBELUM cek golongan valid,
            // supaya pegawai dengan golongan kosong/tidak dikenali tetap
            // masuk 'jumlah_pensiunan_pns' (sesuai aturan), walau tidak
            // dipaksakan masuk ke salah satu bucket Golongan I-IV di bawah.
            if ($gender === 'laki_laki') {
                $totalLakiLaki += $jumlah;
            } else {
                $totalPerempuan += $jumlah;
            }

            $kode = trim((string) $row->golongan_kode);

            if (!str_contains($kode, '/')) {
                continue;
            }

            $romawi = explode('/', $kode)[0];

            if (!isset($agregat[$romawi])) {
                continue;
            }

            $agregat[$romawi][$gender] += $jumlah;
        }

        $totalI = $agregat['I']['laki_laki'] + $agregat['I']['perempuan'];
        $totalII = $agregat['II']['laki_laki'] + $agregat['II']['perempuan'];
        $totalIII = $agregat['III']['laki_laki'] + $agregat['III']['perempuan'];
        $totalIV = $agregat['IV']['laki_laki'] + $agregat['IV']['perempuan'];

        return [
            'tahun' => $tahun,
            'jumlah_pensiunan_pns' => $totalLakiLaki + $totalPerempuan,
            'golongan_I' => [
                'total' => $totalI,
                'laki_laki' => $agregat['I']['laki_laki'],
                'perempuan' => $agregat['I']['perempuan'],
            ],
            'golongan_II' => [
                'total' => $totalII,
                'laki_laki' => $agregat['II']['laki_laki'],
                'perempuan' => $agregat['II']['perempuan'],
            ],
            'golongan_III' => [
                'total' => $totalIII,
                'laki_laki' => $agregat['III']['laki_laki'],
                'perempuan' => $agregat['III']['perempuan'],
            ],
            'golongan_IV' => [
                'total' => $totalIV,
                'laki_laki' => $agregat['IV']['laki_laki'],
                'perempuan' => $agregat['IV']['perempuan'],
            ],
        ];
    }

/**
     * Statistik PNS berdasarkan Golongan (I-IV) dan jenis kelamin, dengan
     * rincian per kode golongan spesifik (I/a, I/b, dst — IV/a s.d. IV/e).
     *
     * Hanya golongan_ruang.kelompok = 'PNS' yang dihitung — PPPK punya skema
     * golongan romawi berbeda (I-XI), lihat statistikPppkGolongan().
     * Filter status_aktif = 'aktif', konsisten dengan rekap/statistik*
     * lain di service ini.
     *
     * $periode belum dipakai untuk filter (tabel pegawai belum punya kolom
     * periode/tahun), dipertahankan untuk konsistensi.
     */
    public function statistikPnsGolongan(?string $periode = null): array
    {
        $rincianPerGolongan = [
            'I' => ['I/a', 'I/b', 'I/c', 'I/d'],
            'II' => ['II/a', 'II/b', 'II/c', 'II/d'],
            'III' => ['III/a', 'III/b', 'III/c', 'III/d'],
            'IV' => ['IV/a', 'IV/b', 'IV/c', 'IV/d', 'IV/e'],
        ];

        $rows = Pegawai::query()
            ->join('golongan_ruang', 'golongan_ruang.id', '=', 'pegawai.golongan_ruang_id')
            ->select(
                'golongan_ruang.kode as golongan_kode',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->where('golongan_ruang.kelompok', 'PNS')
            ->groupBy('golongan_ruang.kode', 'pegawai.jenis_kelamin')
            ->get();

        $data = [];
        foreach ($rincianPerGolongan as $romawi => $kodeList) {
            $data[$romawi] = [
                'laki_laki' => array_fill_keys($kodeList, 0),
                'perempuan' => array_fill_keys($kodeList, 0),
            ];
        }

        foreach ($rows as $row) {
            $kode = trim((string) $row->golongan_kode);

            if (!str_contains($kode, '/')) {
                continue;
            }

            $romawi = explode('/', $kode)[0];

            if (!isset($data[$romawi])) {
                continue;
            }

            $gender = $row->jenis_kelamin === 'L' ? 'laki_laki' : 'perempuan';
            $jumlah = (int) $row->jumlah;

            if (array_key_exists($kode, $data[$romawi][$gender])) {
                $data[$romawi][$gender][$kode] = $jumlah;
            }
        }

        $hasil = ['jumlah_pns' => 0];

        foreach ($data as $romawi => $genders) {
            $totalLakiLaki = array_sum($genders['laki_laki']);
            $totalPerempuan = array_sum($genders['perempuan']);
            $totalGolongan = $totalLakiLaki + $totalPerempuan;

            $hasil['golongan_' . $romawi] = [
                'total' => $totalGolongan,
                'laki_laki' => [
                    'total' => $totalLakiLaki,
                    'rincian' => $genders['laki_laki'],
                ],
                'perempuan' => [
                    'total' => $totalPerempuan,
                    'rincian' => $genders['perempuan'],
                ],
            ];

            $hasil['jumlah_pns'] += $totalGolongan;
        }

        return $hasil;
    }

/**
     * Statistik PPPK berdasarkan Golongan (I-XI) dan jenis kelamin.
     *
     * Hanya golongan_ruang.kelompok = 'PPPK' yang dihitung. Semua 11
     * golongan (I-XI) selalu ditampilkan dalam response walau nilainya 0,
     * supaya struktur output tetap lengkap sesuai template laporan resmi
     * (di data aktual per Agustus 2026, cuma golongan I, III, V, VII, IX,
     * X, XI yang terisi — sisanya memang 0, bukan bug).
     *
     * $periode belum dipakai untuk filter (tabel pegawai belum punya kolom
     * periode/tahun), dipertahankan untuk konsistensi.
     */
    public function statistikPppkGolongan(?string $periode = null): array
    {
        $golonganList = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI'];

        $rows = Pegawai::query()
            ->join('golongan_ruang', 'golongan_ruang.id', '=', 'pegawai.golongan_ruang_id')
            ->select(
                'golongan_ruang.kode as golongan_kode',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->where('golongan_ruang.kelompok', 'PPPK')
            ->groupBy('golongan_ruang.kode', 'pegawai.jenis_kelamin')
            ->get();

        $agregat = [];
        foreach ($golonganList as $g) {
            $agregat[$g] = ['laki_laki' => 0, 'perempuan' => 0];
        }

        foreach ($rows as $row) {
            $kode = trim((string) $row->golongan_kode);

            if (!isset($agregat[$kode])) {
                continue;
            }

            $gender = $row->jenis_kelamin === 'L' ? 'laki_laki' : 'perempuan';
            $agregat[$kode][$gender] += (int) $row->jumlah;
        }

        $hasil = ['jumlah_pppk' => 0, 'golongan' => []];

        foreach ($golonganList as $g) {
            $lakiLaki = $agregat[$g]['laki_laki'];
            $perempuan = $agregat[$g]['perempuan'];
            $total = $lakiLaki + $perempuan;

            $hasil['golongan'][$g] = [
                'total' => $total,
                'laki_laki' => $lakiLaki,
                'perempuan' => $perempuan,
            ];

            $hasil['jumlah_pppk'] += $total;
        }

        return $hasil;
    }

/**
     * Statistik ASN (PNS + PPPK digabung) berdasarkan tingkat pendidikan
     * dan jenis kelamin.
     *
     * Mapping jenjang pendidikan PERSIS sama dengan rekapPendidikan()/
     * rekapDashboard() — sudah divalidasi ke data mentah & laporan final
     * (termasuk varian SEKOLAH DASAR, SLTA KEJURUAN, DIPLOMA I-IV,
     * S-3/DOKTOR). Bedanya, method ini agregat SATU kota (bukan per
     * instansi) dan tidak dipisah PNS/PPPK — sesuai kode taksonomi
     * 5.03.008 "ASN Berdasarkan Tingkat Pendidikan dan Jenis Kelamin".
     *
     * 'jumlah_asn' dihitung dari SEMUA pegawai aktif (termasuk yang
     * jenjang pendidikannya tidak dikenali/kosong), supaya angka total
     * selalu akurat. Baris yang tidak dikenali dicatat di 'tidak_dikenali'
     * dan di-log, sama seperti rekapPendidikan() — bukan bagian dari
     * taksonomi resmi, tapi berguna untuk deteksi varian teks baru.
     *
     * $periode belum dipakai untuk filter (tabel pegawai belum punya kolom
     * periode/tahun), dipertahankan untuk konsistensi.
     */
    public function statistikAsnPendidikan(?string $periode = null): array
    {
        $pendidikanList = ['SD', 'SLTP', 'SLTA', 'D I', 'D II', 'D III', 'D IV', 'S1', 'S2', 'S3'];

        $keyMap = [
            'SD' => 'sd',
            'SLTP' => 'smp',
            'SLTA' => 'sma',
            'D I' => 'diploma_i',
            'D II' => 'diploma_ii',
            'D III' => 'diploma_iii',
            'D IV' => 'diploma_iv',
            'S1' => 'strata_1',
            'S2' => 'strata_2',
            'S3' => 'strata_3',
        ];

        $rows = Pegawai::query()
            ->leftJoin('pendidikan', 'pendidikan.id', '=', 'pegawai.pendidikan_id')
            ->select(
                'pendidikan.jenjang as pendidikan_nama',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->groupBy('pendidikan.jenjang', 'pegawai.jenis_kelamin')
            ->get();

        $agregat = [];
        foreach ($pendidikanList as $jenjang) {
            $agregat[$jenjang] = ['laki_laki' => 0, 'perempuan' => 0];
        }

        $tidakDikenali = 0;

        foreach ($rows as $row) {
            $raw = $row->pendidikan_nama ? strtoupper(trim($row->pendidikan_nama)) : null;

            $jenjang = $raw ? match ($raw) {
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

            $gender = $row->jenis_kelamin === 'L' ? 'laki_laki' : 'perempuan';
            $jumlah = (int) $row->jumlah;

            if ($jenjang === null) {
                $tidakDikenali += $jumlah;

                if ($raw !== null) {
                    Log::warning('StatistikAsnPendidikan: jenjang pendidikan tidak dikenali', [
                        'pendidikan_raw' => $row->pendidikan_nama,
                        'jumlah' => $jumlah,
                    ]);
                }

                continue;
            }

            $agregat[$jenjang][$gender] += $jumlah;
        }

        $hasil = ['jumlah_asn' => 0, 'pendidikan' => [], 'tidak_dikenali' => $tidakDikenali];

        foreach ($pendidikanList as $jenjang) {
            $lakiLaki = $agregat[$jenjang]['laki_laki'];
            $perempuan = $agregat[$jenjang]['perempuan'];
            $total = $lakiLaki + $perempuan;

            $hasil['pendidikan'][$keyMap[$jenjang]] = [
                'total' => $total,
                'laki_laki' => $lakiLaki,
                'perempuan' => $perempuan,
            ];

            $hasil['jumlah_asn'] += $total;
        }

        $hasil['jumlah_asn'] += $tidakDikenali;

        return $hasil;
    }

/**
 * Statistik PNS berdasarkan Tingkat Pendidikan dan Jenis Kelamin.
 *
 * Scope: 5.03.009
 *
 * Hanya PNS aktif yang dihitung.
 * Mapping pendidikan mengikuti mapping pada statistikAsnPendidikan().
 */
public function statistikPnsPendidikan(?string $periode = null): array
{
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

    $keyMap = [
        'SD'   => 'sd',
        'SLTP' => 'smp',
        'SLTA' => 'sma',
        'D I'  => 'diploma_i',
        'D II' => 'diploma_ii',
        'D III' => 'diploma_iii',
        'D IV' => 'diploma_iv',
        'S1'   => 'strata_1',
        'S2'   => 'strata_2',
        'S3'   => 'strata_3',
    ];

    $rows = Pegawai::query()
        ->leftJoin(
            'pendidikan',
            'pendidikan.id',
            '=',
            'pegawai.pendidikan_id'
        )
        ->select(
            'pendidikan.jenjang as pendidikan_nama',
            'pegawai.jenis_kelamin',
            DB::raw('COUNT(*) as jumlah')
        )
        ->where('pegawai.status_aktif', 'aktif')
        ->where('pegawai.status_kepegawaian', 'PNS')
        ->groupBy(
            'pendidikan.jenjang',
            'pegawai.jenis_kelamin'
        )
        ->get();

    $agregat = [];

    foreach ($pendidikanList as $jenjang) {
        $agregat[$jenjang] = [
            'laki_laki' => 0,
            'perempuan' => 0,
        ];
    }

    $tidakDikenali = 0;

    foreach ($rows as $row) {
        $raw = $row->pendidikan_nama
            ? strtoupper(trim($row->pendidikan_nama))
            : null;

        $jenjang = $raw ? match ($raw) {
            'SD',
            'SEKOLAH DASAR'
                => 'SD',

            'SMP',
            'SLTP'
                => 'SLTP',

            'SMA',
            'SMK',
            'SMA/SMK',
            'SLTA',
            'SLTA KEJURUAN'
                => 'SLTA',

            'D1',
            'D-1',
            'D I',
            'DIPLOMA I'
                => 'D I',

            'D2',
            'D-2',
            'D II',
            'DIPLOMA II'
                => 'D II',

            'D3',
            'D-3',
            'D III',
            'DIPLOMA III/SARJANA'
                => 'D III',

            'D4',
            'D-4',
            'D IV',
            'D4/S1',
            'DIPLOMA IV'
                => 'D IV',

            'S1',
            'S-1',
            'S-1/SARJANA',
            'SARJANA'
                => 'S1',

            'S2',
            'S-2',
            'S-2/MAGISTER'
                => 'S2',

            'S3',
            'S-3',
            'S-3/DOKTOR'
                => 'S3',

            default => null,
        } : null;

        if ($jenjang === null) {
            $tidakDikenali += (int) $row->jumlah;
            continue;
        }

        $gender = $row->jenis_kelamin === 'L'
            ? 'laki_laki'
            : 'perempuan';

        $agregat[$jenjang][$gender] += (int) $row->jumlah;
    }

    $hasil = [
        'jumlah_pns' => 0,
    ];

    foreach ($agregat as $jenjang => $gender) {
        $total = $gender['laki_laki'] + $gender['perempuan'];

        $key = $keyMap[$jenjang];

        $hasil[$key] = [
            'total' => $total,
            'laki_laki' => $gender['laki_laki'],
            'perempuan' => $gender['perempuan'],
        ];

        $hasil['jumlah_pns'] += $total;
    }

    $hasil['tidak_dikenali'] = $tidakDikenali;

    return $hasil;
}

/**
 * Statistik PPPK berdasarkan Tingkat Pendidikan dan Jenis Kelamin.
 *
 * Scope: 5.03.010
 *
 * Hanya PPPK aktif yang dihitung.
 */
public function statistikPppkPendidikan(?string $periode = null): array
{
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

    $keyMap = [
        'SD'   => 'sd',
        'SLTP' => 'smp',
        'SLTA' => 'sma',
        'D I'  => 'diploma_i',
        'D II' => 'diploma_ii',
        'D III' => 'diploma_iii',
        'D IV' => 'diploma_iv',
        'S1'   => 'strata_1',
        'S2'   => 'strata_2',
        'S3'   => 'strata_3',
    ];

    $rows = Pegawai::query()
        ->leftJoin(
            'pendidikan',
            'pendidikan.id',
            '=',
            'pegawai.pendidikan_id'
        )
        ->select(
            'pendidikan.jenjang as pendidikan_nama',
            'pegawai.jenis_kelamin',
            DB::raw('COUNT(*) as jumlah')
        )
        ->where('pegawai.status_aktif', 'aktif')
        ->where('pegawai.status_kepegawaian', 'PPPK')
        ->groupBy(
            'pendidikan.jenjang',
            'pegawai.jenis_kelamin'
        )
        ->get();

    $agregat = [];

    foreach ($pendidikanList as $jenjang) {
        $agregat[$jenjang] = [
            'laki_laki' => 0,
            'perempuan' => 0,
        ];
    }

    $tidakDikenali = 0;

    foreach ($rows as $row) {
        $raw = $row->pendidikan_nama
            ? strtoupper(trim($row->pendidikan_nama))
            : null;

        $jenjang = $raw ? match ($raw) {
            'SD',
            'SEKOLAH DASAR'
                => 'SD',

            'SMP',
            'SLTP'
                => 'SLTP',

            'SMA',
            'SMK',
            'SMA/SMK',
            'SLTA',
            'SLTA KEJURUAN'
                => 'SLTA',

            'D1',
            'D-1',
            'D I',
            'DIPLOMA I'
                => 'D I',

            'D2',
            'D-2',
            'D II',
            'DIPLOMA II'
                => 'D II',

            'D3',
            'D-3',
            'D III',
            'DIPLOMA III/SARJANA'
                => 'D III',

            'D4',
            'D-4',
            'D IV',
            'D4/S1',
            'DIPLOMA IV'
                => 'D IV',

            'S1',
            'S-1',
            'S-1/SARJANA',
            'SARJANA'
                => 'S1',

            'S2',
            'S-2',
            'S-2/MAGISTER'
                => 'S2',

            'S3',
            'S-3',
            'S-3/DOKTOR'
                => 'S3',

            default => null,
        } : null;

        if ($jenjang === null) {
            $tidakDikenali += (int) $row->jumlah;
            continue;
        }

        $gender = $row->jenis_kelamin === 'L'
            ? 'laki_laki'
            : 'perempuan';

        $agregat[$jenjang][$gender] += (int) $row->jumlah;
    }

    $hasil = [
        'jumlah_pppk' => 0,
    ];

    foreach ($agregat as $jenjang => $gender) {
        $total = $gender['laki_laki'] + $gender['perempuan'];

        $key = $keyMap[$jenjang];

        $hasil[$key] = [
            'total' => $total,
            'laki_laki' => $gender['laki_laki'],
            'perempuan' => $gender['perempuan'],
        ];

        $hasil['jumlah_pppk'] += $total;
    }

    $hasil['tidak_dikenali'] = $tidakDikenali;

    return $hasil;
}

/**
 * 5.03.011.001
 * Jumlah Staf Kantor Dinas Daerah Berdasarkan Tingkat Pendidikan
 *
 * STAF = JABATAN PELAKSANA
 * Scope hanya 18 Dinas.
 */
public function statistikStafDinasPendidikan(?string $periode = null): array
{
    $dinasList = [
        'DINAS KEBUDAYAAN (KUNDHA KABUDAYAN)',
        'DINAS KEPENDUDUKAN DAN PENCATATAN SIPIL',
        'DINAS KESEHATAN',
        'DINAS KOMUNIKASI INFORMATIKA DAN PERSANDIAN',
        'DINAS LINGKUNGAN HIDUP',
        'DINAS PARIWISATA',
        'DINAS PEKERJAAN UMUM PERUMAHAN DAN KAWASAN PERMUKIMAN',
        'DINAS PEMADAM KEBAKARAN DAN PENYELAMATAN',
        'DINAS PEMBERDAYAAN PEREMPUAN PERLINDUNGAN ANAK DAN PENGENDALIAN PENDUDUK DAN KELUARGA BERENCANA',
        'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU',
        'DINAS PENDIDIKAN PEMUDA DAN OLAHRAGA',
        'DINAS PERDAGANGAN',
        'DINAS PERHUBUNGAN',
        'DINAS PERINDUSTRIAN KOPERASI USAHA KECIL DAN MENENGAH',
        'DINAS PERPUSTAKAAN DAN KEARSIPAN',
        'DINAS PERTANAHAN DAN TATA RUANG (KUNDHA NITI MANDALA SARTA TATA SASANA)',
        'DINAS PERTANIAN DAN PANGAN',
        'DINAS SOSIAL TENAGA KERJA DAN TRANSMIGRASI',
    ];

    $rows = Pegawai::query()
        ->join(
            'instansi',
            'instansi.id',
            '=',
            'pegawai.instansi_id'
        )
        ->leftJoin(
            'pendidikan',
            'pendidikan.id',
            '=',
            'pegawai.pendidikan_id'
        )
        ->select(
            'instansi.nama as instansi_nama',
            'pendidikan.jenjang as pendidikan_nama',
            DB::raw('COUNT(*) as jumlah')
        )
        ->where('pegawai.status_aktif', 'aktif')
        ->where('pegawai.jenis_kedudukan', 'PELAKSANA')
        ->whereIn('instansi.nama', $dinasList)
        ->groupBy(
            'instansi.nama',
            'pendidikan.jenjang'
        )
        ->get();

    $hasilDinas = [];

    foreach ($dinasList as $dinas) {
        $hasilDinas[$dinas] = [
            'sd' => 0,
            'smp' => 0,
            'sma' => 0,
            'diploma' => 0,
            'strata_1' => 0,
            'strata_2' => 0,
            'strata_3' => 0,
            'tidak_dikenali' => 0,
        ];
    }

    foreach ($rows as $row) {

        $dinas = trim((string) $row->instansi_nama);

        if (!isset($hasilDinas[$dinas])) {
            continue;
        }

        $raw = $row->pendidikan_nama
            ? strtoupper(trim($row->pendidikan_nama))
            : null;

        $pendidikan = match ($raw) {

    'SD',
    'SEKOLAH DASAR'
        => 'sd',

    'SMP',
    'SLTP',
    'SEKOLAH MENENGAH PERTAMA'
        => 'smp',

    'SMA',
    'SMK',
    'SMA/SMK',
    'SLTA',
    'SLTA KEJURUAN',
    'SEKOLAH MENENGAH ATAS'
        => 'sma',

    'D1',
    'D-1',
    'D I',
    'DIPLOMA I',
    'DIPLOMA 1',

    'D2',
    'D-2',
    'D II',
    'DIPLOMA II',
    'DIPLOMA 2',

    'D3',
    'D-3',
    'D III',
    'DIPLOMA III',
    'DIPLOMA 3',
    'DIPLOMA III/SARJANA',

    'D4',
    'D-4',
    'D IV',
    'DIPLOMA IV',
    'DIPLOMA 4',
    'D4/S1'
        => 'diploma',

    'S1',
    'S-1',
    'S-1/SARJANA',
    'SARJANA',
    'STRATA 1'
        => 'strata_1',

    'S2',
    'S-2',
    'S-2/MAGISTER',
    'MAGISTER',
    'STRATA 2'
        => 'strata_2',

    'S3',
    'S-3',
    'S-3/DOKTOR',
    'DOKTOR',
    'STRATA 3'
        => 'strata_3',

    default => null,
};

        if ($pendidikan === null) {
            $hasilDinas[$dinas]['tidak_dikenali'] += (int) $row->jumlah;
            continue;
        }

        $hasilDinas[$dinas][$pendidikan] += (int) $row->jumlah;
    }

    $jumlahStafDinas = 0;

    foreach ($hasilDinas as &$data) {

        $data['total'] =
            $data['sd'] +
            $data['smp'] +
            $data['sma'] +
            $data['diploma'] +
            $data['strata_1'] +
            $data['strata_2'] +
            $data['strata_3'] +
            $data['tidak_dikenali'];

        $jumlahStafDinas += $data['total'];
    }

    unset($data);

    return [
        'jumlah_staf_dinas' => $jumlahStafDinas,
        'jumlah_dinas' => count($dinasList),
        'dinas' => $hasilDinas,
    ];
}

/**
 * 5.03.011.002
 * Jumlah Staf Kantor Dinas Daerah Berdasarkan Golongan
 *
 * STAF = JABATAN PELAKSANA
 * Golongan I-IV hanya menghitung PNS.
 */
public function statistikStafDinasGolongan(?string $periode = null): array
{
    $dinasList = [
        'DINAS KEBUDAYAAN (KUNDHA KABUDAYAN)',
        'DINAS KEPENDUDUKAN DAN PENCATATAN SIPIL',
        'DINAS KESEHATAN',
        'DINAS KOMUNIKASI INFORMATIKA DAN PERSANDIAN',
        'DINAS LINGKUNGAN HIDUP',
        'DINAS PARIWISATA',
        'DINAS PEKERJAAN UMUM PERUMAHAN DAN KAWASAN PERMUKIMAN',
        'DINAS PEMADAM KEBAKARAN DAN PENYELAMATAN',
        'DINAS PEMBERDAYAAN PEREMPUAN PERLINDUNGAN ANAK DAN PENGENDALIAN PENDUDUK DAN KELUARGA BERENCANA',
        'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU',
        'DINAS PENDIDIKAN PEMUDA DAN OLAHRAGA',
        'DINAS PERDAGANGAN',
        'DINAS PERHUBUNGAN',
        'DINAS PERINDUSTRIAN KOPERASI USAHA KECIL DAN MENENGAH',
        'DINAS PERPUSTAKAAN DAN KEARSIPAN',
        'DINAS PERTANAHAN DAN TATA RUANG (KUNDHA NITI MANDALA SARTA TATA SASANA)',
        'DINAS PERTANIAN DAN PANGAN',
        'DINAS SOSIAL TENAGA KERJA DAN TRANSMIGRASI',
    ];

    $rows = Pegawai::query()
        ->join(
            'instansi',
            'instansi.id',
            '=',
            'pegawai.instansi_id'
        )
        ->leftJoin(
            'golongan_ruang',
            'golongan_ruang.id',
            '=',
            'pegawai.golongan_ruang_id'
        )
        ->select(
            'instansi.nama as instansi_nama',
            'golongan_ruang.kode as golongan_kode',
            DB::raw('COUNT(*) as jumlah')
        )
        ->where('pegawai.status_aktif', 'aktif')
        ->where('pegawai.status_kepegawaian', 'PNS')
        ->where('pegawai.jenis_kedudukan', 'PELAKSANA')
        ->whereIn('instansi.nama', $dinasList)
        ->groupBy(
            'instansi.nama',
            'golongan_ruang.kode'
        )
        ->get();

    $hasilDinas = [];

    foreach ($dinasList as $dinas) {
        $hasilDinas[$dinas] = [
            'golongan_I' => 0,
            'golongan_II' => 0,
            'golongan_III' => 0,
            'golongan_IV' => 0,
            'tidak_dikenali' => 0,
        ];
    }

    foreach ($rows as $row) {

        $dinas = trim((string) $row->instansi_nama);

        if (!isset($hasilDinas[$dinas])) {
            continue;
        }

        $kode = strtoupper(trim((string) $row->golongan_kode));

        if ($kode === '') {
            $hasilDinas[$dinas]['tidak_dikenali'] += (int) $row->jumlah;
            continue;
        }

        /*
         * Contoh:
         * I/a  -> I
         * II/a -> II
         * III/c -> III
         * IV/a -> IV
         */

        $romawi = preg_split('/[\/\s]/', $kode)[0];

        if ($romawi === 'I') {

            $hasilDinas[$dinas]['golongan_I'] += (int) $row->jumlah;

        } elseif ($romawi === 'II') {

            $hasilDinas[$dinas]['golongan_II'] += (int) $row->jumlah;

        } elseif ($romawi === 'III') {

            $hasilDinas[$dinas]['golongan_III'] += (int) $row->jumlah;

        } elseif ($romawi === 'IV') {

            $hasilDinas[$dinas]['golongan_IV'] += (int) $row->jumlah;

        } else {

            $hasilDinas[$dinas]['tidak_dikenali'] += (int) $row->jumlah;
        }
    }

    $jumlahStafDinas = 0;

    foreach ($hasilDinas as &$data) {

        $data['total'] =
            $data['golongan_I'] +
            $data['golongan_II'] +
            $data['golongan_III'] +
            $data['golongan_IV'] +
            $data['tidak_dikenali'];

        $jumlahStafDinas += $data['total'];
    }

    unset($data);

    return [
        'jumlah_staf_dinas' => $jumlahStafDinas,
        'jumlah_dinas' => count($dinasList),

        'golongan' => [
            'I' => array_sum(array_column($hasilDinas, 'golongan_I')),
            'II' => array_sum(array_column($hasilDinas, 'golongan_II')),
            'III' => array_sum(array_column($hasilDinas, 'golongan_III')),
            'IV' => array_sum(array_column($hasilDinas, 'golongan_IV')),
        ],

        'dinas' => $hasilDinas,
    ];
}

/**
 * 5.03.011.003
 * Jumlah Pejabat Struktural Kantor Dinas Daerah
 */
public function statistikPejabatStrukturalDinas(?string $periode = null): array
{
    $dinasList = [
        'DINAS KEBUDAYAAN (KUNDHA KABUDAYAN)',
        'DINAS KEPENDUDUKAN DAN PENCATATAN SIPIL',
        'DINAS KESEHATAN',
        'DINAS KOMUNIKASI INFORMATIKA DAN PERSANDIAN',
        'DINAS LINGKUNGAN HIDUP',
        'DINAS PARIWISATA',
        'DINAS PEKERJAAN UMUM PERUMAHAN DAN KAWASAN PERMUKIMAN',
        'DINAS PEMADAM KEBAKARAN DAN PENYELAMATAN',
        'DINAS PEMBERDAYAAN PEREMPUAN PERLINDUNGAN ANAK DAN PENGENDALIAN PENDUDUK DAN KELUARGA BERENCANA',
        'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU',
        'DINAS PENDIDIKAN PEMUDA DAN OLAHRAGA',
        'DINAS PERDAGANGAN',
        'DINAS PERHUBUNGAN',
        'DINAS PERINDUSTRIAN KOPERASI USAHA KECIL DAN MENENGAH',
        'DINAS PERPUSTAKAAN DAN KEARSIPAN',
        'DINAS PERTANAHAN DAN TATA RUANG (KUNDHA NITI MANDALA SARTA TATA SASANA)',
        'DINAS PERTANIAN DAN PANGAN',
        'DINAS SOSIAL TENAGA KERJA DAN TRANSMIGRASI',
    ];

    $rows = Pegawai::query()
        ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
        ->join(
            'eselon',
            'eselon.id',
            '=',
            'pegawai.eselon_id'
        )
        ->select(
            'instansi.nama as instansi_nama',
            'eselon.kode as eselon_kode',
            DB::raw('COUNT(*) as jumlah')
        )
        ->where('pegawai.status_aktif', 'aktif')
        ->whereIn('instansi.nama', $dinasList)
        ->whereIn('eselon.kode', [
            'I',
            'II',
            'III',
            'IV',
            'I A',
            'I B',
            'II A',
            'II B',
            'III A',
            'III B',
            'IV A',
            'IV B',
        ])
        ->groupBy(
            'instansi.nama',
            'eselon.kode'
        )
        ->get();

    $hasilDinas = [];

    foreach ($dinasList as $dinas) {
        $hasilDinas[$dinas] = [
            'eselon_I' => 0,
            'eselon_II' => 0,
            'eselon_III' => 0,
            'eselon_IV' => 0,
        ];
    }

    foreach ($rows as $row) {

        $dinas = trim((string) $row->instansi_nama);

        if (!isset($hasilDinas[$dinas])) {
            continue;
        }

        $kode = strtoupper(
            trim((string) $row->eselon_kode)
        );

        if (
            $kode === 'I' ||
            str_starts_with($kode, 'I ')
        ) {
            $hasilDinas[$dinas]['eselon_I'] += (int) $row->jumlah;
        }

        elseif (
            $kode === 'II' ||
            str_starts_with($kode, 'II ')
        ) {
            $hasilDinas[$dinas]['eselon_II'] += (int) $row->jumlah;
        }

        elseif (
            $kode === 'III' ||
            str_starts_with($kode, 'III ')
        ) {
            $hasilDinas[$dinas]['eselon_III'] += (int) $row->jumlah;
        }

        elseif (
            $kode === 'IV' ||
            str_starts_with($kode, 'IV ')
        ) {
            $hasilDinas[$dinas]['eselon_IV'] += (int) $row->jumlah;
        }
    }

    $jumlahPejabatStruktural = 0;

    foreach ($hasilDinas as &$data) {

        $data['total'] =
            $data['eselon_I'] +
            $data['eselon_II'] +
            $data['eselon_III'] +
            $data['eselon_IV'];

        $jumlahPejabatStruktural += $data['total'];
    }

    unset($data);

    return [
        'jumlah_pejabat_struktural' => $jumlahPejabatStruktural,

        'jumlah_dinas' => count($dinasList),

        'eselon' => [
            'I' => array_sum(
                array_column($hasilDinas, 'eselon_I')
            ),
            'II' => array_sum(
                array_column($hasilDinas, 'eselon_II')
            ),
            'III' => array_sum(
                array_column($hasilDinas, 'eselon_III')
            ),
            'IV' => array_sum(
                array_column($hasilDinas, 'eselon_IV')
            ),
        ],

        'dinas' => $hasilDinas,
    ];
}

/**
 * Statistik Pejabat Fungsional Kantor Dinas Daerah.
 *
 * Scope: 5.03.011.004
 *
 * Hanya pegawai aktif pada 18 Dinas yang dihitung.
 */
public function statistikPejabatFungsionalDinas(?string $periode = null): array
{
    $dinasList = [
        'DINAS KEBUDAYAAN (KUNDHA KABUDAYAN)',
        'DINAS KEPENDUDUKAN DAN PENCATATAN SIPIL',
        'DINAS KESEHATAN',
        'DINAS KOMUNIKASI INFORMATIKA DAN PERSANDIAN',
        'DINAS LINGKUNGAN HIDUP',
        'DINAS PARIWISATA',
        'DINAS PEKERJAAN UMUM PERUMAHAN DAN KAWASAN PERMUKIMAN',
        'DINAS PEMADAM KEBAKARAN DAN PENYELAMATAN',
        'DINAS PEMBERDAYAAN PEREMPUAN PERLINDUNGAN ANAK DAN PENGENDALIAN PENDUDUK DAN KELUARGA BERENCANA',
        'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU',
        'DINAS PENDIDIKAN PEMUDA DAN OLAHRAGA',
        'DINAS PERDAGANGAN',
        'DINAS PERHUBUNGAN',
        'DINAS PERINDUSTRIAN KOPERASI USAHA KECIL DAN MENENGAH',
        'DINAS PERPUSTAKAAN DAN KEARSIPAN',
        'DINAS PERTANAHAN DAN TATA RUANG (KUNDHA NITI MANDALA SARTA TATA SASANA)',
        'DINAS PERTANIAN DAN PANGAN',
        'DINAS SOSIAL TENAGA KERJA DAN TRANSMIGRASI',
    ];

    $rows = Pegawai::query()
        ->join(
            'instansi',
            'instansi.id',
            '=',
            'pegawai.instansi_id'
        )
        ->select(
            'instansi.nama as instansi_nama',
            DB::raw('COUNT(*) as jumlah')
        )
        ->where('pegawai.status_aktif', 'aktif')
        ->whereIn('instansi.nama', $dinasList)
        ->whereRaw('UPPER(TRIM(pegawai.jenis_kedudukan)) = ?', ['FUNGSIONAL'])
        ->groupBy('instansi.nama')
        ->get();

    $hasilDinas = [];

    foreach ($dinasList as $dinas) {
        $hasilDinas[$dinas] = [
            'jumlah_fungsional' => 0,
        ];
    }

    foreach ($rows as $row) {
        $dinas = trim((string) $row->instansi_nama);

        if (!isset($hasilDinas[$dinas])) {
            continue;
        }

        $hasilDinas[$dinas]['jumlah_fungsional'] =
            (int) $row->jumlah;
    }

    $jumlahPejabatFungsional = 0;

    foreach ($hasilDinas as $dinas => &$data) {
        $data['total'] = $data['jumlah_fungsional'];

        $jumlahPejabatFungsional += $data['total'];
    }

    unset($data);

    return [
        'jumlah_pejabat_fungsional' => $jumlahPejabatFungsional,
        'jumlah_dinas' => count($dinasList),
        'dinas' => $hasilDinas,
    ];
}

/**
 * 5.03.011.005
 * Jumlah Pensiunan Kantor Dinas Daerah
 *
 * Pensiunan = pegawai dengan tanggal_pensiun
 * berada pada tahun 2026.
 *
 * Scope hanya 18 Dinas.
 */
public function statistikPensiunanDinas(?int $tahun = null): array
{
    $tahun ??= (int) date('Y');
    $dinasList = [
        'DINAS KEBUDAYAAN (KUNDHA KABUDAYAN)',
        'DINAS KEPENDUDUKAN DAN PENCATATAN SIPIL',
        'DINAS KESEHATAN',
        'DINAS KOMUNIKASI INFORMATIKA DAN PERSANDIAN',
        'DINAS LINGKUNGAN HIDUP',
        'DINAS PARIWISATA',
        'DINAS PEKERJAAN UMUM PERUMAHAN DAN KAWASAN PERMUKIMAN',
        'DINAS PEMADAM KEBAKARAN DAN PENYELAMATAN',
        'DINAS PEMBERDAYAAN PEREMPUAN PERLINDUNGAN ANAK DAN PENGENDALIAN PENDUDUK DAN KELUARGA BERENCANA',
        'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU',
        'DINAS PENDIDIKAN PEMUDA DAN OLAHRAGA',
        'DINAS PERDAGANGAN',
        'DINAS PERHUBUNGAN',
        'DINAS PERINDUSTRIAN KOPERASI USAHA KECIL DAN MENENGAH',
        'DINAS PERPUSTAKAAN DAN KEARSIPAN',
        'DINAS PERTANAHAN DAN TATA RUANG (KUNDHA NITI MANDALA SARTA TATA SASANA)',
        'DINAS PERTANIAN DAN PANGAN',
        'DINAS SOSIAL TENAGA KERJA DAN TRANSMIGRASI',
    ];

    $awalTahun = sprintf('%04d-01-01', $tahun);
    $awalTahunBerikutnya = sprintf('%04d-01-01', $tahun + 1);

    $rows = Pegawai::query()
        ->join(
            'instansi',
            'instansi.id',
            '=',
            'pegawai.instansi_id'
        )
        ->select(
            'instansi.nama as instansi_nama',
            DB::raw('COUNT(*) as jumlah')
        )
        ->whereIn('instansi.nama', $dinasList)
        ->where('pegawai.status_kepegawaian', 'PNS')
        ->whereNotNull('pegawai.tanggal_pensiun')
        ->where(
            'pegawai.tanggal_pensiun',
            '>=',
            $awalTahun
        )
        ->where(
            'pegawai.tanggal_pensiun',
            '<',
            $awalTahunBerikutnya
        )
        ->groupBy('instansi.nama')
        ->get();

    $hasilDinas = [];

    foreach ($dinasList as $dinas) {
        $hasilDinas[$dinas] = [
            'jumlah' => 0,
        ];
    }

    foreach ($rows as $row) {
        $dinas = trim((string) $row->instansi_nama);

        if (!isset($hasilDinas[$dinas])) {
            continue;
        }

        $hasilDinas[$dinas]['jumlah'] =
            (int) $row->jumlah;
    }

    $jumlahPensiunan = 0;

    foreach ($hasilDinas as &$data) {
        $jumlahPensiunan += $data['jumlah'];
    }

    unset($data);

    return [
        'tahun' => $tahun,
        'jumlah_pensiunan' => $jumlahPensiunan,
        'jumlah_dinas' => count($dinasList),
        'dinas' => $hasilDinas,
    ];
}
/**
 * Statistik ASN Perangkat Daerah berdasarkan Jenis Kelamin.
 *
 * Scope: 5.03.012
 *
 * Menghitung:
 * - Jumlah seluruh ASN Pemerintah Kota Yogyakarta
 * - Jumlah ASN laki-laki Pemerintah Kota Yogyakarta
 * - Jumlah ASN laki-laki pada setiap perangkat daerah
 *
 * ASN dihitung berdasarkan pegawai aktif.
 */
public function statistikPenjabatPerangkatDaerahJenisKelamin(): array
{
    /*
    |--------------------------------------------------------------------------
    | Helper hitung berdasarkan jabatan / kedudukan
    |--------------------------------------------------------------------------
    |
    | FIX (konsistensi total):
    | 'total' sekarang dihitung dari laki_laki + perempuan, BUKAN dari
    | ->count() query terpisah. Sebelumnya total bisa lebih besar dari
    | laki_laki + perempuan kalau ada baris dengan jenis_kelamin NULL/typo/
    | nilai lain di luar L/LAKI-LAKI/P/PEREMPUAN, melanggar aturan
    | "Total = Laki-Laki + Perempuan".
    */
    $hitung = function ($queryCallback) {
        $query = Pegawai::query()
            ->where('pegawai.status_aktif', 'aktif');

        $queryCallback($query);

        $lakiLaki = (clone $query)
            ->where(function ($q) {
                $q->where('pegawai.jenis_kelamin', 'L')
                    ->orWhere('pegawai.jenis_kelamin', 'LAKI-LAKI');
            })
            ->count();

        $perempuan = (clone $query)
            ->where(function ($q) {
                $q->where('pegawai.jenis_kelamin', 'P')
                    ->orWhere('pegawai.jenis_kelamin', 'PEREMPUAN');
            })
            ->count();

        return [
            'total' => $lakiLaki + $perempuan,
            'laki_laki' => $lakiLaki,
            'perempuan' => $perempuan,
        ];
    };


    /*
    |--------------------------------------------------------------------------
    | 5.03.013.001
    | Kepala Daerah
    |--------------------------------------------------------------------------
    |
    | Tidak diubah — belum ditemukan bug di sini. Hasil 0 di data saat ini
    | kemungkinan memang benar (Wali Kota tidak selalu tercatat sebagai
    | baris pegawai biasa). TETAP PERLU KONFIRMASI dari kamu.
    |
    */
    $kepalaDaerah = $hitung(function ($query) {
        $query->where(function ($q) {
            $q->whereRaw('UPPER(TRIM(pegawai.jabatan)) LIKE ?', ['%BUPATI%'])
                ->orWhereRaw('UPPER(TRIM(pegawai.jabatan)) LIKE ?', ['%WALI KOTA%'])
                ->orWhereRaw('UPPER(TRIM(pegawai.jabatan)) LIKE ?', ['%WALIKOTA%'])
                ->orWhereRaw('UPPER(TRIM(pegawai.jabatan)) LIKE ?', ['%GUBERNUR%']);
        });
    });


    /*
    |--------------------------------------------------------------------------
    | 5.03.013.002
    | Mantri Pamong Praja
    |--------------------------------------------------------------------------
    |
    | Tidak diubah — sudah terverifikasi benar (13 total, 12 laki-laki,
    | 1 perempuan, cocok dengan data).
    */
    $mantriPamongPraja = $hitung(function ($query) {
        $query->whereRaw(
            'UPPER(TRIM(pegawai.jabatan)) LIKE ?',
            ['%MANTRI PAMONG PRAJA%']
        );
    });


    /*
    |--------------------------------------------------------------------------
    | 5.03.013.003
    | Lurah
    |--------------------------------------------------------------------------
    |
    | FIX: sebelumnya exact match '= LURAH' selalu menghasilkan 0 karena
    | jabatan Lurah di database ditulis lengkap, misal:
    | "LURAH KELURAHAN KADIPATEN KEMANTREN KRATON".
    | Diganti jadi prefix match 'LURAH %' supaya menangkap format itu,
    | sekaligus tetap menghindari false-positive dari jabatan lain yang
    | cuma MENGANDUNG kata "LURAH" di tengah kalimat (mis. "KEPALA SEKSI
    | ... KELURAHAN ...", yang tidak diawali kata "LURAH").
    */
    $lurah = $hitung(function ($query) {
        $query->whereRaw(
            'UPPER(TRIM(pegawai.jabatan)) LIKE ?',
            ['LURAH %']
        );
    });


    /*
    |--------------------------------------------------------------------------
    | 5.03.013.004
    | Kepala OPD
    |--------------------------------------------------------------------------
    |
    | FIX: menambahkan 2 jabatan pimpinan Perangkat Daerah yang sebelumnya
    | tidak tertangkap pattern manapun, walau levelnya setara eselon II
    | dengan Kepala Dinas/Kepala Badan lain:
    | - "DIREKTUR RUMAH SAKIT UMUM DAERAH ..." (pimpinan RSUD)
    | - "INSPEKTUR" (pimpinan Inspektorat)
    |
    | CATATAN — BELUM DITAMBAHKAN, PERLU KONFIRMASI KAMU:
    | "KEPALA PELAKSANA BADAN PENANGGULANGAN BENCANA DAERAH" (BPBD) juga
    | setara eselon II tapi title-nya beda konvensi ("Kepala Pelaksana",
    | bukan "Kepala Badan"). Saya SENGAJA tidak menambahkannya otomatis
    | karena ini keputusan definisi indikator, bukan bug teknis murni —
    | kalau kamu konfirmasi itu termasuk "Kepala OPD", tambahkan baris
    | orWhereRaw ketiga di bawah ini (sudah saya siapkan, tinggal uncomment):
    */
    $kepalaOpd = $hitung(function ($query) {
        $query->where(function ($q) {
            $q->whereRaw(
                'UPPER(TRIM(pegawai.jabatan)) LIKE ?',
                ['%KEPALA DINAS%']
            )
            ->orWhereRaw(
                'UPPER(TRIM(pegawai.jabatan)) LIKE ?',
                ['%KEPALA BADAN%']
            )
            ->orWhereRaw(
                'UPPER(TRIM(pegawai.jabatan)) LIKE ?',
                ['%KEPALA SATUAN POLISI PAMONG PRAJA%']
            )
            ->orWhereRaw(
                'UPPER(TRIM(pegawai.jabatan)) LIKE ?',
                ['%KEPALA SATPOL PP%']
            )
            ->orWhereRaw(
                'UPPER(TRIM(pegawai.jabatan)) LIKE ?',
                ['%SEKRETARIS DAERAH%']
            )
            ->orWhereRaw(
                'UPPER(TRIM(pegawai.jabatan)) LIKE ?',
                ['%SEKRETARIS DPRD%']
            )
            ->orWhereRaw(
                'UPPER(TRIM(pegawai.jabatan)) LIKE ?',
                ['%DIREKTUR RUMAH SAKIT UMUM DAERAH%']
            )
            ->orWhereRaw(
                'UPPER(TRIM(pegawai.jabatan)) = ?',
                ['INSPEKTUR']
            );
            // ->orWhereRaw(
            //     'UPPER(TRIM(pegawai.jabatan)) LIKE ?',
            //     ['%KEPALA PELAKSANA BADAN PENANGGULANGAN BENCANA DAERAH%']
            // );
        });
    });


    /*
    |--------------------------------------------------------------------------
    | 5.03.013.005
    | Pejabat ASN Struktural
    |--------------------------------------------------------------------------
    |
    | FIX (konsistensi total): sama seperti $hitung(), 'total' sekarang
    | dihitung dari laki_laki + perempuan, bukan ->count() terpisah.
    | Query filter eselon-nya sendiri TIDAK diubah — sudah terverifikasi
    | benar (663).
    */
    $pejabatStrukturalQuery = Pegawai::query()
        ->leftJoin(
            'eselon',
            'eselon.id',
            '=',
            'pegawai.eselon_id'
        )
        ->where('pegawai.status_aktif', 'aktif')
        ->whereIn('eselon.kode', [
            'II A',
            'II B',
            'III A',
            'III B',
            'IV A',
            'IV B',
        ]);

    $pejabatStrukturalLakiLaki = (clone $pejabatStrukturalQuery)
        ->where(function ($q) {
            $q->where('pegawai.jenis_kelamin', 'L')
                ->orWhere('pegawai.jenis_kelamin', 'LAKI-LAKI');
        })
        ->count();

    $pejabatStrukturalPerempuan = (clone $pejabatStrukturalQuery)
        ->where(function ($q) {
            $q->where('pegawai.jenis_kelamin', 'P')
                ->orWhere('pegawai.jenis_kelamin', 'PEREMPUAN');
        })
        ->count();

    $pejabatStruktural = [
        'total' => $pejabatStrukturalLakiLaki + $pejabatStrukturalPerempuan,
        'laki_laki' => $pejabatStrukturalLakiLaki,
        'perempuan' => $pejabatStrukturalPerempuan,
    ];


    /*
    |--------------------------------------------------------------------------
    | 5.03.013.006
    | Pejabat ASN Pelaksana
    |--------------------------------------------------------------------------
    |
    | Tidak diubah — sudah terverifikasi benar (1795).
    */
    $pelaksana = $hitung(function ($query) {
        $query->whereRaw(
            'UPPER(TRIM(pegawai.jenis_kedudukan)) = ?',
            ['PELAKSANA']
        );
    });


    /*
    |--------------------------------------------------------------------------
    | 5.03.013.007
    | Anggota Tim Badan Pertimbangan dan Kepangkatan
    |--------------------------------------------------------------------------
    |
    | TIDAK DIUBAH. Data anggota Baperjakat belum punya field/relasi apa
    | pun di tabel pegawai — tidak ada kolom atau tabel referensi yang bisa
    | dipakai untuk query ini. Mengarang query terhadap kolom yang tidak
    | ada (mis. jabatan_tambahan) akan menghasilkan angka yang KELIHATAN
    | valid tapi sebenarnya salah/kosong, dan itu lebih berbahaya daripada
    | 0/0/0 yang jujur menyatakan "belum ada data". Perlu ditentukan dulu
    | sumber datanya (tabel baru? relasi ke SK penunjukan?) sebelum ini
    | bisa diimplementasikan sungguhan.
    */
    $anggotaTimBaperjakat = [
        'total' => 0,
        'laki_laki' => 0,
        'perempuan' => 0,
    ];


    /*
    |--------------------------------------------------------------------------
    | RESPONSE — struktur key TIDAK diubah, tetap sama seperti sebelumnya.
    |--------------------------------------------------------------------------
    */
    return [
        'kepala_daerah' => $kepalaDaerah,

        'mantri_pamong_praja' => $mantriPamongPraja,

        'lurah' => $lurah,

        'kepala_opd' => $kepalaOpd,

        'pejabat_asn_struktural' => $pejabatStruktural,

        'pejabat_asn_pelaksana' => $pelaksana,

        'anggota_tim_baperjakat' => $anggotaTimBaperjakat,
    ];
}

/**
 * 5.03.014
 * Jumlah ASN Kemantren berdasarkan Tingkat Pendidikan.
 *
 * Scope: 5.03.014.001 s.d. 5.03.014.001.10.14
 *
 * ASN = seluruh pegawai aktif (PNS + PPPK, semua jenis_kedudukan) yang
 * ber-UNIT salah satu dari 14 Kemantren Kota Yogyakarta. TIDAK dibatasi
 * ke jabatan PELAKSANA saja — beda dengan statistikStafDinasPendidikan()
 * yang scope-nya memang "Staf" (= JABATAN PELAKSANA). Kalau nanti scope
 * 5.03.014 ternyata dimaksudkan cuma staf, tinggal tambah satu where.
 *
 * Mapping jenjang pendidikan mengikuti mapping yang sudah dipakai di
 * statistikAsnPendidikan()/statistikStafDinasPendidikan() — sudah
 * divalidasi ke data mentah (S-1/Sarjana, Diploma III/Sarjana, S-2, SLTA,
 * SLTA Kejuruan, dst).
 *
 * Struktur hierarki kode taksonomi (pendidikan di atas, Kemantren di
 * bawahnya):
 * 5.03.014.001.01    -> pendidikan['sd']['total']
 * 5.03.014.001.01.01 -> pendidikan['sd']['kemantren']['TEGALREJO']
 * dst.
 *
 * $periode belum dipakai untuk filter (tabel pegawai belum punya kolom
 * periode/tahun), dipertahankan untuk konsistensi dengan method rekap*
 * lain di service ini.
 */
public function statistikAsnKemantrenPendidikan(?string $periode = null): array
{
    $kemantrenList = [
        'TEGALREJO', 'JETIS', 'GONDOKUSUMAN', 'DANUREJAN', 'GEDONGTENGEN',
        'NGAMPILAN', 'WIROBRAJAN', 'MANTRIJERON', 'KRATON', 'GONDOMANAN',
        'PAKUALAMAN', 'MERGANGSAN', 'UMBULHARJO', 'KOTAGEDE',
    ];

    $pendidikanList = ['SD', 'SLTP', 'SLTA', 'D I', 'D II', 'D III', 'D IV', 'S1', 'S2', 'S3'];

    $keyMap = [
        'SD'    => 'sd',
        'SLTP'  => 'smp',
        'SLTA'  => 'sma',
        'D I'   => 'diploma_i',
        'D II'  => 'diploma_ii',
        'D III' => 'diploma_iii',
        'D IV'  => 'diploma_iv',
        'S1'    => 'strata_1',
        'S2'    => 'strata_2',
        'S3'    => 'strata_3',
    ];

    $rows = Pegawai::query()
        ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
        ->leftJoin('pendidikan', 'pendidikan.id', '=', 'pegawai.pendidikan_id')
        ->select(
            'instansi.nama as instansi_nama',
            'pendidikan.jenjang as pendidikan_nama',
            DB::raw('COUNT(*) as jumlah')
        )
        ->where('pegawai.status_aktif', 'aktif')
        ->whereRaw("UPPER(TRIM(instansi.nama)) LIKE 'KEMANTREN %'")
        ->groupBy('instansi.nama', 'pendidikan.jenjang')
        ->get();

    // Siapkan struktur kosong dulu, supaya kemantren/pendidikan yang
    // datanya 0 tetap muncul di response (konsisten dengan pola
    // statistikPppkGolongan()/statistikStafDinasPendidikan()).
    $agregat = [];
    foreach ($pendidikanList as $jenjang) {
        $agregat[$jenjang] = array_fill_keys($kemantrenList, 0);
    }

    $tidakDikenali = 0;

    foreach ($rows as $row) {
        $namaKemantren = strtoupper(trim((string) $row->instansi_nama));
        // "KEMANTREN TEGALREJO" -> "TEGALREJO"
        $namaKemantren = trim(str_replace('KEMANTREN', '', $namaKemantren));

        if (!in_array($namaKemantren, $kemantrenList, true)) {
            continue;
        }

        $raw = $row->pendidikan_nama ? strtoupper(trim($row->pendidikan_nama)) : null;

        $jenjang = $raw ? match ($raw) {
            'SD', 'SEKOLAH DASAR'                            => 'SD',
            'SMP', 'SLTP'                                    => 'SLTP',
            'SMA', 'SMK', 'SMA/SMK', 'SLTA', 'SLTA KEJURUAN' => 'SLTA',
            'D1', 'D-1', 'D I', 'DIPLOMA I'                  => 'D I',
            'D2', 'D-2', 'D II', 'DIPLOMA II'                => 'D II',
            'D3', 'D-3', 'D III', 'DIPLOMA III/SARJANA'      => 'D III',
            'D4', 'D-4', 'D IV', 'D4/S1', 'DIPLOMA IV'       => 'D IV',
            'S1', 'S-1', 'S-1/SARJANA', 'SARJANA'            => 'S1',
            'S2', 'S-2', 'S-2/MAGISTER'                      => 'S2',
            'S3', 'S-3', 'S-3/DOKTOR'                        => 'S3',
            default => null,
        } : null;

        $jumlah = (int) $row->jumlah;

        if ($jenjang === null) {
            $tidakDikenali += $jumlah;

            if ($raw !== null) {
                Log::warning('StatistikAsnKemantrenPendidikan: jenjang pendidikan tidak dikenali', [
                    'instansi' => $row->instansi_nama,
                    'pendidikan_raw' => $row->pendidikan_nama,
                    'jumlah' => $jumlah,
                ]);
            }

            continue;
        }

        $agregat[$jenjang][$namaKemantren] += $jumlah;
    }

    $hasil = ['jumlah_asn_kemantren' => 0, 'pendidikan' => [], 'tidak_dikenali' => $tidakDikenali];

    foreach ($pendidikanList as $jenjang) {
        $perKemantren = $agregat[$jenjang];
        $totalJenjang = array_sum($perKemantren);

        $hasil['pendidikan'][$keyMap[$jenjang]] = [
            'total' => $totalJenjang,
            'kemantren' => $perKemantren,
        ];

        $hasil['jumlah_asn_kemantren'] += $totalJenjang;
    }

    $hasil['jumlah_asn_kemantren'] += $tidakDikenali;

    return $hasil;
}
/**
 * 5.03.015
 * Jumlah PNS Kemantren berdasarkan Tingkat Pendidikan dan Jenis Kelamin.
 *
 * Beda dengan statistikAsnKemantrenPendidikan() (5.03.014):
 * - Scope hanya PNS (status_kepegawaian = 'PNS'), PPPK tidak dihitung.
 * - Setiap jenjang pendidikan dipecah lagi per jenis kelamin, dan setiap
 *   gender dipecah lagi per kemantren (nested 3 level: pendidikan -> gender
 *   -> kemantren), bukan cuma pendidikan -> kemantren seperti 5.03.014.
 *
 * Mapping jenjang pendidikan & daftar kemantren mengikuti
 * statistikAsnKemantrenPendidikan() supaya konsisten.
 *
 * $periode belum dipakai untuk filter (tabel pegawai belum punya kolom
 * periode/tahun), dipertahankan untuk konsistensi dengan method rekap*
 * lain di service ini.
 */
public function statistikPnsKemantrenPendidikan(?string $periode = null): array
{
    $kemantrenList = [
        'TEGALREJO', 'JETIS', 'GONDOKUSUMAN', 'DANUREJAN', 'GEDONGTENGEN',
        'NGAMPILAN', 'WIROBRAJAN', 'MANTRIJERON', 'KRATON', 'GONDOMANAN',
        'PAKUALAMAN', 'MERGANGSAN', 'UMBULHARJO', 'KOTAGEDE',
    ];

    $pendidikanList = ['SD', 'SLTP', 'SLTA', 'D I', 'D II', 'D III', 'D IV', 'S1', 'S2', 'S3'];

    $keyMap = [
        'SD'    => 'sd',
        'SLTP'  => 'smp',
        'SLTA'  => 'sma',
        'D I'   => 'diploma_i',
        'D II'  => 'diploma_ii',
        'D III' => 'diploma_iii',
        'D IV'  => 'diploma_iv',
        'S1'    => 'strata_1',
        'S2'    => 'strata_2',
        'S3'    => 'strata_3',
    ];

    $rows = Pegawai::query()
        ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
        ->leftJoin('pendidikan', 'pendidikan.id', '=', 'pegawai.pendidikan_id')
        ->select(
            'instansi.nama as instansi_nama',
            'pendidikan.jenjang as pendidikan_nama',
            'pegawai.jenis_kelamin',
            DB::raw('COUNT(*) as jumlah')
        )
        ->where('pegawai.status_aktif', 'aktif')
        ->where('pegawai.status_kepegawaian', 'PNS')
        ->whereRaw("UPPER(TRIM(instansi.nama)) LIKE 'KEMANTREN %'")
        ->groupBy('instansi.nama', 'pendidikan.jenjang', 'pegawai.jenis_kelamin')
        ->get();

    // Siapkan struktur kosong: pendidikan -> gender -> kemantren,
    // supaya kombinasi yang datanya 0 tetap muncul di response.
    $agregat = [];
    foreach ($pendidikanList as $jenjang) {
        $agregat[$jenjang] = [
            'laki_laki' => array_fill_keys($kemantrenList, 0),
            'perempuan' => array_fill_keys($kemantrenList, 0),
        ];
    }

    $tidakDikenali = 0;

    foreach ($rows as $row) {
        $namaKemantren = strtoupper(trim((string) $row->instansi_nama));
        // "KEMANTREN TEGALREJO" -> "TEGALREJO"
        $namaKemantren = trim(str_replace('KEMANTREN', '', $namaKemantren));

        if (!in_array($namaKemantren, $kemantrenList, true)) {
            continue;
        }

        $raw = $row->pendidikan_nama ? strtoupper(trim($row->pendidikan_nama)) : null;

        $jenjang = $raw ? match ($raw) {
            'SD', 'SEKOLAH DASAR'                            => 'SD',
            'SMP', 'SLTP'                                    => 'SLTP',
            'SMA', 'SMK', 'SMA/SMK', 'SLTA', 'SLTA KEJURUAN' => 'SLTA',
            'D1', 'D-1', 'D I', 'DIPLOMA I'                  => 'D I',
            'D2', 'D-2', 'D II', 'DIPLOMA II'                => 'D II',
            'D3', 'D-3', 'D III', 'DIPLOMA III/SARJANA'      => 'D III',
            'D4', 'D-4', 'D IV', 'D4/S1', 'DIPLOMA IV'       => 'D IV',
            'S1', 'S-1', 'S-1/SARJANA', 'SARJANA'            => 'S1',
            'S2', 'S-2', 'S-2/MAGISTER'                      => 'S2',
            'S3', 'S-3', 'S-3/DOKTOR'                         => 'S3',
            default => null,
        } : null;

        $jumlah = (int) $row->jumlah;

        if ($jenjang === null) {
            $tidakDikenali += $jumlah;

            if ($raw !== null) {
                Log::warning('StatistikPnsKemantrenPendidikan: jenjang pendidikan tidak dikenali', [
                    'instansi' => $row->instansi_nama,
                    'pendidikan_raw' => $row->pendidikan_nama,
                    'jumlah' => $jumlah,
                ]);
            }

            continue;
        }

        $gender = $row->jenis_kelamin === 'L' ? 'laki_laki' : 'perempuan';

        $agregat[$jenjang][$gender][$namaKemantren] += $jumlah;
    }

    $hasil = ['jumlah_pns_kemantren' => 0, 'pendidikan' => [], 'tidak_dikenali' => $tidakDikenali];

    foreach ($pendidikanList as $jenjang) {
        $perKemantrenL = $agregat[$jenjang]['laki_laki'];
        $perKemantrenP = $agregat[$jenjang]['perempuan'];

        $totalL = array_sum($perKemantrenL);
        $totalP = array_sum($perKemantrenP);
        $totalJenjang = $totalL + $totalP;

        $hasil['pendidikan'][$keyMap[$jenjang]] = [
            'total' => $totalJenjang,
            'laki_laki' => [
                'total' => $totalL,
                'kemantren' => $perKemantrenL,
            ],
            'perempuan' => [
                'total' => $totalP,
                'kemantren' => $perKemantrenP,
            ],
        ];

        $hasil['jumlah_pns_kemantren'] += $totalJenjang;
    }

    $hasil['jumlah_pns_kemantren'] += $tidakDikenali;

    return $hasil;
}
/**
 * 5.03.016
 * Jumlah PPPK Kemantren berdasarkan Tingkat Pendidikan dan Jenis Kelamin.
 *
 * Struktur & logic PERSIS sama dengan statistikPnsKemantrenPendidikan()
 * (5.03.015) — bedanya hanya filter status_kepegawaian = 'PPPK'.
 * Nested 3 level: pendidikan -> gender -> kemantren.
 *
 * Mapping jenjang pendidikan & daftar kemantren mengikuti
 * statistikAsnKemantrenPendidikan() supaya konsisten.
 *
 * $periode belum dipakai untuk filter (tabel pegawai belum punya kolom
 * periode/tahun), dipertahankan untuk konsistensi dengan method rekap*
 * lain di service ini.
 */
public function statistikPppkKemantrenPendidikan(?string $periode = null): array
{
    $kemantrenList = [
        'TEGALREJO', 'JETIS', 'GONDOKUSUMAN', 'DANUREJAN', 'GEDONGTENGEN',
        'NGAMPILAN', 'WIROBRAJAN', 'MANTRIJERON', 'KRATON', 'GONDOMANAN',
        'PAKUALAMAN', 'MERGANGSAN', 'UMBULHARJO', 'KOTAGEDE',
    ];

    $pendidikanList = ['SD', 'SLTP', 'SLTA', 'D I', 'D II', 'D III', 'D IV', 'S1', 'S2', 'S3'];

    $keyMap = [
        'SD'    => 'sd',
        'SLTP'  => 'smp',
        'SLTA'  => 'sma',
        'D I'   => 'diploma_i',
        'D II'  => 'diploma_ii',
        'D III' => 'diploma_iii',
        'D IV'  => 'diploma_iv',
        'S1'    => 'strata_1',
        'S2'    => 'strata_2',
        'S3'    => 'strata_3',
    ];

    $rows = Pegawai::query()
        ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
        ->leftJoin('pendidikan', 'pendidikan.id', '=', 'pegawai.pendidikan_id')
        ->select(
            'instansi.nama as instansi_nama',
            'pendidikan.jenjang as pendidikan_nama',
            'pegawai.jenis_kelamin',
            DB::raw('COUNT(*) as jumlah')
        )
        ->where('pegawai.status_aktif', 'aktif')
        ->where('pegawai.status_kepegawaian', 'PPPK')
        ->whereRaw("UPPER(TRIM(instansi.nama)) LIKE 'KEMANTREN %'")
        ->groupBy('instansi.nama', 'pendidikan.jenjang', 'pegawai.jenis_kelamin')
        ->get();

    $agregat = [];
    foreach ($pendidikanList as $jenjang) {
        $agregat[$jenjang] = [
            'laki_laki' => array_fill_keys($kemantrenList, 0),
            'perempuan' => array_fill_keys($kemantrenList, 0),
        ];
    }

    $tidakDikenali = 0;

    foreach ($rows as $row) {
        $namaKemantren = strtoupper(trim((string) $row->instansi_nama));
        $namaKemantren = trim(str_replace('KEMANTREN', '', $namaKemantren));

        if (!in_array($namaKemantren, $kemantrenList, true)) {
            continue;
        }

        $raw = $row->pendidikan_nama ? strtoupper(trim($row->pendidikan_nama)) : null;

        $jenjang = $raw ? match ($raw) {
            'SD', 'SEKOLAH DASAR'                            => 'SD',
            'SMP', 'SLTP'                                    => 'SLTP',
            'SMA', 'SMK', 'SMA/SMK', 'SLTA', 'SLTA KEJURUAN' => 'SLTA',
            'D1', 'D-1', 'D I', 'DIPLOMA I'                  => 'D I',
            'D2', 'D-2', 'D II', 'DIPLOMA II'                => 'D II',
            'D3', 'D-3', 'D III', 'DIPLOMA III/SARJANA'      => 'D III',
            'D4', 'D-4', 'D IV', 'D4/S1', 'DIPLOMA IV'       => 'D IV',
            'S1', 'S-1', 'S-1/SARJANA', 'SARJANA'            => 'S1',
            'S2', 'S-2', 'S-2/MAGISTER'                      => 'S2',
            'S3', 'S-3', 'S-3/DOKTOR'                         => 'S3',
            default => null,
        } : null;

        $jumlah = (int) $row->jumlah;

        if ($jenjang === null) {
            $tidakDikenali += $jumlah;

            if ($raw !== null) {
                Log::warning('StatistikPppkKemantrenPendidikan: jenjang pendidikan tidak dikenali', [
                    'instansi' => $row->instansi_nama,
                    'pendidikan_raw' => $row->pendidikan_nama,
                    'jumlah' => $jumlah,
                ]);
            }

            continue;
        }

        $gender = $row->jenis_kelamin === 'L' ? 'laki_laki' : 'perempuan';

        $agregat[$jenjang][$gender][$namaKemantren] += $jumlah;
    }

    $hasil = ['jumlah_pppk_kemantren' => 0, 'pendidikan' => [], 'tidak_dikenali' => $tidakDikenali];

    foreach ($pendidikanList as $jenjang) {
        $perKemantrenL = $agregat[$jenjang]['laki_laki'];
        $perKemantrenP = $agregat[$jenjang]['perempuan'];

        $totalL = array_sum($perKemantrenL);
        $totalP = array_sum($perKemantrenP);
        $totalJenjang = $totalL + $totalP;

        $hasil['pendidikan'][$keyMap[$jenjang]] = [
            'total' => $totalJenjang,
            'laki_laki' => [
                'total' => $totalL,
                'kemantren' => $perKemantrenL,
            ],
            'perempuan' => [
                'total' => $totalP,
                'kemantren' => $perKemantrenP,
            ],
        ];

        $hasil['jumlah_pppk_kemantren'] += $totalJenjang;
    }

    $hasil['jumlah_pppk_kemantren'] += $tidakDikenali;

    return $hasil;
}
}

