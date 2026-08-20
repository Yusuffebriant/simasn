<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\RekapAgamaExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class RekapController extends Controller
{
    public function exportAgama(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        return Excel::download(
            new RekapAgamaExport($periode),
            "rekap-agama-{$periode}.xlsx"
        );
    }
}