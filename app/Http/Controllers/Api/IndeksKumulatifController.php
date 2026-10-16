<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IndeksKumulatifService;
use App\Support\RekapCache;
use Illuminate\Http\Request;

/**
 * GET /api/indeks-kumulatif?tahun=YYYY
 *
 * Deret bulanan Januari s.d. bulan terakhir yang diupload pada tahun itu
 * (default: tahun periode aktif) untuk agama, pendidikan, golongan, JFT, JFU.
 */
class IndeksKumulatifController extends Controller
{
    public function index(Request $request, IndeksKumulatifService $service)
    {
        $request->validate([
            'tahun' => ['nullable', 'regex:/^\d{4}$/'],
        ]);

        $tahun = $request->query('tahun')
            ?: substr((string) (RekapCache::periodeAktif() ?: now()->format('Y-m')), 0, 4);

        return response()->json(
            $service->susun($tahun) + ['tahun_tersedia' => $service->tahunTersedia()]
        );
    }
}