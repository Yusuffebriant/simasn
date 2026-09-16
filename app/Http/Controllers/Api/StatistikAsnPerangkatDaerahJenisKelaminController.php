<?php

namespace App\Http\Controllers\Api;

use App\Exports\AsnPerangkatDaerahJenisKelaminExport;
use App\Http\Controllers\Controller;
use App\Services\StatistikService;
use Maatwebsite\Excel\Facades\Excel;

class StatistikAsnPerangkatDaerahJenisKelaminController extends Controller
{
    public function __construct(
        private StatistikService $statistikService
    ) {}

    public function index()
    {
        return response()->json(
            $this->statistikService
                ->statistikAsnPerangkatDaerahJenisKelamin()
        );
    }

    public function export()
    {
        return Excel::download(
            new AsnPerangkatDaerahJenisKelaminExport(),
            'asn-perangkat-daerah-jenis-kelamin.xlsx'
        );
    }
}
