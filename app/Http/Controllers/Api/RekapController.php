<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\RekapAgamaExport;
use App\Exports\RekapAllExport;
use App\Exports\RekapPendidikanExport;
use App\Exports\RekapGolonganExport;
use App\Exports\RekapJabatanExport;
use App\Exports\RekapEselonGolonganGenderExport;

use App\Services\RekapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

class RekapController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | JSON REKAP
    |--------------------------------------------------------------------------
    */

    public function agamaJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = Cache::remember(
            "rekap.agama.{$periode}",
            now()->addMinutes(10),
            function () use ($periode) {
                return (new RekapService())->rekapAgama($periode);
            }
        );

        return response()->json($data);
    }

    public function pendidikanJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = Cache::remember(
            "rekap.pendidikan.{$periode}",
            now()->addMinutes(10),
            function () use ($periode) {
                return (new RekapService())->rekapPendidikan($periode);
            }
        );

        return response()->json($data);
    }

    public function jabatanJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = Cache::remember(
            "rekap.jabatan.{$periode}",
            now()->addMinutes(10),
            function () use ($periode) {
                return (new RekapService())->rekapJabatan($periode);
            }
        );

        return response()->json($data);
    }


    /*
    |--------------------------------------------------------------------------
    | EXPORT EXCEL
    |--------------------------------------------------------------------------
    */

    public function exportAll(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        return Excel::download(
            new RekapAllExport($periode),
            "rekap-all-{$periode}.xlsx"
        );
    }

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
