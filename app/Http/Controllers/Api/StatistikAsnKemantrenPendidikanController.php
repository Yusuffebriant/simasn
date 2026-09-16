<?php

namespace App\Http\Controllers\Api;

use App\Exports\AsnKemantrenPendidikanExport;
use App\Http\Controllers\Controller;
use App\Services\StatistikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class StatistikAsnKemantrenPendidikanController extends Controller
{
    public function __construct(
        private StatistikService $statistikService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->statistikService
                ->statistikAsnKemantrenPendidikan(),
        ]);
    }

    public function export(Request $request)
    {
        $periode = $request->query('periode');
        $namaFile = 'asn-kemantren-pendidikan' . ($periode ? "-{$periode}" : '') . '.xlsx';

        return Excel::download(new AsnKemantrenPendidikanExport($periode), $namaFile);
    }
}
