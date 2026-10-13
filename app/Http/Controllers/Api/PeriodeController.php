<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RekapSnapshotService;
use App\Support\RekapCache;

class PeriodeController extends Controller
{
    /** Periode data yang sedang berlaku (import terakhir yang selesai). */
    public function aktif()
    {
        return response()->json(['periode' => RekapCache::periodeAktif()]);
    }

    /**
     * Periode yang bisa dibuka di rekapitulasi: periode aktif + periode
     * lama yang rekapnya sudah dibekukan.
     */
    public function riwayat(RekapSnapshotService $snapshot)
    {
        return response()->json([
            'aktif' => RekapCache::periodeAktif(),
            'periode' => $snapshot->daftarPeriode(),
        ]);
    }
}