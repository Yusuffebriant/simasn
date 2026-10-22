<?php

namespace App\Http\Controllers\Api;

use App\Exports\PerubahanBulananExport;
use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\ImportPerubahan;
use App\Services\PerubahanPegawaiService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Halaman "Perubahan Pegawai" (per bulan) di menu Admin.
 *
 * Datanya sama dengan yang dicatat PerubahanPegawaiService saat import
 * (tabel import_perubahan), tapi dikelompokkan per periode (YYYY-MM)
 * supaya admin bisa membuka bulan mana pun tanpa lewat layar hasil import.
 *
 * Kalau satu periode diimport lebih dari sekali, catatan dari semua
 * import periode itu digabung (tiap import hanya mencatat selisihnya
 * dengan kondisi sebelum import tersebut, jadi tidak dobel).
 */
class PerubahanBulananController extends Controller
{
    /**
     * GET /api/perubahan-pegawai
     * Daftar periode yang punya import selesai (terbaru di atas),
     * lengkap dengan jumlah perubahan tercatat.
     */
    public function index()
    {
        $batches = ImportBatch::where('status', 'selesai')
            ->get(['id', 'periode', 'perubahan_status']);

        $jumlah = ImportPerubahan::select('periode', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('periode')
            ->pluck('jumlah', 'periode');

        $data = $batches->groupBy('periode')
            ->map(fn (Collection $grup, $periode) => [
                'periode' => (string) $periode,
                'jumlah_import' => $grup->count(),
                'jumlah_perubahan' => (int) ($jumlah[$periode] ?? 0),
                'status' => $this->statusPeriode($grup),
            ])
            ->sortKeysDesc()
            ->values();

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/perubahan-pegawai/{periode}?jenis=&q=&page=
     * Ringkasan per jenis + daftar perubahan untuk satu bulan.
     */
    public function show(Request $request, string $periode)
    {
        $this->pastikanPeriode($periode);

        $label = PerubahanPegawaiService::LABEL;

        $perJenis = ImportPerubahan::where('periode', $periode)
            ->select('jenis', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('jenis')
            ->pluck('jumlah', 'jenis');

        $ringkasan = [];
        foreach ($label as $kunci => $nama) {
            $ringkasan[] = [
                'jenis' => $kunci,
                'label' => $nama,
                'jumlah' => (int) ($perJenis[$kunci] ?? 0),
            ];
        }

        $batches = ImportBatch::where('periode', $periode)
            ->where('status', 'selesai')
            ->orderBy('id')
            ->get(['id', 'nama_file', 'perubahan_status', 'created_at']);

        // Bulan tercatat sebelumnya, untuk pembanding jumlah perubahan.
        $sebelumnya = DB::table('import_perubahan')
            ->where('periode', '<', $periode)
            ->select('periode', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('periode')
            ->orderByDesc('periode')
            ->first();

        // Bulan data yang menjadi pembanding: import selesai terakhir
        // dengan periode sebelum bulan ini.
        $periodePembanding = ImportBatch::where('status', 'selesai')
            ->where('periode', '<', $periode)
            ->max('periode');

        $query = ImportPerubahan::where('periode', $periode);

        if ($jenis = $request->query('jenis')) {
            $query->where('jenis', $jenis);
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($w) use ($q) {
                $w->where('nama', 'like', "%{$q}%")->orWhere('nip', 'like', "%{$q}%");
            });
        }

        $daftar = $query->orderBy('jenis')->orderBy('nama')
            ->paginate(50, ['jenis', 'nip', 'nama', 'unit', 'status_kepegawaian', 'dari', 'ke', 'keterangan']);

        $daftar->getCollection()->transform(function ($r) use ($label) {
            $r->jenis_label = $label[$r->jenis] ?? $r->jenis;

            return $r;
        });

        return response()->json([
            // selesai | tanpa_pembanding | gagal | null (belum ada import / import lama)
            'status' => $this->statusPeriode($batches),
            'periode' => $periode,
            // Perubahan dihitung dari data bulan `pembanding` ke bulan `periode`.
            'pembanding' => $periodePembanding ? ['periode' => $periodePembanding] : null,
            'jumlah_import' => $batches->count(),
            'total' => (int) $perJenis->sum(),
            'ringkasan' => $ringkasan,
            'sebelumnya' => $sebelumnya
                ? ['periode' => $sebelumnya->periode, 'total' => (int) $sebelumnya->jumlah]
                : null,
            'daftar' => $daftar,
        ]);
    }

    /**
     * GET /api/perubahan-pegawai/{periode}/export
     * Unduh seluruh perubahan satu bulan (Excel).
     */
    public function export(string $periode)
    {
        $this->pastikanPeriode($periode);

        return Excel::download(
            new PerubahanBulananExport($periode),
            "perubahan-pegawai-{$periode}.xlsx"
        );
    }

    // ------------------------------------------------------------------

    private function pastikanPeriode(string $periode): void
    {
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode), 404, 'Periode tidak valid.');
    }

    /**
     * Status catatan satu periode dari semua import-nya:
     * selesai > tanpa_pembanding > gagal > null.
     */
    private function statusPeriode(Collection $batches): ?string
    {
        $status = $batches->pluck('perubahan_status');

        return match (true) {
            $status->contains('selesai') => 'selesai',
            $status->contains('tanpa_pembanding') => 'tanpa_pembanding',
            $status->contains('gagal') => 'gagal',
            default => null,
        };
    }
}