<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatistikService;
use Illuminate\Http\JsonResponse;

class StatistikStafDinasGolonganController extends Controller
{
    public function __construct(
        protected StatistikService $statistikService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            $this->statistikService->statistikStafDinasGolongan()
        );
    }
}
