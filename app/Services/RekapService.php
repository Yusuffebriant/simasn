<?php

namespace App\Services;

use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;

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
        'BELUM DIISI', // <-- tambahan
    ];

    $rows = Pegawai::query()
        ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
        ->leftJoin('pendidikan', 'pendidikan.id', '=', 'pegawai.pendidikan_id') // <-- diubah dari join
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
            ];
        }

        $pendidikan = $row->pendidikan_nama
            ? strtoupper(trim($row->pendidikan_nama))
            : null;

        $pendidikan = $pendidikan ? match ($pendidikan) {
            'SD'                                => 'SD',
            'SMP','SLTP'                          => 'SLTP',
            'SMA','SMK','SMA/SMK','SLTA'           => 'SLTA',
            'D1','D-1','D I'                      => 'D I',
            'D2','D-2','D II'                     => 'D II',
            'D3','D-3','D III'                    => 'D III',
            'D4','D-4','D IV','D4/S1'             => 'D IV',
            'S1','S-1','S-1/SARJANA','SARJANA'    => 'S1',
            'S2','S-2'                            => 'S2',
            'S3','S-3'                            => 'S3',
            default => 'BELUM DIISI', // <-- diubah dari null
        } : 'BELUM DIISI';

        $kelompok = $row->jenis_kelamin === 'L'
            ? 'pria'
            : 'wanita';

        $perInstansi[$id][$kelompok][$pendidikan] =
            ($perInstansi[$id][$kelompok][$pendidikan] ?? 0) + (int) $row->jumlah;
    }

    foreach ($perInstansi as &$data) {
        $data['jml_pria'] = array_sum($data['pria']);
        $data['jml_wanita'] = array_sum($data['wanita']);
        $data['jml_total'] =
            $data['jml_pria'] + $data['jml_wanita'];
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
        $rows = Pegawai::query()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->leftJoin('eselon', 'eselon.id', '=', 'pegawai.eselon_id')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'pegawai.jabatan',
                'eselon.kode as eselon_kode',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->groupBy(
                'instansi.id',
                'instansi.nama',
                'pegawai.jabatan',
                'eselon.kode'
            )
            ->orderBy('pegawai.jabatan')
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
            $jabatan = (string) $row->jabatan;

            if (in_array($kodeEselon, $eselonList, true)) {
                $perInstansi[$id]['eselon'][$kodeEselon] += $jumlah;
            } elseif ($this->isJabatanFungsional($jabatan)) {
                $perInstansi[$id]['fungsional_tertentu'] += $jumlah;
            } else {
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

    protected function isJabatanFungsional(string $jabatan): bool
    {
        return preg_match(
            '/FUNGSIONAL|GURU|DOKTER|PERAWAT|BIDAN|PENYULUH|AUDITOR|PENGAWAS|ANALIS|PRANATA|ARSIPARIS|PUSTAKAWAN|STATISTISI|WIDYAISWARA|MEDIS|PEKERJA SOSIAL/i',
            $jabatan
        ) === 1;
    }

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
                'pegawai.jabatan',
                'pegawai.tanggal_lahir',
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

        $golonganGroup = [
            'I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0,
            'PPPK' => 0, 'BELUM DIISI' => 0,
        ];

        $pendidikanList = [
            'SD', 'SLTP', 'SLTA', 'D I', 'D II', 'D III', 'D IV',
            'S1', 'S2', 'S3', 'BELUM DIISI',
        ];
        $pendidikanGroup = array_fill_keys($pendidikanList, 0);

        foreach ($rows as $row) {
            $isPria = $row->jenis_kelamin === 'L';
            $isPria ? $totalPria++ : $totalWanita++;

            $kodeEselon = preg_replace('/\s+/', ' ', strtoupper(trim((string) $row->eselon_kode)));

            if (in_array($kodeEselon, $eselonList, true)) {
                $isPria ? $strukturalPria++ : $strukturalWanita++;
            } elseif ($this->isJabatanFungsional((string) $row->jabatan)) {
                $jft++;
            } else {
                $jfu++;
            }

            if ($row->tanggal_lahir) {
                $tahun = (int) $row->tanggal_lahir->format('Y');

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

            $pnd = $pnd ? match ($pnd) {
                'SD' => 'SD',
                'SMP', 'SLTP' => 'SLTP',
                'SMA', 'SMK', 'SMA/SMK', 'SLTA' => 'SLTA',
                'D1', 'D-1', 'D I' => 'D I',
                'D2', 'D-2', 'D II' => 'D II',
                'D3', 'D-3', 'D III' => 'D III',
                'D4', 'D-4', 'D IV', 'D4/S1' => 'D IV',
                'S1', 'S-1', 'S-1/SARJANA', 'SARJANA' => 'S1',
                'S2', 'S-2' => 'S2',
                'S3', 'S-3' => 'S3',
                default => 'BELUM DIISI',
            } : 'BELUM DIISI';

            $pendidikanGroup[$pnd] = ($pendidikanGroup[$pnd] ?? 0) + 1;
        }

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
                fn ($label) => [
                    'label' => $label,
                    'pria' => $generasi[$label]['pria'],
                    'wanita' => $generasi[$label]['wanita'],
                    'total' => $generasi[$label]['pria'] + $generasi[$label]['wanita'],
                ],
                $generasiKeys
            )),
            'golongan' => array_map(
                fn ($label, $jumlah) => ['label' => $label, 'jumlah' => $jumlah],
                array_keys($golonganGroup),
                array_values($golonganGroup)
            ),
            'pendidikan' => array_map(
                fn ($label, $jumlah) => ['label' => $label, 'jumlah' => $jumlah],
                array_keys($pendidikanGroup),
                array_values($pendidikanGroup)
            ),
        ];
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
}