<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatistikService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
}