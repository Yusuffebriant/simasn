<?php

namespace App\Services;

use App\Imports\PenghitungBarisValid;
use App\Models\Pegawai;
use App\Support\RekapCache;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Pengaman sebelum import (mode GANTI TOTAL) dijalankan:
 *
 *  - periode lebih lama dari data yang sedang berlaku ditolak (dicek di
 *    ImportController::store lewat RekapCache::periodeAktif()).
 *  - pratinjau(): bandingkan jumlah baris valid di file dengan jumlah pegawai
 *    sekarang. Kalau turun melebihi ambang (config import.ambang_penurunan_persen),
 *    upload ditahan sampai admin mengonfirmasi.
 */
class ImportPengaman
{
    /**
     * @param string $pathFile path absolut file Excel (harus berekstensi .xlsx/.xls)
     */
    public function pratinjau(string $pathFile, string $periode): array
    {
        $sekarang = Pegawai::count();
        $ambang = (int) config('import.ambang_penurunan_persen', 20);

        $hasil = [
            'periode' => $periode,
            'periode_aktif' => RekapCache::periodeAktif(),
            'jumlah_sekarang' => $sekarang,
            'jumlah_file' => null,
            'penurunan' => 0,
            'persen_penurunan' => 0.0,
            'ambang_persen' => $ambang,
            'melewati_ambang' => false,
        ];

        // Belum ada data pegawai: tidak ada yang bisa hilang, file tidak perlu dibaca.
        if ($sekarang === 0) {
            return $hasil;
        }

        $penghitung = new PenghitungBarisValid();
        Excel::import($penghitung, $pathFile);

        $penurunan = max(0, $sekarang - $penghitung->valid);
        $persen = round($penurunan / $sekarang * 100, 1);

        return array_merge($hasil, [
            'jumlah_file' => $penghitung->valid,
            'penurunan' => $penurunan,
            'persen_penurunan' => $persen,
            'melewati_ambang' => $persen > $ambang,
        ]);
    }
}