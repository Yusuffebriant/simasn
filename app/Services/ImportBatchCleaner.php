<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\ImportBatchError;
use App\Models\ImportPerubahan;
use App\Models\Pegawai;
use App\Models\RekapSnapshot;
use App\Models\RekapSnapshotData;
use App\Support\RekapCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/**
 * Menghapus SATU riwayat import beserta SEMUA data yang berasal darinya,
 * supaya tidak ada data sisa di sistem:
 *
 *  - data pegawai hasil import ini (+ pegawai_detail dan log aktivitasnya)
 *  - catatan perubahan pegawai, dan catatan baris gagal
 *  - rekap beku (snapshot) periode ini, kalau tidak ada import lain
 *    dengan periode yang sama
 *  - file cadangan sebelum import, dan file upload yang tersisa
 *  - job import yang masih antre untuk batch ini
 *  - cache rekap
 *
 * Penghapusan bersifat permanen dan tidak bisa dibatalkan.
 */
class ImportBatchCleaner
{
    /**
     * Cek apakah job import batch ini sedang dikerjakan worker
     * (hanya untuk driver queue database). Job yang reserved lebih lama
     * dari retry_after dianggap sudah mati.
     */
    public function jobSedangDikerjakan(ImportBatch $batch): bool
    {
        return $this->jobBatch($batch)
            ->contains(fn ($j) => $this->masihReserved($j));
    }

    /**
     * Ringkasan yang akan terhapus (untuk ditampilkan di dialog konfirmasi).
     */
    public function ringkasan(ImportBatch $batch): array
    {
        return [
            'jumlah_pegawai' => Pegawai::withTrashed()->where('raw_import_id', $batch->id)->count(),
            'jumlah_perubahan' => ImportPerubahan::where('import_batch_id', $batch->id)->count(),
        ];
    }

    /**
     * Hapus batch + semua datanya. Mengembalikan jumlah yang terhapus.
     */
    public function hapusSemua(ImportBatch $batch): array
    {
        $batchId = $batch->id;
        $periode = $batch->periode;
        $backupPath = $batch->backup_path;

        // Job antre (belum/tidak lagi dikerjakan) milik batch ini.
        $jobAntre = $this->jobBatch($batch)->reject(fn ($j) => $this->masihReserved($j));
        $fileUpload = $jobAntre
            ->map(fn ($j) => $this->filePathDariPayload($j->payload))
            ->filter()
            ->values();

        $hasil = ['pegawai' => 0, 'perubahan' => 0, 'baris_gagal' => 0, 'snapshot' => 0];

        DB::transaction(function () use ($batch, $batchId, $periode, $jobAntre, &$hasil) {
            if ($jobAntre->isNotEmpty()) {
                DB::table($this->tabelJobs())->whereIn('id', $jobAntre->pluck('id'))->delete();
            }

            // Pegawai hasil import ini, termasuk yang sudah soft-delete.
            $hasil['pegawai'] = $this->hapusPegawai(
                Pegawai::withTrashed()->where('raw_import_id', $batchId)->pluck('id')
            );

            $hasil['perubahan'] = ImportPerubahan::where('import_batch_id', $batchId)->delete();
            $hasil['baris_gagal'] = ImportBatchError::where('import_batch_id', $batchId)->delete();

            $batch->delete();

            // Rekap beku periode ini hanya dibuang kalau tidak ada import lain
            // dengan periode yang sama.
            if (!ImportBatch::where('periode', $periode)->exists()) {
                $hasil['snapshot'] = RekapSnapshotData::where('periode', $periode)->delete();

                foreach (RekapSnapshot::where('periode', $periode)->get() as $lama) {
                    if ($lama->file_path) {
                        Storage::delete($lama->file_path);
                    }
                    $lama->delete();
                }
            }
        });

        // File dihapus setelah database beres (kalau transaksi gagal, file tetap ada).
        if ($backupPath) {
            Storage::delete($backupPath);
        }
        foreach ($fileUpload as $path) {
            Storage::delete($path);
        }

        // Tidak ada import tersisa = tidak ada pemilik data lagi. Pegawai tanpa
        // pemilik (sisa penghapusan riwayat lama) dan file yatim ikut dibersihkan.
        if (!ImportBatch::exists()) {
            $hasil['sisa'] = $this->hapusSisa();
        }

        // Semua cache rekap lama tidak terpakai lagi.
        RekapCache::bump();

        Log::info("Import batch {$batchId} (periode {$periode}) dihapus total.", $hasil);

        return $hasil;
    }

    /**
     * Hitung data sisa: data yang tidak terhubung ke riwayat import mana pun
     * (mis. pegawai dari riwayat yang dulu dihapus saat aplikasi belum
     * menghapus total).
     */
    public function hitungSisa(): array
    {
        return [
            'pegawai' => Pegawai::withTrashed()->whereNull('raw_import_id')->count(),
            'snapshot' => RekapSnapshotData::whereNotIn('periode', $this->periodeAda())->count(),
            'file_cadangan' => count($this->fileCadanganYatim()),
            'file_upload' => count($this->fileUploadYatim()),
        ];
    }

    /**
     * Hapus semua data sisa (lihat hitungSisa). Pegawai yang masih terhubung
     * ke riwayat import TIDAK disentuh.
     */
    public function hapusSisa(): array
    {
        $hasil = ['pegawai' => 0, 'snapshot' => 0, 'file_cadangan' => 0, 'file_upload' => 0];

        DB::transaction(function () use (&$hasil) {
            $hasil['pegawai'] = $this->hapusPegawai(
                Pegawai::withTrashed()->whereNull('raw_import_id')->pluck('id')
            );

            $periode = $this->periodeAda();
            $hasil['snapshot'] = RekapSnapshotData::whereNotIn('periode', $periode)->delete();

            foreach (RekapSnapshot::whereNotIn('periode', $periode)->get() as $lama) {
                if ($lama->file_path) {
                    Storage::delete($lama->file_path);
                }
                $lama->delete();
            }
        });

        foreach ($this->fileCadanganYatim() as $path) {
            Storage::delete($path);
            $hasil['file_cadangan']++;
        }
        foreach ($this->fileUploadYatim() as $path) {
            Storage::delete($path);
            $hasil['file_upload']++;
        }

        RekapCache::bump();

        Log::info('Data sisa import dibersihkan.', $hasil);

        return $hasil;
    }

    /** Hapus permanen pegawai berikut detail & log aktivitasnya. Return jumlahnya. */
    private function hapusPegawai(Collection $ids): int
    {
        $morph = (new Pegawai())->getMorphClass();

        foreach ($ids->chunk(1000) as $potongan) {
            $potongan = $potongan->values()->all();

            Activity::query()
                ->where('subject_type', $morph)
                ->whereIn('subject_id', $potongan)
                ->delete();

            DB::table('pegawai_detail')->whereIn('pegawai_id', $potongan)->delete();

            // Lewat query builder: hapus permanen tanpa memicu log aktivitas baru.
            Pegawai::withTrashed()->whereIn('id', $potongan)->forceDelete();
        }

        return $ids->count();
    }

    private function periodeAda(): array
    {
        return ImportBatch::query()->pluck('periode')->unique()->values()->all();
    }

    /** File backups/* yang tidak dipakai batch mana pun. */
    private function fileCadanganYatim(): array
    {
        $dipakai = ImportBatch::query()->whereNotNull('backup_path')->pluck('backup_path')->all();

        return array_values(array_diff(Storage::files('backups'), $dipakai));
    }

    /** File imports/* yang tidak dirujuk job antre mana pun. */
    private function fileUploadYatim(): array
    {
        $dipakai = [];

        if (config('queue.default') === 'database') {
            DB::table($this->tabelJobs())
                ->where('payload', 'like', '%ProcessPegawaiImport%')
                ->pluck('payload')
                ->each(function ($payload) use (&$dipakai) {
                    if ($path = $this->filePathDariPayload($payload)) {
                        $dipakai[] = $path;
                    }
                });
        }

        return array_values(array_diff(Storage::files('imports'), $dipakai));
    }

    // ------------------------------------------------------------------

    private function tabelJobs(): string
    {
        return config('queue.connections.database.table', 'jobs');
    }

    /** Baris tabel jobs milik batch ini (kosong kalau queue bukan database). */
    private function jobBatch(ImportBatch $batch): Collection
    {
        if (config('queue.default') !== 'database') {
            return collect();
        }

        return DB::table($this->tabelJobs())
            ->where('payload', 'like', '%ProcessPegawaiImport%')
            ->get(['id', 'payload', 'reserved_at'])
            ->filter(fn ($j) => (bool) preg_match(
                '/ImportBatch.{0,20}?id.{0,10}?i:' . (int) $batch->id . ';/s',
                $j->payload
            ))
            ->values();
    }

    private function masihReserved(object $job): bool
    {
        if ($job->reserved_at === null) {
            return false;
        }

        $retryAfter = (int) config('queue.connections.database.retry_after', 600);

        return (int) $job->reserved_at > now()->subSeconds($retryAfter)->getTimestamp();
    }

    /** Ambil path file upload (mis. imports/abc.xlsx) dari payload job. */
    private function filePathDariPayload(string $payload): ?string
    {
        if (preg_match('/filePath\\\\*";s:\d+:\\\\*"([^"\\\\]+)/', $payload, $m)) {
            return $m[1];
        }

        return null;
    }
}