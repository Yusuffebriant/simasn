<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatistikService;
use Illuminate\Http\Request;

class StatistikPensiunanDinasController extends Controller
{
    public function __construct(
        private StatistikService $statistikService
    ) {}

    public function index(Request $request)
    {
        $tahun = $request->query('tahun');

        return response()->json(
            $this->statistikService->statistikPensiunanDinas(
                $tahun !== null ? (int) $tahun : null
            )
        );
    }
}