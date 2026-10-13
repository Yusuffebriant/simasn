<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exports\RekapAgamaExport;
use App\Exports\RekapAllExport;
use App\Exports\RekapDashboardExport;
use App\Exports\RekapPendidikanExport;
use App\Exports\RekapGolonganExport;
use App\Exports\RekapJabatanExport;
use App\Exports\RekapEselonGolonganGenderExport;
use App\Exports\RekapNakesExport;
use App\Exports\RekapParuhWaktuLengkapExport;
use App\Exports\RekapStrukturGolonganExport;
use App\Exports\RekapStrukturEselonExport;
use App\Exports\RekapJfTertentuExport;
use App\Exports\RekapJfPelaksanaExport;
use App\Exports\RekapPensiunExport;

use App\Services\RekapService;
use App\Services\RekapParuhWaktuTambahanService;
use App\Support\RekapCache;
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

    public function dashboardJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = RekapCache::remember('dashboard', $periode, function () use ($periode) {
                return (new RekapService())->rekapDashboard($periode);
            }
        );

        return response()->json($data);
    }

    public function exportDashboard(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = RekapCache::remember('dashboard', $periode, function () use ($periode) {
                return (new RekapService())->rekapDashboard($periode);
            }
        );

        return Excel::download(
            new RekapDashboardExport($data, $periode),
            "laporan-simasn-{$periode}.xlsx"
        );
    }

    public function agamaJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = RekapCache::remember('agama', $periode, function () use ($periode) {
                return (new RekapService())->rekapAgama($periode);
            }
        );

        return response()->json($data);
    }

    public function pendidikanJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = RekapCache::remember('pendidikan', $periode, function () use ($periode) {
                return (new RekapService())->rekapPendidikan($periode);
            }
        );

        return response()->json($data);
    }

    public function jabatanJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = RekapCache::remember('jabatan', $periode, function () use ($periode) {
                return (new RekapService())->rekapJabatan($periode);
            }
        );

        return response()->json($data);
    }

    public function golonganJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = RekapCache::remember('golongan', $periode, function () use ($periode) {
                return (new RekapService())->rekapGolongan($periode);
            }
        );

        return response()->json($data);
    }

    public function eselonGolonganGenderJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = RekapCache::remember('eselon-golongan-gender', $periode, function () use ($periode) {
                return (new RekapService())->rekapEselonGolonganGender($periode);
            }
        );

        return response()->json($data);
    }
    public function nakesJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = RekapCache::remember('nakes', $periode, fn() => (new RekapService())->rekapNakes($periode)
        );

        return response()->json($data);
    }

    public function paruhWaktuJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = RekapCache::remember('paruh-waktu', $periode, fn () => array_merge([
                'ringkasan_status' => (new RekapService())->ringkasanStatusKepegawaian(),
                'per_instansi' => (new RekapService())->rekapParuhWaktu($periode),
                'per_pendidikan' => (new RekapService())->rekapParuhWaktuPendidikan($periode),
                'per_jenis_jabatan' => (new RekapService())->rekapParuhWaktuJenisJabatan($periode),
            ],
                // per_usia, per_pensiun, per_jf_rumpun, per_jabatan,
                // per_persentase_instansi
                (new RekapParuhWaktuTambahanService())->semua($periode)
            )
        );

        return response()->json($data);
    }

    public function exportNakes(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        return Excel::download(new RekapNakesExport($periode), "rekap-nakes-{$periode}.xlsx");
    }

    public function exportParuhWaktu(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        return Excel::download(
            new RekapParuhWaktuLengkapExport($periode),
            "rekap-paruh-waktu-{$periode}.xlsx"
        );
    }
    public function sdJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));
        $data = RekapCache::remember('sd', $periode, fn() => (new RekapService())->rekapSdFungsional($periode)
        );
        return response()->json($data);
    }

    public function smpJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));
        $data = RekapCache::remember('smp', $periode, fn() => (new RekapService())->rekapSmpFungsional($periode)
        );
        return response()->json($data);
    }

    public function exportSd(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));
        return Excel::download(new \App\Exports\RekapSdExport($periode), "rekap-sd-{$periode}.xlsx");
    }

    public function exportSmp(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));
        return Excel::download(new \App\Exports\RekapSmpExport($periode), "rekap-smp-{$periode}.xlsx");
    }

    public function exportSdSmp(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));
        return Excel::download(new \App\Exports\RekapSdSmpExport($periode), "rekap-sd-smp-{$periode}.xlsx");
    }
    public function kecamatanJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));
        $data = RekapCache::remember('kecamatan', $periode, fn() => (new RekapService())->rekapKecamatanKelurahan($periode)
        );
        return response()->json($data);
    }

    public function exportKecamatan(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));
        return Excel::download(new \App\Exports\RekapKecamatanExport($periode), "rekap-kecamatan-{$periode}.xlsx");
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
    public function strukturGolonganJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = RekapCache::remember('struktur-golongan', $periode, fn() => (new RekapService())->rekapStrukturGolonganInstansi($periode)
        );

        return response()->json($data);
    }

    public function exportStrukturGolongan(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        return Excel::download(
            new RekapStrukturGolonganExport($periode),
            "rekap-struktur-golongan-{$periode}.xlsx"
        );
    }

    public function strukturEselonJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = RekapCache::remember('struktur-eselon', $periode, fn() => (new RekapService())->rekapStrukturEselonInstansi($periode)
        );

        return response()->json($data);
    }

    public function exportStrukturEselon(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        return Excel::download(
            new RekapStrukturEselonExport($periode),
            "rekap-struktur-eselon-{$periode}.xlsx"
        );
    }

    public function jfTertentuJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = RekapCache::remember('jf-tertentu', $periode, fn() => (new RekapService())->rekapJfTertentu($periode)
        );

        return response()->json($data);
    }

    public function exportJfTertentu(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        return Excel::download(
            new RekapJfTertentuExport($periode),
            "rekap-jf-tertentu-{$periode}.xlsx"
        );
    }

    public function jfPelaksanaJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = RekapCache::remember('jf-pelaksana', $periode, fn() => (new RekapService())->rekapJfPelaksana($periode)
        );

        return response()->json($data);
    }

    public function exportJfPelaksana(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        return Excel::download(
            new RekapJfPelaksanaExport($periode),
            "rekap-jf-pelaksana-{$periode}.xlsx"
        );
    }

    public function pensiunJson(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        $data = RekapCache::remember('pensiun', $periode, fn() => (new RekapService())->rekapPensiun($periode)
        );

        return response()->json($data);
    }

    public function exportPensiun(Request $request)
    {
        $periode = $request->query('periode', now()->format('Y-m'));

        return Excel::download(
            new RekapPensiunExport($periode),
            "rekap-pensiun-{$periode}.xlsx"
        );
    }
}