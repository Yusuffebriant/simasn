<?php

namespace App\Services;

use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;

class RekapService
{
    /**
     * Rekap per Instansi x Agama x Jenis Kelamin.
     * Struktur output meniru sheet 'agama' di data-asn-agustus-2026-22675.xlsx
     */
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
            ->groupBy('instansi.id', 'instansi.nama', 'agama.nama', 'pegawai.jenis_kelamin')
            ->get();

        // Susun ulang jadi struktur per-instansi: {pria: {Islam: n, ...}, wanita: {...}}
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

            $kelompok = $row->jenis_kelamin === 'L' ? 'pria' : 'wanita';

            if (in_array($row->agama_nama, $agamaList)) {
                $perInstansi[$id][$kelompok][$row->agama_nama] = (int) $row->jumlah;
            }
        }

        // Hitung subtotal per baris (JML pria, JML wanita, JML TOTAL)
        foreach ($perInstansi as &$data) {
            $data['jml_pria'] = array_sum($data['pria']);
            $data['jml_wanita'] = array_sum($data['wanita']);
            $data['jml_total'] = $data['jml_pria'] + $data['jml_wanita'];
        }

        return array_values($perInstansi);
    }
}

