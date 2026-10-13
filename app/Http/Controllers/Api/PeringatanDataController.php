<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PeringatanDataService;

class PeringatanDataController extends Controller
{
    /** GET /api/peringatan-data — kesehatan data pegawai saat ini. */
    public function index(PeringatanDataService $service)
    {
        return response()->json($service->periksa());
    }
}