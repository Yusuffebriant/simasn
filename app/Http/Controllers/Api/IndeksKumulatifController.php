<?php

namespace App\Http\Controllers\Api;

use App\Exports\IndeksKumulatifExport;
use App\Exports\PerbandinganPeriodeExport;
use App\Http\Controllers\Controller;
use App\Services\IndeksKumulatifService;
use App\Services\MutasiInstansiService;
use App\Services\PerbandinganPeriodeService;
use App\Support\PerubahanAtribut;
use App\Support\RekapCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * REKAP AGREGAT (jumlah per bulan) - sudah ada:
 *   GET /api/indeks-kumulatif?tahun=YYYY            (atau ?awal=YYYY-MM&akhir=YYYY-MM)
 *   GET /api/indeks-kumulatif/export?tahun=YYYY     (atau ?awal=&akhir=)
 *
 * PERUBAHAN INDIVIDUAL (snapshot per pegawai) - baru:
 *   GET /api/indeks-kumulatif/periode               pilihan periode + status snapshot
 *   GET /api/indeks-kumulatif/perubahan/ringkasan   ?awal&akhir[&filter]
 *   GET /api/indeks-kumulatif/perubahan             ?awal&akhir&mode=kondisi|kejadian&page&per_page[&filter]
 *   GET /api/indeks-kumulatif/perubahan/deret       ?awal&akhir[&filter]  (berurutan + kumulatif dari awal)
 *   GET /api/indeks-kumulatif/perubahan/pegawai     ?kunci&awal&akhir     (detail riwayat satu pegawai)
 *   GET /api/indeks-kumulatif/perubahan/export      ?awal&akhir&mode[&filter]
 *
 * PERPINDAHAN PER INSTANSI (jumlah per instansi + penjelasan pindah/baru/berhenti):
 *   GET /api/indeks-kumulatif/instansi              ?awal&akhir
 *
 * Semua rute berada di grup role super-admin|admin-instansi (routes/api.php).
 */
class IndeksKumulatifController extends Controller
{
    private const POLA_PERIODE = '/^\d{4}-(0[1-9]|1[0-2])$/';

    public function index(Request $request, IndeksKumulatifService $service)
    {
        $request->validate([
            'tahun' => ['nullable', 'regex:/^\d{4}$/'],
            'awal' => ['nullable', 'regex:' . self::POLA_PERIODE, 'required_with:akhir'],
            'akhir' => ['nullable', 'regex:' . self::POLA_PERIODE, 'required_with:awal'],
            'paruh_waktu' => ['nullable', 'boolean'],
        ]);

        if ($request->filled('awal')) {
            [$awal, $akhir] = $this->rentang($request);

            return response()->json(
                $service->susunRentang($awal, $akhir, $request->boolean('paruh_waktu')) + ['tahun_tersedia' => $service->tahunTersedia()]
            );
        }

        $tahun = $this->tahun($request);

        return response()->json(
            $service->susun($tahun, $request->boolean('paruh_waktu')) + ['tahun_tersedia' => $service->tahunTersedia()]
        );
    }

    public function export(Request $request, IndeksKumulatifService $service)
    {
        $request->validate([
            'tahun' => ['nullable', 'regex:/^\d{4}$/'],
            'awal' => ['nullable', 'regex:' . self::POLA_PERIODE, 'required_with:akhir'],
            'akhir' => ['nullable', 'regex:' . self::POLA_PERIODE, 'required_with:awal'],
            'paruh_waktu' => ['nullable', 'boolean'],
        ]);

        $awal = $akhir = null;
        if ($request->filled('awal')) {
            [$awal, $akhir] = $this->rentang($request);
            $tahun = substr($awal, 0, 4);
            $data = $service->susunRentang($awal, $akhir, $request->boolean('paruh_waktu'));
        } else {
            $tahun = $this->tahun($request);
            $data = $service->susun($tahun, $request->boolean('paruh_waktu'));
        }

        // Tanpa data tidak ada yang bisa diexport (sheet kosong membingungkan).
        // Pada rentang pilihan, "ada data" berarti minimal satu bulan terisi.
        $adaIsi = collect($data['bulan'])->contains(fn ($b) => $b['sumber'] !== 'kosong' && $b['sumber'] !== 'tanpa_snapshot');
        if ($data['bulan'] === [] || ($awal !== null && ! $adaIsi)) {
            $pesan = $awal !== null
                ? "Belum ada data rekap pada rentang {$awal} s.d. {$akhir}."
                : "Belum ada data yang diupload pada tahun {$tahun}.";

            return response()->json(['message' => $pesan], 404);
        }

        $nama = $awal !== null ? "indeks-kumulatif-{$awal}-sd-{$akhir}.xlsx" : "indeks-kumulatif-{$tahun}.xlsx";

        return Excel::download(new IndeksKumulatifExport($tahun, $awal, $akhir, $request->boolean('paruh_waktu')), $nama);
    }

    // ------------------------------------------------------------------
    // Perubahan individual
    // ------------------------------------------------------------------

    public function periode(PerbandinganPeriodeService $service)
    {
        return response()->json(['periode' => $service->periodeOpsi()]);
    }

    /** Jumlah ASN aktif per instansi tiap bulan + penjelasan perpindahannya. */
    public function instansi(Request $request, MutasiInstansiService $service)
    {
        $request->validate(['paruh_waktu' => ['nullable', 'boolean']]);
        [$awal, $akhir] = $this->rentang($request);

        return response()->json($service->susun($awal, $akhir, $request->boolean('paruh_waktu')));
    }

    public function ringkasan(Request $request, PerbandinganPeriodeService $service)
    {
        [$awal, $akhir] = $this->rentang($request);

        return response()->json($service->ringkasan($awal, $akhir, $this->filter($request)));
    }

    public function perubahan(Request $request, PerbandinganPeriodeService $service)
    {
        [$awal, $akhir] = $this->rentang($request);
        $filter = $this->filter($request);

        $request->validate([
            'mode' => ['nullable', Rule::in(['kondisi', 'kejadian'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:10,200'],
        ]);

        $mode = $request->query('mode', 'kondisi');
        $halaman = (int) $request->query('page', 1);
        $perHalaman = (int) $request->query('per_page', 50);

        if (! $this->bisaDibandingkan($service, $awal, $akhir)) {
            return response()->json([
                'dapat_dibandingkan' => false,
                'pesan' => 'Perbandingan tidak tersedia: snapshot individual periode awal dan/atau akhir belum ada.',
                'data' => [], 'total' => 0, 'per_page' => $perHalaman, 'current_page' => 1, 'last_page' => 1,
            ]);
        }

        $hasil = $mode === 'kejadian'
            ? $service->daftarKejadian($awal, $akhir, $filter, $halaman, $perHalaman)
            : $service->daftarKondisi($awal, $akhir, $filter, $halaman, $perHalaman);

        return response()->json(['dapat_dibandingkan' => true, 'mode' => $mode] + $hasil);
    }

    public function deret(Request $request, PerbandinganPeriodeService $service)
    {
        [$awal, $akhir] = $this->rentang($request);

        return response()->json($service->deret($awal, $akhir, $this->filter($request)));
    }

    public function riwayatPegawai(Request $request, PerbandinganPeriodeService $service)
    {
        $request->validate(['kunci' => ['required', 'string', 'max:40']]);
        [$awal, $akhir] = $this->rentang($request);

        $hasil = $service->riwayatPegawai((string) $request->query('kunci'), $awal, $akhir);

        if (! $hasil['ditemukan']) {
            return response()->json(['message' => 'Pegawai tidak ditemukan pada snapshot di rentang ini.'], 404);
        }

        return response()->json($hasil);
    }

    public function exportPerubahan(Request $request, PerbandinganPeriodeService $service)
    {
        [$awal, $akhir] = $this->rentang($request);
        $filter = $this->filter($request);

        $request->validate(['mode' => ['nullable', Rule::in(['kondisi', 'kejadian'])]]);
        $mode = $request->query('mode', 'kondisi');

        if (! $this->bisaDibandingkan($service, $awal, $akhir)) {
            return response()->json(
                ['message' => 'Perbandingan tidak tersedia: snapshot individual periode awal dan/atau akhir belum ada.'],
                404
            );
        }

        return Excel::download(
            new PerbandinganPeriodeExport($awal, $akhir, $mode, $filter),
            "perubahan-individual-{$awal}-sd-{$akhir}.xlsx"
        );
    }

    // ------------------------------------------------------------------

    private function tahun(Request $request): string
    {
        return $request->query('tahun')
            ?: substr((string) (RekapCache::periodeAktif() ?: now()->format('Y-m')), 0, 4);
    }

    /** @return array{0:string,1:string} [awal, akhir] sudah divalidasi */
    private function rentang(Request $request): array
    {
        $request->validate([
            'awal' => ['required', 'regex:' . self::POLA_PERIODE],
            'akhir' => ['required', 'regex:' . self::POLA_PERIODE],
        ]);

        $awal = (string) $request->query('awal');
        $akhir = (string) $request->query('akhir');

        if ($akhir < $awal) {
            throw ValidationException::withMessages(['akhir' => 'Periode akhir tidak boleh sebelum periode awal.']);
        }

        if (PerubahanAtribut::selisihBulan($awal, $akhir) + 1 > PerbandinganPeriodeService::MAKS_BULAN) {
            throw ValidationException::withMessages(['akhir' => 'Rentang periode maksimal ' . PerbandinganPeriodeService::MAKS_BULAN . ' bulan.']);
        }

        return [$awal, $akhir];
    }

    private function filter(Request $request): array
    {
        $request->validate([
            'kategori' => ['nullable', Rule::in(array_keys(PerubahanAtribut::KATEGORI))],
            'q' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:200'],
            'verifikasi' => ['nullable', 'boolean'],
            'atribut' => ['nullable', Rule::in(array_keys(PerubahanAtribut::ATRIBUT))],
            'dari' => ['nullable', 'string', 'max:60'],
            'ke' => ['nullable', 'string', 'max:60'],
        ]);

        return [
            'kategori' => $request->query('kategori') ?: null,
            'q' => $request->query('q') ?: null,
            'unit' => $request->query('unit') ?: null,
            'verifikasi' => $request->boolean('verifikasi'),
            'atribut' => $request->query('atribut') ?: null,
            'dari' => $request->query('dari') ?: null,
            'ke' => $request->query('ke') ?: null,
        ];
    }

    private function bisaDibandingkan(PerbandinganPeriodeService $service, string $awal, string $akhir): bool
    {
        return $service->dapatDibandingkan($awal, $akhir);
    }
}