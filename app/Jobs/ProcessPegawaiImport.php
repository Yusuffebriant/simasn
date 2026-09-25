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
use Illuminate\Support\Facades\Cache;

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
            Excel::import(
                new PegawaiImport($this->batch),
                Storage::path($this->filePath)
            );

            $this->batch->refresh();

            $this->batch->update([
                'status' => 'selesai',
                'total_baris' => $this->batch->berhasil + $this->batch->gagal,
            ]);

            // Hapus cache rekap setelah seluruh proses import selesai.
            //
            // FIX: sebelumnya hanya 3 key (agama/pendidikan/jabatan) yang
            // dihapus, padahal RekapController meng-cache SEMUA endpoint
            // rekap (termasuk dashboard) per periode selama 10 menit. Ini
            // yang menyebabkan angka di Dashboard (mis. kartu Generasi,
            // tabel Usia, grafik Golongan, dll.) bisa tampak "salah
            // hitung"/tidak konsisten segera setelah import baru selesai —
            // padahal cuma menyajikan data cache lama sampai TTL-nya habis.
            // Sekarang semua key rekap per periode ikut dihapus supaya
            // seluruh halaman rekap langsung menampilkan data terbaru.
            foreach (
                [
                    'rekap.dashboard',
                    'rekap.agama',
                    'rekap.pendidikan',
                    'rekap.jabatan',
                    'rekap.golongan',
                    'rekap.eselon-golongan-gender',
                    'rekap.nakes',
                    'rekap.sd',
                    'rekap.smp',
                    'rekap.kecamatan',
                    'rekap.struktur-golongan',
                    'rekap.struktur-eselon',
                    'rekap.jf-tertentu',
                    'rekap.jf-pelaksana',
                ] as $rekapKey
            ) {
                Cache::forget("{$rekapKey}.{$this->batch->periode}");
            }
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
}
