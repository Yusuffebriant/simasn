<?php

namespace App\Jobs;

use App\Imports\PegawaiImport;
use App\Models\ImportBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Pegawai;
use App\Services\ImportRekonsiliasi;
use App\Services\RekapSnapshotService;
use App\Support\RekapCache;

class ProcessPegawaiImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // import tidak aman diulang otomatis (bisa dobel proses)
    public int $timeout = 600; // 10 menit, cukup untuk file besar

    public function __construct(
        protected ImportBatch $batch,
        protected string $filePath
    ) {}

    public function handle(): void
    {
        try {
            $import = new PegawaiImport($this->batch);

            Excel::import($import, Storage::path($this->filePath));

            $this->batch->refresh();

            // Urutan penting: sinkron & reset cache dulu, baru status
            // 'selesai'. Frontend membaca hasil begitu status 'selesai',
            // jadi hasil sinkron dan angka rekap sudah final saat itu.
            $this->jalankanRekonsiliasi($import->tally());

            $this->nonaktifkanPegawaiSisa();

            // Naikkan versi cache rekap: semua key rekap (semua periode,
            // termasuk endpoint yang ditambahkan kemudian) otomatis tidak
            // terpakai lagi, jadi tidak ada angka lama yang tertinggal
            // sampai TTL 10 menit habis.
            RekapCache::bump();

            // Bekukan rekap periode import ini supaya tetap bisa dibuka
            // setelah ada import periode berikutnya.
            $this->bekukanRekap();

            $this->batch->update([
                'status' => 'selesai',
                'total_baris' => $this->batch->berhasil + $this->batch->gagal,
            ]);
        } catch (\Throwable $e) {
            $this->batch->update([
                'status' => 'gagal'
            ]);

            throw $e; // tetap lempar supaya tercatat di failed_jobs untuk debugging

        } finally {
            // Hapus file sementara setelah selesai diproses
            // (berhasil ataupun gagal)
            Storage::delete($this->filePath);
        }
    }

    /**
     * Setelah import penuh, pegawai aktif dari batch LAIN (import lama)
     * yang tidak ikut ter-update oleh file ini ditandai 'mutasi_keluar',
     * supaya tidak ikut terhitung di rekap.
     *
     * Pengaman: dilewati jika ada baris gagal, tidak ada baris berhasil,
     * atau jumlah yang akan dinonaktifkan > 20% pegawai aktif (curiga
     * file hanya sebagian data).
     */
    private function nonaktifkanPegawaiSisa(): void
    {
        if ($this->batch->gagal > 0 || $this->batch->berhasil <= 0) {
            Log::info('Sinkron import dilewati: ada baris gagal / tidak ada baris berhasil', [
                'batch_id' => $this->batch->id,
            ]);
            $this->catatSinkron(
                'dilewati',
                null,
                null,
                $this->batch->berhasil <= 0
                    ? 'Tidak ada baris berhasil.'
                    : "Ada {$this->batch->gagal} baris gagal; penonaktifan dilewati demi keamanan data."
            );
            return;
        }

        $sisa = Pegawai::where('status_aktif', 'aktif')
            ->where(function ($q) {
                $q->whereNull('raw_import_id')
                  ->orWhere('raw_import_id', '!=', $this->batch->id);
            });

        $jumlahSisa = (clone $sisa)->count();
        $totalAktif = Pegawai::where('status_aktif', 'aktif')->count();

        if ($jumlahSisa === 0) {
            $this->catatSinkron('tidak_ada_sisa', 0, $totalAktif, 'Semua pegawai aktif ada di file ini.');
            return;
        }

        if ($totalAktif > 0 && ($jumlahSisa / $totalAktif) > 0.20) {
            Log::warning('Sinkron import dibatalkan: terlalu banyak pegawai akan dinonaktifkan', [
                'batch_id' => $this->batch->id,
                'jumlah_sisa' => $jumlahSisa,
                'total_aktif' => $totalAktif,
            ]);
            $persen = round($jumlahSisa / $totalAktif * 100, 1);
            $this->catatSinkron(
                'dilewati',
                $jumlahSisa,
                $totalAktif,
                "{$jumlahSisa} pegawai tidak ada di file ({$persen}% dari pegawai aktif), melebihi batas 20%. Tidak ada yang dinonaktifkan; periksa apakah file lengkap."
            );
            return;
        }

        $sisa->update(['status_aktif' => 'mutasi_keluar']);

        Log::info('Sinkron import: pegawai sisa batch lama dinonaktifkan', [
            'batch_id' => $this->batch->id,
            'jumlah' => $jumlahSisa,
        ]);

        $this->catatSinkron(
            'dinonaktifkan',
            $jumlahSisa,
            $totalAktif,
            "{$jumlahSisa} pegawai yang tidak ada di file ditandai mutasi keluar."
        );
    }

    /**
     * Bandingkan isi file (tally dari importer) dengan isi database.
     * Kegagalan di sini TIDAK boleh menggagalkan import, jadi dibungkus
     * try/catch dan hanya ditandai 'tidak_tersedia'.
     */
    private function jalankanRekonsiliasi(array $tally): void
    {
        try {
            $hasil = (new ImportRekonsiliasi())->bandingkan($this->batch, $tally);

            $this->batch->update([
                'rekonsiliasi_status' => $hasil['status'],
                'rekonsiliasi' => $hasil,
            ]);

            if ($hasil['status'] === 'selisih') {
                Log::warning('Rekonsiliasi import: ada selisih file vs database', [
                    'batch_id' => $this->batch->id,
                    'total' => $hasil['total'],
                    'jumlah_selisih' => count($hasil['selisih']),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Rekonsiliasi import gagal dihitung', [
                'batch_id' => $this->batch->id,
                'error' => $e->getMessage(),
            ]);

            $this->batch->update(['rekonsiliasi_status' => 'tidak_tersedia']);
        }
    }

    /**
     * Simpan hasil sinkron di batch supaya terlihat di halaman Import
     * dan Riwayat Import (tidak perlu membuka laravel.log).
     */
    private function catatSinkron(string $status, ?int $jumlahSisa, ?int $totalAktif, string $catatan): void
    {
        $this->batch->update([
            'sinkron_status' => $status,
            'sinkron_jumlah_sisa' => $jumlahSisa,
            'sinkron_total_aktif' => $totalAktif,
            'sinkron_catatan' => mb_substr($catatan, 0, 250),
        ]);
    }

    /**
     * Simpan snapshot rekap untuk periode batch ini. Kegagalan di sini
     * tidak boleh menggagalkan import (data pegawai sudah tersimpan),
     * jadi hanya dicatat di log.
     */
    private function bekukanRekap(): void
    {
        if (blank($this->batch->periode)) {
            return;
        }

        try {
            (new RekapSnapshotService())->bekukan($this->batch->periode);
        } catch (\Throwable $e) {
            Log::error('Gagal membekukan rekap periode import', [
                'batch_id' => $this->batch->id,
                'periode' => $this->batch->periode,
                'error' => $e->getMessage(),
            ]);
        }
    }
}