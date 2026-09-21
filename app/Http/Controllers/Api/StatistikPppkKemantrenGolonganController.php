<?php

namespace App\Http\Controllers\Api;

use App\Exports\PppkKemantrenGolonganExport;
use App\Http\Controllers\Controller;
use App\Services\StatistikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class StatistikPppkKemantrenGolonganController extends Controller
{
    public function __construct(private StatistikService $statistikService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $periode = $request->query('periode');

        return response()->json(
            $this->statistikService->statistikPppkKemantrenGolongan($periode)
        );
    }

    public function export(Request $request)
    {
        $periode = $request->query('periode');
        $namaFile = 'pppk-kemantren-golongan' . ($periode ? "-{$periode}" : '') . '.xlsx';

        return Excel::download(new PppkKemantrenGolonganExport($periode), $namaFile);
    }
}