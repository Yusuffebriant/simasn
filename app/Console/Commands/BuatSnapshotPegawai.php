<?php

namespace App\Console\Commands;

use App\Services\PegawaiSnapshotService;
use App\Support\RekapCache;
use Illuminate\Console\Command;

/**
 * Membuat snapshot individual pegawai dari data SAAT INI (tabel pegawai).
 *
 * Normalnya snapshot dibuat otomatis tiap import selesai. Perintah ini untuk
 * periode aktif yang diimport SEBELUM fitur snapshot individual ada, atau
 * untuk menghitung ulang riwayat perubahan.
 *
 *   php artisan pegawai:snapshot              # periode aktif
 *   php artisan pegawai:snapshot 2026-10      # harus sama dengan periode aktif
 *   php artisan pegawai:snapshot 2026-10 --paksa
 *   php artisan pegawai:snapshot --hitung-ulang
 *
 * Tabel pegawai hanya berisi data periode aktif. Memberi label periode lain
 * pada data ini sama dengan mengarang riwayat, jadi ditolak kecuali --paksa.
 */
class BuatSnapshotPegawai extends Command
{
    protected $signature = 'pegawai:snapshot
        {periode? : Format YYYY-MM, default periode aktif}
        {--paksa : Izinkan periode yang bukan periode aktif (hati-hati)}
        {--hitung-ulang : Hitung ulang riwayat perubahan semua periode berurutan (tanpa membuat snapshot baru)}';

    protected $description = 'Buat snapshot individual pegawai dari data saat ini dan hitung riwayat perubahannya';

    public function handle(PegawaiSnapshotService $snapshot): int
    {
        if ($this->option('hitung-ulang')) {
            $jumlah = $snapshot->hitungUlangSemua();
            $this->info("Riwayat perubahan dihitung ulang ({$jumlah} catatan).");

            return self::SUCCESS;
        }

        $aktif = RekapCache::periodeAktif();
        $periode = $this->argument('periode') ?: $aktif;

        if (! $periode || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode)) {
            $this->error('Periode tidak valid. Pakai format YYYY-MM, contoh: 2026-10.');

            return self::FAILURE;
        }

        if ($periode !== $aktif && ! $this->option('paksa')) {
            $this->error("Data di tabel pegawai adalah data periode aktif ({$aktif}), bukan {$periode}. "
                . 'Snapshot periode lain tidak dibuat supaya riwayat tidak dikarang. Pakai --paksa jika Anda yakin.');

            return self::FAILURE;
        }

        $hasil = $snapshot->bangun($periode, null, 'perintah');

        $this->info("Snapshot {$periode}: {$hasil['status']} (versi {$hasil['versi']}, {$hasil['jumlah_pegawai']} pegawai, {$hasil['jumlah_perubahan']} perubahan dihitung).");

        return self::SUCCESS;
    }
}