<?php

namespace App\Http\Controllers\Api;

use App\Exports\AsnPendidikanExport;
use App\Http\Controllers\Controller;
use App\Services\StatistikService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;

class StatistikAsnPendidikanController extends Controller
{
    public function __construct(
        protected StatistikService $statistikService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $periode = $request->query('periode');

        $data = $this->statistikService->statistikAsnPendidikan($periode);

        return response()->json([
            'data' => $data,
        ]);
    }

    public function export(Request $request)
    {
        $periode = $request->query('periode');
        $namaFile = 'asn-pendidikan' . ($periode ? "-{$periode}" : '') . '.xlsx';

        return Excel::download(new AsnPendidikanExport($periode), $namaFile);
    }
}