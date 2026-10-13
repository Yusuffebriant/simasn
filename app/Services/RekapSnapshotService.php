<?php

namespace App\Services;

use App\Http\Controllers\Api\RekapController;
use App\Models\RekapSnapshotData;
use App\Support\RekapCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Membekukan hasil rekap satu periode ke tabel rekap_snapshot_data.
 *
 * Cara kerjanya memanggil endpoint JSON RekapController dalam "mode
 * membekukan" (lihat RekapCache::remember), jadi isi snapshot SAMA
 * PERSIS dengan yang tampil di layar dan tidak ada logika rekap yang
 * diduplikasi.
 *
 * PENTING: kalau menambah endpoint JSON rekap baru di RekapController
 * yang memakai RekapCache::remember(), tambahkan nama method-nya ke
 * DAFTAR_ENDPOINT di bawah supaya ikut dibekukan.
 */
class RekapSnapshotService
{
    private const DAFTAR_ENDPOINT = [
        'dashboardJson',
        'agamaJson',
        'pendidikanJson',
        'jabatanJson',
        'golonganJson',
        'eselonGolonganGenderJson',
        'nakesJson',
        'paruhWaktuJson',
        'sdJson',
        'smpJson',
        'kecamatanJson',
        'strukturGolonganJson',
        'strukturEselonJson',
        'jfTertentuJson',
        'jfPelaksanaJson',
        'pensiunJson',
    ];

    /**
     * Bekukan kondisi data SAAT INI sebagai rekap periode $periode
     * (menimpa snapshot lama periode yang sama). Mengembalikan jumlah
     * endpoint yang dibekukan.
     */
    public function bekukan(string $periode): int
    {
        $controller = app(RekapController::class);
        $request = Request::create('/api/rekap', 'GET', ['periode' => $periode]);

        // Satu transaksi: kalau satu endpoint gagal, tidak ada snapshot
        // setengah jadi (sebagian baru, sebagian dari import lama).
        DB::transaction(function () use ($controller, $request) {
            RekapCache::membekukan(function () use ($controller, $request) {
                foreach (self::DAFTAR_ENDPOINT as $method) {
                    $controller->{$method}($request);
                }
            });
        });

        return count(self::DAFTAR_ENDPOINT);
    }

    /** Daftar periode yang punya snapshot, terbaru dulu. */
    public function daftarPeriode(): array
    {
        return RekapSnapshotData::query()
            ->selectRaw('periode, MAX(dibekukan_pada) as dibekukan_pada')
            ->groupBy('periode')
            ->orderByDesc('periode')
            ->get()
            ->map(fn ($r) => [
                'periode' => $r->periode,
                'dibekukan_pada' => $r->dibekukan_pada,
            ])
            ->all();
    }
}