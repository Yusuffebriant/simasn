<?php

namespace App\Http\Controllers\Api;

use App\Exports\PenjabatPerangkatDaerahExport;
use App\Http\Controllers\Controller;
use App\Services\StatistikService;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;

class StatistikPenjabatPerangkatDaerahController extends Controller
{
    public function __construct(
        private StatistikService $statistikService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->statistikService
                ->statistikPenjabatPerangkatDaerahJenisKelamin(),
        ]);
    }

    public function export()
    {
        return Excel::download(
            new PenjabatPerangkatDaerahExport(),
            'penjabat-perangkat-daerah.xlsx'
        );
    }
}
