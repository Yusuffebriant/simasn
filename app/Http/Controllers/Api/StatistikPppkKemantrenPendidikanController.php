<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatistikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatistikPppkKemantrenPendidikanController extends Controller
{
    public function __construct(private StatistikService $statistikService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $periode = $request->query('periode');

        return response()->json(
            $this->statistikService->statistikPppkKemantrenPendidikan($periode)
        );
    }
}