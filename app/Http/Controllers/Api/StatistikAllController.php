<?php

namespace App\Http\Controllers\Api;

use App\Exports\StatistikAllExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Endpoint "Export Semua Statistik": satu file Excel berisi seluruh
 * tabel yang ada di halaman Statistik (satu sheet per tabel) — versi
 * halaman Statistik dari RekapController::exportAll() di halaman Admin.
 */
class StatistikAllController extends Controller
{
    public function export(Request $request)
    {
        $periode = $request->query('periode');
        $namaFile = 'statistik-semua' . ($periode ? "-{$periode}" : '') . '.xlsx';

        return Excel::download(new StatistikAllExport($periode), $namaFile);
    }
}
