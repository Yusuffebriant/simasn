<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RekapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatistikPejabatFungsionalController extends Controller
{
    public function __construct(protected RekapService $rekapService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $periode = $request->query('periode');

        $data = $this->rekapService->statistikPejabatFungsional($periode);

        return response()->json([
            'data' => $data,
        ]);
    }
}