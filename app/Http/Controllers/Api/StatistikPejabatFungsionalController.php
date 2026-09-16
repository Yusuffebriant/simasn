<?php

namespace App\Http\Controllers\Api;

use App\Exports\PejabatFungsionalExport;
use App\Http\Controllers\Controller;
use App\Services\StatistikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class StatistikPejabatFungsionalController extends Controller
{
    public function __construct(protected StatistikService $statistikService) {}

    public function index(Request $request): JsonResponse
    {
        $periode = $request->query('periode');

        $data = $this->statistikService->statistikPejabatFungsional($periode);

        return response()->json([
            'data' => $data,
        ]);
    }

    public function export(Request $request)
    {
        $periode = $request->query('periode');
        $namaFile = 'pejabat-fungsional' . ($periode ? "-{$periode}" : '') . '.xlsx';

        return Excel::download(new PejabatFungsionalExport($periode), $namaFile);
    }
}