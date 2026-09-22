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
            'I/a',
            'I/b',
            'I/c',
            'I/d',
            'II/a',
            'II/b',
            'II/c',
            'II/d',
            'III/a',
            'III/b',
            'III/c',
            'III/d',
            'IV/a',
            'IV/b',
            'IV/c',
            'IV/d',
            'IV/e',
            'BELUM DIISI',
        ];
        $pppkList = ['I', 'III', 'V', 'VII', 'IX', 'X', 'XI'];

        $kolomList = array_merge($golonganList, $pppkList);

        $rows = Pegawai::query()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->leftJoin('golongan_ruang', 'golongan_ruang.id', '=', 'pegawai.golongan_ruang_id')
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

                $perInstansi[$id][$kelompok][$kode] =
                    ($perInstansi[$id][$kelompok][$kode] ?? 0) + $jumlah;

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
        // pegawai.jenis_kedudukan langsung (nilai: 'FUNGSIONAL',
        // 'PELAKSANA', 'STRUKTURAL', dst) — BUKAN menebak dari teks
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

    /**
     * Ringkasan dashboard: total pegawai, jabatan (struktural/JFU/JFT),
     * distribusi generasi, golongan, dan pendidikan — semuanya dari satu
     * query (tidak N+1).
     *
     * Klasifikasi JFU/JFT memakai pegawai.jenis_kedudukan, sama seperti
     * rekapJabatan() (bukan regex isJabatanFungsional() versi lama —
     * method itu sudah dihapus karena sudah digantikan pendekatan ini).
     *
     * Mapping pendidikan di bawah disamakan persis dengan rekapPendidikan()
     * (termasuk varian SEKOLAH DASAR, SLTA KEJURUAN, DIPLOMA I-IV,
     * S-3/DOKTOR) supaya tidak mengulang bug lama di mana varian teks tak
     * dikenal membuat pegawai "hilang" dari kategori pendidikannya.
     */
    public function rekapDashboard(?string $periode = null): array
    {
        $eselonList = ['II A', 'II B', 'III A', 'III B', 'IV A', 'IV B'];

        $rows = Pegawai::query()
            ->leftJoin('golongan_ruang', 'golongan_ruang.id', '=', 'pegawai.golongan_ruang_id')
            ->leftJoin('eselon', 'eselon.id', '=', 'pegawai.eselon_id')
            ->leftJoin('pendidikan', 'pendidikan.id', '=', 'pegawai.pendidikan_id')
            ->where('pegawai.status_aktif', 'aktif')
            ->select(
                'pegawai.jenis_kelamin',
                'pegawai.jenis_kedudukan',
                'pegawai.tanggal_lahir',
                'pegawai.tmt_pangkat',
                'golongan_ruang.kode as golongan_kode',
                'golongan_ruang.kelompok as golongan_kelompok',
                'eselon.kode as eselon_kode',
                'pendidikan.jenjang as pendidikan_jenjang'
            )
            ->get();

        $totalPria = 0;
        $totalWanita = 0;

        $strukturalPria = 0;
        $strukturalWanita = 0;
        $jfu = 0;
        $jft = 0;

        $generasiKeys = ['Baby Boomer', 'Generasi X', 'Generasi Y', 'Generasi Z'];
        $generasi = [];
        foreach ($generasiKeys as $g) {
            $generasi[$g] = ['pria' => 0, 'wanita' => 0];
        }

        $mkPangkatKeys = ['s.d. 10 Tahun', '11 - 20 Tahun', '21 - 30 Tahun', '30 Tahun Keatas'];
        $mkPangkat = [];
        foreach ($mkPangkatKeys as $mk) {
            $mkPangkat[$mk] = ['pria' => 0, 'wanita' => 0];
        }

        $usiaKeys = ['s.d. 25 Tahun', '26 - 35 Tahun', '36 - 45 Tahun', '46 - 55 Tahun', '56 Tahun atau Lebih'];
        $usia = [];
        foreach ($usiaKeys as $u) {
            $usia[$u] = ['pria' => 0, 'wanita' => 0];
        }

        $golonganGroup = [
            'I' => 0,
            'II' => 0,
            'III' => 0,
            'IV' => 0,
            'PPPK' => 0,
            'BELUM DIISI' => 0,
        ];

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
            'BELUM DIISI',
        ];
        $pendidikanGroup = [];
        foreach ($pendidikanList as $pl) {
            $pendidikanGroup[$pl] = ['pria' => 0, 'wanita' => 0];
        }

        foreach ($rows as $row) {
            $isPria = $row->jenis_kelamin === 'L';
            $isPria ? $totalPria++ : $totalWanita++;

            $kodeEselon = preg_replace('/\s+/', ' ', strtoupper(trim((string) $row->eselon_kode)));
            $jenisKedudukan = strtoupper(trim((string) $row->jenis_kedudukan));

            if (in_array($kodeEselon, $eselonList, true)) {
                $isPria ? $strukturalPria++ : $strukturalWanita++;
            } elseif ($jenisKedudukan === 'FUNGSIONAL') {
                $jft++;
            } else {
                $jfu++;
            }

            if ($row->tanggal_lahir) {
                $tgl = trim((string) $row->tanggal_lahir);
                $tahun = null;

                if (preg_match('/^(\d{4})[-\/]/', $tgl, $matches)) {
                    $tahun = (int) $matches[1];
                } elseif (preg_match('/^\d{1,2}[-\/]\d{1,2}[-\/](\d{4})/', $tgl, $matches)) {
                    $tahun = (int) $matches[1];
                }

                if ($tahun !== null) {
                    $g = match (true) {
                        $tahun >= 1946 && $tahun <= 1964 => 'Baby Boomer',
                        $tahun >= 1965 && $tahun <= 1980 => 'Generasi X',
                        $tahun >= 1981 && $tahun <= 1996 => 'Generasi Y',
                        $tahun >= 1997 && $tahun <= 2012 => 'Generasi Z',
                        default => null,
                    };

                    if ($g) {
                        $generasi[$g][$isPria ? 'pria' : 'wanita']++;
                    }
                }

                $usiaTahun = $row->tanggal_lahir->diffInYears(now());

                $kategoriUsia = match (true) {
                    $usiaTahun <= 25 => 's.d. 25 Tahun',
                    $usiaTahun <= 35 => '26 - 35 Tahun',
                    $usiaTahun <= 45 => '36 - 45 Tahun',
                    $usiaTahun <= 55 => '46 - 55 Tahun',
                    default => '56 Tahun atau Lebih',
                };

                $usia[$kategoriUsia][$isPria ? 'pria' : 'wanita']++;
            }

            if ($row->tmt_pangkat) {
                $masaKerja = $row->tmt_pangkat->diffInYears(now());

                $kategoriMk = match (true) {
                    $masaKerja <= 10 => 's.d. 10 Tahun',
                    $masaKerja <= 20 => '11 - 20 Tahun',
                    $masaKerja <= 30 => '21 - 30 Tahun',
                    default => '30 Tahun Keatas',
                };

                $mkPangkat[$kategoriMk][$isPria ? 'pria' : 'wanita']++;
            }

            if (!$row->golongan_kode) {
                $golonganGroup['BELUM DIISI']++;
            } elseif ($row->golongan_kelompok === 'PPPK') {
                $golonganGroup['PPPK']++;
            } else {
                $romawi = explode('/', trim($row->golongan_kode))[0] ?? null;
                if (isset($golonganGroup[$romawi])) {
                    $golonganGroup[$romawi]++;
                }
            }

            $pnd = $row->pendidikan_jenjang ? strtoupper(trim($row->pendidikan_jenjang)) : null;
            $pnd = $pnd ? preg_replace('/\s+/', ' ', $pnd) : null;

            $pnd = $pnd ? match ($pnd) {
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
                default => 'BELUM DIISI',
            } : 'BELUM DIISI';

            $pendidikanGroup[$pnd][$isPria ? 'pria' : 'wanita']++;
        }

        // Rekap per Unit Kerja (instansi)
        $unitKerjaRows = Pegawai::query()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->where('pegawai.status_aktif', 'aktif')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->groupBy('instansi.id', 'instansi.nama', 'pegawai.jenis_kelamin')
            ->get();

        $unitKerjaMap = [];
        foreach ($unitKerjaRows as $row) {
            $id = $row->instansi_id;

            if (!isset($unitKerjaMap[$id])) {
                $unitKerjaMap[$id] = [
                    'label' => $row->instansi_nama,
                    'pria' => 0,
                    'wanita' => 0,
                ];
            }

            if ($row->jenis_kelamin === 'L') {
                $unitKerjaMap[$id]['pria'] = (int) $row->jumlah;
            } else {
                $unitKerjaMap[$id]['wanita'] = (int) $row->jumlah;
            }
        }

        $unitKerja = array_values($unitKerjaMap);

        foreach ($unitKerja as &$uk) {
            $uk['total'] = $uk['pria'] + $uk['wanita'];
        }
        unset($uk);

        usort($unitKerja, fn($a, $b) => strcmp($a['label'], $b['label']));

        // Rekap per Agama
        $agamaRows = Pegawai::query()
            ->join('agama', 'agama.id', '=', 'pegawai.agama_id')
            ->where('pegawai.status_aktif', 'aktif')
            ->select(
                'agama.id as agama_id',
                'agama.nama as agama_nama',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->groupBy('agama.id', 'agama.nama', 'pegawai.jenis_kelamin')
            ->get();

        $agamaMap = [];
        foreach ($agamaRows as $row) {
            $id = $row->agama_id;

            if (!isset($agamaMap[$id])) {
                $agamaMap[$id] = [
                    'label' => $row->agama_nama,
                    'pria' => 0,
                    'wanita' => 0,
                ];
            }

            if ($row->jenis_kelamin === 'L') {
                $agamaMap[$id]['pria'] = (int) $row->jumlah;
            } else {
                $agamaMap[$id]['wanita'] = (int) $row->jumlah;
            }
        }

        $agama = array_values($agamaMap);

        foreach ($agama as &$ag) {
            $ag['total'] = $ag['pria'] + $ag['wanita'];
        }
        unset($ag);

        usort($agama, fn($a, $b) => strcmp($a['label'], $b['label']));

        return [
            'total' => [
                'total' => $totalPria + $totalWanita,
                'pria' => $totalPria,
                'wanita' => $totalWanita,
            ],
            'jabatan' => [
                'struktural' => [
                    'total' => $strukturalPria + $strukturalWanita,
                    'pria' => $strukturalPria,
                    'wanita' => $strukturalWanita,
                ],
                'jfu' => $jfu,
                'jft' => $jft,
            ],
            'generasi' => array_values(array_map(
                fn($label) => [
                    'label' => $label,
                    'pria' => $generasi[$label]['pria'],
                    'wanita' => $generasi[$label]['wanita'],
                    'total' => $generasi[$label]['pria'] + $generasi[$label]['wanita'],
                ],
                $generasiKeys
            )),
            'masa_kerja_pangkat' => array_values(array_map(
                fn($label) => [
                    'label' => $label,
                    'pria' => $mkPangkat[$label]['pria'],
                    'wanita' => $mkPangkat[$label]['wanita'],
                    'total' => $mkPangkat[$label]['pria'] + $mkPangkat[$label]['wanita'],
                ],
                $mkPangkatKeys
            )),
            'usia' => array_values(array_map(
                fn($label) => [
                    'label' => $label,
                    'pria' => $usia[$label]['pria'],
                    'wanita' => $usia[$label]['wanita'],
                    'total' => $usia[$label]['pria'] + $usia[$label]['wanita'],
                ],
                $usiaKeys
            )),
            'golongan' => array_map(
                fn($label, $jumlah) => ['label' => $label, 'jumlah' => $jumlah],
                array_keys($golonganGroup),
                array_values($golonganGroup)
            ),
            'pendidikan' => array_map(
                fn($label) => [
                    'label' => $label,
                    'pria' => $pendidikanGroup[$label]['pria'],
                    'wanita' => $pendidikanGroup[$label]['wanita'],
                    'jumlah' => $pendidikanGroup[$label]['pria'] + $pendidikanGroup[$label]['wanita'],
                ],
                $pendidikanList
            ),
            'unit_kerja' => $unitKerja,
            'agama' => $agama,
        ];
    }

    public function rekapEselonGolonganGender(?string $periode = null): array
    {
        $golonganList = ['III/a', 'III/b', 'III/c', 'III/d', 'IV/a', 'IV/b', 'IV/c', 'IV/d', 'IV/e'];
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
    private const NAKES_LIST = [
        'UPT PUSKESMAS DANUREJAN 1',
        'UPT PUSKESMAS DANUREJAN 2',
        'UPT PUSKESMAS GEDONGTENGEN',
        'UPT PUSKESMAS GONDOKUSUMAN 1',
        'UPT PUSKESMAS GONDOKUSUMAN 2',
        'UPT PUSKESMAS GONDOMANAN',
        'UPT PUSKESMAS JETIS',
        'UPT PUSKESMAS KOTAGEDE 1',
        'UPT PUSKESMAS KOTAGEDE 2',
        'UPT PUSKESMAS KRATON',
        'UPT PUSKESMAS MANTRIJERON',
        'UPT PUSKESMAS MERGANGSAN',
        'UPT PUSKESMAS NGAMPILAN',
        'UPT PUSKESMAS PAKUALAMAN',
        'UPT PUSKESMAS TEGALREJO',
        'UPT PUSKESMAS UMBULHARJO 1',
        'UPT PUSKESMAS UMBULHARJO 2',
        'UPT PUSKESMAS WIROBRAJAN',
        'RUMAH SAKIT PRATAMA',
    ];

    private const NAKES_RSUD_UNIT = 'RUMAH SAKIT UMUM DAERAH KOTA YOGYAKARTA';

    /**
     * Peta Kemantren -> daftar Kelurahan di bawahnya, Kota Yogyakarta
     * (14 Kemantren, 45 Kelurahan). Dipakai oleh rekapKecamatanKelurahan()
     * untuk membangun baris output & mencocokkan sub_unit pegawai.
     *
     * FIX: konstanta ini sebelumnya dipakai di rekapKecamatanKelurahan()
     * tapi tidak pernah didefinisikan -> Fatal Error "Undefined constant"
     * (500 Internal Server Error) setiap kali endpoint /rekap/kecamatan
     * diakses. Datanya diambil dari database/seeders/AlamatWilayahSeeder.php
     * supaya konsisten dengan data alamat yang sudah di-seed ke
     * tabel alamat_wilayah.
     */
    private const KEMANTREN_KELURAHAN_MAP = [
        'MANTRIJERON' => ['GEDONGKIWO', 'SURYODININGRATAN', 'MANTRIJERON'],
        'KRATON' => ['PATEHAN', 'PANEMBAHAN', 'KADIPATEN'],
        'MERGANGSAN' => ['BRONTOKUSUMAN', 'WIROGUNAN', 'KEPARAKAN'],
        'UMBULHARJO' => ['GIWANGAN', 'SOROSUTAN', 'PANDEYAN', 'WARUNGBOTO', 'TAHUNAN', 'MUJA-MUJU', 'SEMAKI'],
        'KOTAGEDE' => ['PRENGGAN', 'PURBAYAN', 'REJOWINANGUN'],
        'GONDOKUSUMAN' => ['BACIRO', 'DEMANGAN', 'KLITREN', 'KOTABARU', 'TERBAN'],
        'DANUREJAN' => ['SURYATMAJAN', 'TEGALPANGGUNG', 'BAUSASRAN'],
        'PAKUALAMAN' => ['PURWOKINANTI', 'GUNUNGKETUR'],
        'GONDOMANAN' => ['PRAWIRODIRJAN', 'NGUPASAN'],
        'NGAMPILAN' => ['NOTOPRAJAN', 'NGAMPILAN'],
        'WIROBRAJAN' => ['PATANGPULUHAN', 'WIROBRAJAN', 'PAKUNCEN'],
        'GEDONGTENGEN' => ['PRINGGOKUSUMAN', 'SOSROMENDURAN'],
        'JETIS' => ['BUMIJO', 'GOWONGAN', 'COKRODININGRATAN'],
        'TEGALREJO' => ['KRICAK', 'KARANGWARU', 'TEGALREJO', 'BENER'],
    ];

    /**
     * Rekap Pejabat Fungsional Nakes (Puskesmas, RS Pratama, RSUD).
     *
     * REVISI PENTING (setelah cek data dummy asli): 18 Puskesmas + RS Pratama
     * TIDAK punya nama fasilitas di kolom unit — semuanya unit='DINAS
     * KESEHATAN', nama fasilitas ada di sub_unit (kadang persis "UPT
     * PUSKESMAS X", kadang berprefix "SUB BAGIAN TATA USAHA UPT PUSKESMAS X"
     * untuk staf TU-nya), makanya dicocokkan pakai str_contains bukan exact
     * match.
     *
     * RSUD Kota Yogyakarta BEDA STRUKTUR — dia OPD/instansi sendiri (bukan UPT
     * di bawah Dinas Kesehatan), jadi unit-nya LANGSUNG bernilai nama RSUD
     * tsb, dan sub_unit isinya nama bidang/bagian internal (Bidang Pelayanan
     * Medis, dst). Untuk RSUD, SELURUH staf dengan unit tsb dihitung,
     * terlepas dari sub_unit/bidang penempatannya.
     *
     * Filter jenis_kedudukan='FUNGSIONAL' sesuai cakupan laporan asli
     * ("Data Pejabat Fungsional Nakes") — otomatis mengecualikan staf TU
     * administratif (jenis_kedudukan PELAKSANA) walau sub_unit-nya mengandung
     * nama fasilitas yang sama.
     */
    public function rekapNakes(?string $periode = null): array
    {
        $rowsDinkes = Pegawai::query()
            ->select('sub_unit', 'jenis_kelamin', DB::raw('COUNT(*) as jumlah'))
            ->where('status_aktif', 'aktif')
            ->whereRaw("UPPER(TRIM(jenis_kedudukan)) = 'FUNGSIONAL'")
            ->whereRaw("UPPER(TRIM(unit)) = 'DINAS KESEHATAN'")
            ->whereNotNull('sub_unit')
            ->groupBy('sub_unit', 'jenis_kelamin')
            ->get();

        $agregat = [];
        foreach (self::NAKES_LIST as $nama) {
            $agregat[$nama] = ['pria' => 0, 'wanita' => 0];
        }

        foreach ($rowsDinkes as $row) {
            $subUnit = strtoupper(trim((string) $row->sub_unit));
            $jumlah = (int) $row->jumlah;
            $kelompok = $row->jenis_kelamin === 'L' ? 'pria' : 'wanita';

            foreach (self::NAKES_LIST as $nama) {
                if (str_contains($subUnit, $nama)) {
                    $agregat[$nama][$kelompok] += $jumlah;
                    break; // satu sub_unit cuma cocok ke 1 fasilitas
                }
            }
        }

        $rowsRsud = Pegawai::query()
            ->select('jenis_kelamin', DB::raw('COUNT(*) as jumlah'))
            ->where('status_aktif', 'aktif')
            ->whereRaw("UPPER(TRIM(jenis_kedudukan)) = 'FUNGSIONAL'")
            ->whereRaw('UPPER(TRIM(unit)) = ?', [self::NAKES_RSUD_UNIT])
            ->groupBy('jenis_kelamin')
            ->get();

        $rsudPria = 0;
        $rsudWanita = 0;
        foreach ($rowsRsud as $row) {
            $row->jenis_kelamin === 'L' ? $rsudPria += (int) $row->jumlah : $rsudWanita += (int) $row->jumlah;
        }

        $alamatMap = \App\Models\AlamatFasilitas::where('jenis', 'nakes')->pluck('alamat', 'nama');

        $hasil = [];
        foreach (self::NAKES_LIST as $nama) {
            $pria = $agregat[$nama]['pria'];
            $wanita = $agregat[$nama]['wanita'];

            $hasil[] = [
                'fasilitas' => $nama,
                'alamat' => $alamatMap[$nama] ?? null,
                'pria' => $pria,
                'wanita' => $wanita,
                'jumlah' => $pria + $wanita,
            ];
        }

        $hasil[] = [
            'fasilitas' => self::NAKES_RSUD_UNIT,
            'alamat' => $alamatMap[self::NAKES_RSUD_UNIT] ?? null,
            'pria' => $rsudPria,
            'wanita' => $rsudWanita,
            'jumlah' => $rsudPria + $rsudWanita,
        ];

        return $hasil;
    }
    /**
     * Rekap Guru Fungsional per SD Negeri.
     *
     * REVISI: nama sekolah ternyata ada di kolom sub_unit, BUKAN unit
     * (unit-nya sama untuk semua guru: "DINAS PENDIDIKAN PEMUDA DAN
     * OLAHRAGA"). Tidak pakai daftar sekolah hardcode — GROUP BY sub_unit
     * otomatis menangkap sekolah manapun yang namanya diawali "SD NEGERI".
     */
    public function rekapSdFungsional(?string $periode = null): array
    {
        $rows = Pegawai::query()
            ->select('sub_unit', 'jenis_kelamin', DB::raw('COUNT(*) as jumlah'))
            ->where('status_aktif', 'aktif')
            ->whereRaw("UPPER(TRIM(jenis_kedudukan)) = 'FUNGSIONAL'")
            ->whereRaw("UPPER(TRIM(unit)) = 'DINAS PENDIDIKAN PEMUDA DAN OLAHRAGA'")
            ->whereRaw("UPPER(TRIM(sub_unit)) LIKE 'SD NEGERI%'")
            ->groupBy('sub_unit', 'jenis_kelamin')
            ->get();

        return $this->aggregasiSekolah($rows);
    }

    /**
     * Rekap Guru Fungsional per SMP Negeri. Logic sama seperti
     * rekapSdFungsional(), hanya beda prefix nama sekolah.
     */
    public function rekapSmpFungsional(?string $periode = null): array
    {
        $rows = Pegawai::query()
            ->select('sub_unit', 'jenis_kelamin', DB::raw('COUNT(*) as jumlah'))
            ->where('status_aktif', 'aktif')
            ->whereRaw("UPPER(TRIM(jenis_kedudukan)) = 'FUNGSIONAL'")
            ->whereRaw("UPPER(TRIM(unit)) = 'DINAS PENDIDIKAN PEMUDA DAN OLAHRAGA'")
            ->whereRaw("UPPER(TRIM(sub_unit)) LIKE 'SMP NEGERI%'")
            ->groupBy('sub_unit', 'jenis_kelamin')
            ->get();

        return $this->aggregasiSekolah($rows);
    }

    private function aggregasiSekolah($rows): array
    {
        $agregat = [];

        foreach ($rows as $row) {
            $nama = trim((string) $row->sub_unit); // <- diambil dari sub_unit
            $key = strtoupper($nama);

            if (!isset($agregat[$key])) {
                $agregat[$key] = ['nama' => $nama, 'pria' => 0, 'wanita' => 0];
            }

            $kelompok = $row->jenis_kelamin === 'L' ? 'pria' : 'wanita';
            $agregat[$key][$kelompok] += (int) $row->jumlah;
        }

        ksort($agregat);

        $hasil = [];
        foreach ($agregat as $data) {
            $hasil[] = [
                'sekolah' => $data['nama'],
                'pria' => $data['pria'],
                'wanita' => $data['wanita'],
                'jumlah' => $data['pria'] + $data['wanita'],
            ];
        }

        return $hasil;
    }

    /**
     * Rekap PNS Kecamatan (Kemantren) & Kelurahan berdasarkan Jenis
     * Kedudukan (Fungsional/Struktural/Pelaksana) dan Jenis Kelamin,
     * dilengkapi alamat kantor dari alamat_wilayah.
     *
     * Struktur STRUKTURAL ditentukan dari eselon.kode (bukan
     * jenis_kedudukan), karena kolom itu masih NULL untuk sebagian pegawai
     * lama yang belum backfill — lihat docblock statistikPejabatStruktural()
     * di StatistikService untuk detail alasannya.
     *
     * Return berbentuk flat array: satu baris = satu Kelurahan, dengan info
     * Kemantren induk HANYA diisi di baris pertama tiap grup (baris
     * selanjutnya null) — supaya gampang di-export dengan merge cell meniru
     * tampilan Excel aslinya.
     */
    public function rekapKecamatanKelurahan(?string $periode = null): array
    {
        $eselonStruktural = ['II A', 'II B', 'III A', 'III B', 'IV A', 'IV B'];

        $rows = Pegawai::query()
            ->leftJoin('eselon', 'eselon.id', '=', 'pegawai.eselon_id')
            ->select(
                'pegawai.unit',
                'pegawai.sub_unit',
                'pegawai.jenis_kedudukan',
                'eselon.kode as eselon_kode',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->where('pegawai.status_kepegawaian', 'PNS')
            ->whereNotNull('pegawai.unit')
            ->whereRaw("UPPER(TRIM(pegawai.unit)) LIKE 'KEMANTREN %'")
            ->groupBy('pegawai.unit', 'pegawai.sub_unit', 'pegawai.jenis_kedudukan', 'eselon.kode', 'pegawai.jenis_kelamin')
            ->get();

        $agregat = [];
        foreach (self::KEMANTREN_KELURAHAN_MAP as $kemantren => $kelurahanList) {
            $agregat[$kemantren] = [
                'kecamatan' => [
                    'fungsional' => ['laki_laki' => 0, 'perempuan' => 0],
                    'struktural' => ['laki_laki' => 0, 'perempuan' => 0],
                    'pelaksana' => ['laki_laki' => 0, 'perempuan' => 0],
                ],
                'kelurahan' => array_fill_keys($kelurahanList, [
                    'struktural' => ['laki_laki' => 0, 'perempuan' => 0],
                    'pelaksana' => ['laki_laki' => 0, 'perempuan' => 0],
                ]),
            ];
        }

        foreach ($rows as $row) {
            $unit = strtoupper(trim((string) $row->unit));
            $namaKemantren = trim(str_replace('KEMANTREN', '', $unit));

            if (!isset($agregat[$namaKemantren])) {
                continue;
            }

            $jumlah = (int) $row->jumlah;
            $gender = $row->jenis_kelamin === 'L' ? 'laki_laki' : 'perempuan';

            $eselonKode = strtoupper(trim((string) $row->eselon_kode));
            $jenisKedudukan = strtoupper(trim((string) $row->jenis_kedudukan));

            if (in_array($eselonKode, $eselonStruktural, true)) {
                $kedudukan = 'struktural';
            } elseif ($jenisKedudukan === 'PELAKSANA') {
                $kedudukan = 'pelaksana';
            } elseif ($jenisKedudukan === 'FUNGSIONAL') {
                $kedudukan = 'fungsional';
            } else {
                $kedudukan = null;
            }

            $subUnit = strtoupper(trim((string) $row->sub_unit));
            $isKelurahan = $subUnit !== '' && str_contains($subUnit, 'KELURAHAN');

            if ($isKelurahan) {
                $kelurahanDitemukan = null;
                foreach (self::KEMANTREN_KELURAHAN_MAP[$namaKemantren] as $kelurahan) {
                    if (str_contains($subUnit, 'KELURAHAN ' . $kelurahan)) {
                        $kelurahanDitemukan = $kelurahan;
                        break;
                    }
                }

                if ($kelurahanDitemukan !== null && in_array($kedudukan, ['struktural', 'pelaksana'], true)) {
                    $agregat[$namaKemantren]['kelurahan'][$kelurahanDitemukan][$kedudukan][$gender] += $jumlah;
                }
            } elseif ($kedudukan !== null) {
                $agregat[$namaKemantren]['kecamatan'][$kedudukan][$gender] += $jumlah;
            }
        }

        $alamatKemantren = \App\Models\AlamatWilayah::where('jenis', 'kemantren')->pluck('alamat', 'kemantren');
        $alamatKelurahan = \App\Models\AlamatWilayah::where('jenis', 'kelurahan')->get()
            ->keyBy(fn($r) => $r->kemantren . '|' . $r->kelurahan);

        $hasil = [];

        foreach (self::KEMANTREN_KELURAHAN_MAP as $kemantren => $kelurahanList) {
            $kec = $agregat[$kemantren]['kecamatan'];
            $first = true;

            foreach ($kelurahanList as $kelurahan) {
                $kel = $agregat[$kemantren]['kelurahan'][$kelurahan];
                $alamatKel = $alamatKelurahan->get($kemantren . '|' . $kelurahan);

                $hasil[] = [
                    'kemantren' => $first ? $kemantren : null,
                    'kemantren_alamat' => $first ? ($alamatKemantren[$kemantren] ?? null) : null,
                    'fungsional_l' => $first ? $kec['fungsional']['laki_laki'] : null,
                    'fungsional_p' => $first ? $kec['fungsional']['perempuan'] : null,
                    'struktural_l' => $first ? $kec['struktural']['laki_laki'] : null,
                    'struktural_p' => $first ? $kec['struktural']['perempuan'] : null,
                    'pelaksana_l' => $first ? $kec['pelaksana']['laki_laki'] : null,
                    'pelaksana_p' => $first ? $kec['pelaksana']['perempuan'] : null,
                    // Rollup total gender Kecamatan (Fungsional+Struktural+Pelaksana
                    // digabung jadi satu angka L, satu angka P) — sesuai kotak
                    // ringkasan di pojok kanan Excel asli (baris 74-78).
                    'kecamatan_total_l' => $first
                        ? $kec['fungsional']['laki_laki'] + $kec['struktural']['laki_laki'] + $kec['pelaksana']['laki_laki']
                        : null,
                    'kecamatan_total_p' => $first
                        ? $kec['fungsional']['perempuan'] + $kec['struktural']['perempuan'] + $kec['pelaksana']['perempuan']
                        : null,
                    'kelurahan' => $kelurahan,
                    'kelurahan_alamat' => $alamatKel->alamat ?? null,
                    'kel_struktural_l' => $kel['struktural']['laki_laki'],
                    'kel_struktural_p' => $kel['struktural']['perempuan'],
                    'kel_pelaksana_l' => $kel['pelaksana']['laki_laki'],
                    'kel_pelaksana_p' => $kel['pelaksana']['perempuan'],
                    // Rollup total gender Kelurahan (Struktural+Pelaksana digabung).
                    'kelurahan_total_l' => $kel['struktural']['laki_laki'] + $kel['pelaksana']['laki_laki'],
                    'kelurahan_total_p' => $kel['struktural']['perempuan'] + $kel['pelaksana']['perempuan'],
                    'jumlah_baris_kemantren' => $first ? count($kelurahanList) : null,
                ];

                $first = false;
            }
        }

        return $hasil;
    }
}
