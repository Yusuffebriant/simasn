<?php

namespace App\Http\Controllers\Api;

use App\Exports\PensiunanPnsExport;
use App\Http\Controllers\Controller;
use App\Services\StatistikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class StatistikPensiunanPNSController extends Controller
{
    public function __construct(protected StatistikService $statistikService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            // Batas atas sengaja dilebihkan 1 tahun dari tahun berjalan,
            // supaya proyeksi pensiun tahun depan tetap bisa diminta.
            'tahun' => ['nullable', 'integer', 'digits:4', 'min:2000', 'max:' . (now()->year + 1)],
        ]);

        $tahun = $request->filled('tahun')
            ? (int) $request->query('tahun')
            : (int) now()->year;

        $data = $this->statistikService->statistikPensiunanPNS($tahun);

        return response()->json([
            'data' => $data,
        ]);
    }

    public function export(Request $request)
    {
        // Validasi $tahun disamakan persis dengan index() supaya export
        // tidak bisa diminta untuk rentang tahun di luar yang didukung.
        $request->validate([
            'tahun' => ['nullable', 'integer', 'digits:4', 'min:2000', 'max:' . (now()->year + 1)],
        ]);

        $tahun = $request->filled('tahun')
            ? (int) $request->query('tahun')
            : (int) now()->year;

        $namaFile = "pensiunan-pns-{$tahun}.xlsx";

        return Excel::download(new PensiunanPnsExport($tahun), $namaFile);
    }
}