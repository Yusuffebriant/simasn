<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatistikService;  
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}