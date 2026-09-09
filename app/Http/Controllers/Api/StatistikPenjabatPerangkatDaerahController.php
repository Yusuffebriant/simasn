<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatistikService;
use Illuminate\Http\JsonResponse;

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
}