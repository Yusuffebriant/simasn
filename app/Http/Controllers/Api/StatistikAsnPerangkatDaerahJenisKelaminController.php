<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatistikService;

class StatistikAsnPerangkatDaerahJenisKelaminController extends Controller
{
    public function __construct(
        private StatistikService $statistikService
    ) {
    }

    public function index()
    {
        return response()->json(
            $this->statistikService
                ->statistikAsnPerangkatDaerahJenisKelamin()
        );
    }
}