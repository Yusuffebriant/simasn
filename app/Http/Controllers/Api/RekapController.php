<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\RekapAgamaExport;
use App\Exports\RekapPendidikanExport;
use App\Exports\RekapGolonganExport;
use App\Exports\RekapJabatanExport;
use App\Exports\RekapEselonGolonganGenderExport;
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

    public function exportPendidikan(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        return Excel::download(
            new RekapPendidikanExport($periode),
            "rekap-pendidikan-{$periode}.xlsx"
        );
    }

    public function exportGolongan(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        return Excel::download(
            new RekapGolonganExport($periode),
            "rekap-golongan-{$periode}.xlsx"
        );
    }

    public function exportJabatan(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        return Excel::download(
            new RekapJabatanExport($periode),
            "rekap-jabatan-{$periode}.xlsx"
        );
    }

    public function exportEselonGolonganGender(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        return Excel::download(
            new RekapEselonGolonganGenderExport($periode),
            "rekap-eselon-golongan-gender-{$periode}.xlsx"
        );
    }
}