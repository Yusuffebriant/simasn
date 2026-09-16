<?php

namespace App\Http\Controllers\Api;

use App\Exports\PppkGolonganExport;
use App\Http\Controllers\Controller;
use App\Services\StatistikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class StatistikPppkGolonganController extends Controller
{
    public function __construct(protected StatistikService $statistikService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $periode = $request->query('periode');

        $data = $this->statistikService->statistikPppkGolongan($periode);

        return response()->json([
            'data' => $data,
        ]);
    }

    public function export(Request $request)
    {
        $periode = $request->query('periode');
        $namaFile = 'pppk-golongan' . ($periode ? "-{$periode}" : '') . '.xlsx';

        return Excel::download(new PppkGolonganExport($periode), $namaFile);
    }
}