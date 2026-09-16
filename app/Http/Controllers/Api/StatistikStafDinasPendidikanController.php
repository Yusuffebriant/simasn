<?php

namespace App\Http\Controllers\Api;

use App\Exports\StafDinasSkpdExport;
use App\Http\Controllers\Controller;
use App\Services\StatistikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class StatistikStafDinasPendidikanController extends Controller
{
    public function __construct(
        protected StatistikService $statistikService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            $this->statistikService->statistikStafDinasPendidikan()
        );
    }

    /**
     * Export SELURUH isi panel "Pegawai Berdasarkan Pendidikan & SKPD"
     * (SkpdDinasPanel.jsx) dalam satu file: pendidikan, golongan, eselon
     * pejabat struktural, dan ringkasan fungsional/pensiunan. Panel itu
     * memang menggabungkan beberapa endpoint jadi satu halaman, jadi
     * tombol Export-nya juga cuma satu.
     */
    public function export(Request $request)
    {
        $periode = $request->query('periode');
        $namaFile = 'pegawai-pendidikan-skpd' . ($periode ? "-{$periode}" : '') . '.xlsx';

        return Excel::download(new StafDinasSkpdExport($periode), $namaFile);
    }
}
