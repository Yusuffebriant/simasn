<?php

namespace App\Http\Controllers\Api;

use App\Exports\PnsKemantrenGolonganExport;
use App\Http\Controllers\Controller;
use App\Services\StatistikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class StatistikPnsKemantrenGolonganController extends Controller
{
    public function __construct(private StatistikService $statistikService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $periode = $request->query('periode');

        return response()->json(
            $this->statistikService->statistikPnsKemantrenGolongan($periode)
        );
    }

    public function export(Request $request)
    {
        $periode = $request->query('periode');
        $namaFile = 'pns-kemantren-golongan' . ($periode ? "-{$periode}" : '') . '.xlsx';

        return Excel::download(new PnsKemantrenGolonganExport($periode), $namaFile);
    }
}