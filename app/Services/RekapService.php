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
        ];

        $rows = Pegawai::query()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->join('pendidikan', 'pendidikan.id', '=', 'pegawai.pendidikan_id')
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

            $pendidikan = strtoupper(trim($row->pendidikan_nama));

            $pendidikan = match ($pendidikan) {
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
                default => null,
            };

            if (!$pendidikan) {
                continue;
            }

            $kelompok = $row->jenis_kelamin === 'L'
                ? 'pria'
                : 'wanita';

            $perInstansi[$id][$kelompok][$pendidikan] =
                (int) $row->jumlah;
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
    ];

    $rows = Pegawai::query()
        ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
        ->join('golongan_ruang', 'golongan_ruang.id', '=', 'pegawai.golongan_ruang_id')
        ->select(
            'instansi.id as instansi_id',
            'instansi.nama as instansi_nama',
            'golongan_ruang.kode as golongan_kode',
            'pegawai.jenis_kelamin',
            DB::raw('COUNT(*) as jumlah')
        )
        ->where('pegawai.status_aktif', 'aktif')
        ->groupBy(
            'instansi.id',
            'instansi.nama',
            'golongan_ruang.kode',
            'pegawai.jenis_kelamin'
        )
        ->get();

    $perInstansi = [];

    foreach ($rows as $row) {
        $id = $row->instansi_id;

        if (!isset($perInstansi[$id])) {
            $perInstansi[$id] = [
                'instansi' => $row->instansi_nama,
                'pria' => array_fill_keys($golonganList, 0),
                'wanita' => array_fill_keys($golonganList, 0),
            ];
        }

        $kode = trim($row->golongan_kode);

        if (!in_array($kode, $golonganList, true)) {
            continue;
        }

        $kelompok = $row->jenis_kelamin === 'L'
            ? 'pria'
            : 'wanita';

        $perInstansi[$id][$kelompok][$kode] =
            (int) $row->jumlah;
    }

    foreach ($perInstansi as &$data) {
        $data['jml_pria'] = array_sum($data['pria']);
        $data['jml_wanita'] = array_sum($data['wanita']);
        $data['jml_total'] =
            $data['jml_pria'] + $data['jml_wanita'];
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
            ->whereNotNull('pegawai.jabatan')
            ->where('pegawai.jabatan', '<>', '')
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

            if (in_array($kodeEselon, $eselonList, true)) {
                $perInstansi[$id]['eselon'][$kodeEselon] += $jumlah;
            }

            if ($this->isJabatanFungsional($row->jabatan)) {
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