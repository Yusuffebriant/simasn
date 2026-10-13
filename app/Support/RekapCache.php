<?php

namespace App\Support;

use App\Models\ImportBatch;
use App\Models\RekapSnapshotData;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Cache rekap dengan "versi" global + pembacaan snapshot per periode.
 *
 * Cache: setiap key rekap memuat versi saat ini. Selesai import, bump()
 * cukup mengganti versi sehingga SEMUA cache rekap lama otomatis tidak
 * terpakai, tanpa perlu daftar key manual (yang dulu sempat melewatkan
 * rekap.paruh-waktu dan rekap.pensiun).
 *
 * Snapshot: tabel pegawai hanya menyimpan kondisi TERKINI. Supaya rekap
 * periode lama tidak hilang saat ada import periode baru, hasil rekap
 * dibekukan ke tabel rekap_snapshot_data setiap import selesai
 * (lihat RekapSnapshotService). remember() lalu menyajikan snapshot untuk
 * periode yang BUKAN periode aktif, dan menghitung langsung (dengan cache
 * 10 menit) untuk periode aktif.
 */
class RekapCache
{
    private const VERSION_KEY = 'rekap.version';

    /** true selama RekapSnapshotService membekukan hasil rekap. */
    private static bool $membekukan = false;

    public static function version(): string
    {
        return (string) Cache::get(self::VERSION_KEY, '0');
    }

    public static function key(string $name, string $periode): string
    {
        return 'rekap.v' . self::version() . ".{$name}.{$periode}";
    }

    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, (string) Str::uuid());
    }

    /**
     * Periode aktif = periode dari import terakhir yang selesai. Data di
     * tabel pegawai saat ini adalah data periode ini.
     */
    public static function periodeAktif(): ?string
    {
        return ImportBatch::query()
            ->where('status', 'selesai')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('periode');
    }

    /**
     * Ambil hasil rekap $name untuk $periode.
     *
     * - Mode membekukan: hitung ulang (tanpa cache) lalu simpan sebagai
     *   snapshot periode tsb.
     * - Periode BUKAN periode aktif dan snapshot-nya ada: sajikan snapshot.
     * - Selain itu: hitung langsung, di-cache 10 menit.
     */
    public static function remember(string $name, string $periode, Closure $hitung): mixed
    {
        if (self::$membekukan) {
            $data = $hitung();

            RekapSnapshotData::updateOrCreate(
                ['periode' => $periode, 'nama' => $name],
                ['payload' => $data, 'dibekukan_pada' => now()]
            );

            return $data;
        }

        if ($periode !== self::periodeAktif()) {
            $beku = RekapSnapshotData::where('periode', $periode)
                ->where('nama', $name)
                ->first();

            if ($beku !== null) {
                return $beku->payload;
            }
        }

        return Cache::remember(
            self::key($name, $periode),
            now()->addMinutes(10),
            $hitung
        );
    }

    /** Jalankan $aksi dalam mode membekukan. */
    public static function membekukan(Closure $aksi): void
    {
        self::$membekukan = true;

        try {
            $aksi();
        } finally {
            self::$membekukan = false;
        }
    }
}