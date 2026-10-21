<?php

namespace App\Http\Controllers\Api;

use App\Exports\IndeksKumulatifExport;
use App\Http\Controllers\Controller;
use App\Services\IndeksKumulatifService;
use App\Support\RekapCache;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * GET /api/indeks-kumulatif?tahun=YYYY
 * GET /api/indeks-kumulatif/export?tahun=YYYY  (file Excel)
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

    public function export(Request $request, IndeksKumulatifService $service)
    {
        $request->validate([
            'tahun' => ['nullable', 'regex:/^\d{4}$/'],
        ]);

        $tahun = $request->query('tahun')
            ?: substr((string) (RekapCache::periodeAktif() ?: now()->format('Y-m')), 0, 4);

        // Tanpa data tidak ada yang bisa diexport (sheet kosong membingungkan).
        if ($service->susun($tahun)['bulan'] === []) {
            return response()->json(
                ['message' => "Belum ada data yang diupload pada tahun {$tahun}."],
                404
            );
        }

        return Excel::download(
            new IndeksKumulatifExport($tahun),
            "indeks-kumulatif-{$tahun}.xlsx"
        );
    }
}