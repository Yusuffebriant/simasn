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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Pegawai;
use App\Services\ImportRekonsiliasi;
use App\Services\PegawaiSnapshotService;
use App\Services\RekapSnapshotService;
use App\Services\PerubahanPegawaiService;
use App\Support\RekapCache;

class ProcessPegawaiImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // import tidak aman diulang otomatis (bisa dobel proses)
    public int $timeout = 600; // 10 menit, cukup untuk file besar

    // Kalau riwayat import dihapus (mis. hapus paksa) sebelum job sempat jalan,
    // job dibuang diam-diam, tidak masuk failed_jobs.
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        protected ImportBatch $batch,
        protected string $filePath
    ) {}

    /**
     * Ekspor semua pegawai (+ detail) ke CSV di storage/app/.../backups.
     * Return path relatif file cadangan.
     */
    protected function buatCadangan(): string
    {
        $rel = 'backups/pegawai-sebelum-import-' . $this->batch->id
            . '-' . now()->format('Ymd-His') . '.csv';

        Storage::makeDirectory('backups');

        $fh = fopen(Storage::path($rel), 'w');
        if ($fh === false) {
            throw new \RuntimeException('Gagal membuat file cadangan data pegawai.');
        }

        $headerDitulis = false;

        $rows = DB::table('pegawai')
            ->leftJoin('pegawai_detail', 'pegawai_detail.pegawai_id', '=', 'pegawai.id')
            ->select(
                'pegawai.*',
                'pegawai_detail.alamat as detail_alamat',
                'pegawai_detail.hp as detail_hp',
                'pegawai_detail.email as detail_email',
                'pegawai_detail.status_perkawinan as detail_status_perkawinan'
            )
            ->orderBy('pegawai.id')
            ->cursor();

        foreach ($rows as $row) {
            $arr = (array) $row;
            if (!$headerDitulis) {
                fputcsv($fh, array_keys($arr), ',', '"', '');
                $headerDitulis = true;
            }
            fputcsv($fh, $arr, ',', '"', '');
        }

        fclose($fh);

        return $rel;
    }

    /**
     * DIAGNOSA SEMENTARA: catat pemakaian memori tiap tahap ke storage/logs/laravel.log
     * (ditulis sebagai WARNING karena LOG_LEVEL=warning). Tahap yang lonjakannya
     * paling besar adalah sumber masalah "Allowed memory size exhausted".
     * Boleh dihapus setelah penyebabnya ketemu.
     */
    protected function catatMemori(string $tahap): void
    {
        gc_collect_cycles();

        Log::warning(sprintf(
            '[MEMORI] batch %d setelah %s: sekarang %.1f MB, puncak %.1f MB',
            $this->batch->id,
            $tahap,
            memory_get_usage() / 1048576,
            memory_get_peak_usage() / 1048576
        ));
    }

    public function handle(): void
    {
        // Worker berjalan lama; jangan simpan riwayat query di memori.
        DB::disableQueryLog();

        // Batch dihapus saat job menunggu/baru mulai: batalkan, bersihkan file.
        if (!ImportBatch::whereKey($this->batch->id)->exists()) {
            Storage::delete($this->filePath);

            return;
        }

        try {
            // Cadangan seluruh data pegawai SEBELUM ada yang diubah/dihapus.
            // Kalau gagal membuat cadangan, import dihentikan (data aman).
            $backupPath = $this->buatCadangan();
            $this->batch->update(['backup_path' => $backupPath]);
            $this->catatMemori('cadangan CSV');

            // Foto kondisi pegawai SEBELUM import, untuk catatan perubahan
            // bulanan (pensiun, naik pangkat, pegawai baru, dst). Kalau gagal,
            // import tetap jalan; hanya catatan perubahannya yang tidak ada.
            $perubahan = new PerubahanPegawaiService();
            $foto = null;
            try {
                $foto = $perubahan->ambilFoto();
            } catch (\Throwable $e) {
                Log::warning("Import batch {$this->batch->id}: gagal mengambil foto perubahan pegawai: " . $e->getMessage());
            }

            // Objek import dipegang supaya hitungan isi file & rincian baris
            // ganda bisa dibaca lagi setelah proses selesai.
            $this->catatMemori('foto perubahan (sebelum baca Excel)');

            $import = new PegawaiImport($this->batch);

            Excel::import($import, Storage::path($this->filePath));
            $this->catatMemori('baca & simpan Excel');

            $this->batch->refresh();

            // MODE GANTI TOTAL: data pegawai lama yang tidak ikut di file
            // import ini dihapus permanen, jadi database = isi file terbaru.
            // Pengaman: kalau tidak ada satu pun baris yang berhasil
            // (file kosong / salah format), JANGAN hapus apa pun.
            $dihapus = 0;

            if ($this->batch->berhasil > 0) {
                $batchId = $this->batch->id;

                $dihapus = DB::transaction(function () use ($batchId) {
                    $query = Pegawai::withTrashed()->where(function ($q) use ($batchId) {
                        $q->whereNull('raw_import_id')
                          ->orWhere('raw_import_id', '!=', $batchId);
                    });

                    $jumlah = (clone $query)->count();

                    // forceDelete lewat query builder: permanen (menghindari
                    // bentrok unique NIP/NIK akibat soft delete) dan
                    // pegawai_detail ikut terhapus via cascade FK.
                    $query->forceDelete();

                    return $jumlah;
                });

                Log::info("Import batch {$batchId}: {$dihapus} pegawai lama dihapus (diganti data baru).");
            }

            // Catat perubahan pegawai (dibanding sebelum import). Dilakukan SEBELUM
            // status 'selesai' supaya begitu layar hasil muncul, catatannya sudah ada.
            // Kegagalan di sini tidak boleh membatalkan import yang datanya sudah masuk.
            if ($foto === null) {
                $perubahan->setStatus($this->batch, 'gagal');
            } elseif ($this->batch->berhasil > 0) {
                try {
                    $jumlahPerubahan = $perubahan->catat($this->batch, $foto);
                    Log::info("Import batch {$this->batch->id}: {$jumlahPerubahan} perubahan pegawai dicatat.");
                } catch (\Throwable $e) {
                    Log::error("Import batch {$this->batch->id}: gagal mencatat perubahan pegawai: " . $e->getMessage());
                    $perubahan->setStatus($this->batch, 'gagal');
                }
            }
            unset($foto);
            $this->catatMemori('catat perubahan pegawai');

            // Pengecekan otomatis (rekonsiliasi): bandingkan isi file dengan hasil
            // import periode ini. Kalau
            // jumlahnya berbeda (mis. 39 baris di Excel tapi 37 pegawai di
            // sistem), rincian baris yang saling menimpa ikut disimpan agar
            // alasannya tampil di layar hasil import. Kegagalan di sini tidak
            // boleh membatalkan import yang datanya sudah masuk.
            if ($this->batch->berhasil > 0) {
                try {
                    $rekon = (new ImportRekonsiliasi())->bandingkan($this->batch, $import->tally());
                    $rekon['ganda_detail'] = $import->gandaDetail();
                    // Baris dengan NIK sama tetapi nama berbeda: pegawai di baris
                    // sebelumnya tertimpa dan hilang dari database. Jumlah ini
                    // akurat walau rincian gandanya dibatasi.
                    $rekon['ganda_nik_beda_nama'] = $import->gandaNikBedaNamaJumlah();
                    // Pegawai tersimpan tapi tidak ikut rekap (bukan berstatus aktif).
                    $rekon['tidak_aktif_jumlah'] = $import->tidakAktifJumlah();
                    $rekon['tidak_aktif_detail'] = $import->tidakAktifDetail();
                    // Pegawai tersimpan tapi ada isian wajib kosong, beserta sebabnya
                    // (kolom tidak ada / sel kosong / isi tidak dikenali).
                    $rekon['kosong_jumlah'] = $import->kosongJumlah();
                    $rekon['kosong_detail'] = $import->kosongDetail();
                    $rekon['kosong_ringkas'] = $import->kosongRingkas();
                    // Sel Excel yang isinya rumus (=VLOOKUP, ="123") per kolom, supaya
                    // admin tahu kolom mana yang harus di-Paste Values di Excel.
                    $rekon['rumus_baris'] = $import->rumusJumlahBaris();
                    $rekon['rumus_ringkas'] = $import->rumusRingkas();

                    $this->batch->update([
                        'rekonsiliasi_status' => $rekon['status'],
                        'rekonsiliasi' => $rekon,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("Import batch {$this->batch->id}: rekonsiliasi gagal dihitung: " . $e->getMessage());
                    $this->batch->update(['rekonsiliasi_status' => 'tidak_tersedia']);
                }
            }

            $this->catatMemori('rekonsiliasi');

            $this->batch->update([
                'jumlah_dihapus' => $dihapus,
                'status' => 'selesai',
                'total_baris' => $this->batch->berhasil + $this->batch->gagal,
            ]);

            // Bekukan rekap periode ini sebagai arsip bulanan. Menu Indeks
            // Kumulatif membaca arsip ini untuk membandingkan Januari s.d.
            // bulan yang baru diupload, jadi harus ada tiap import selesai
            // (sebelumnya hanya terbentuk lewat "php artisan rekap:bekukan").
            // Kegagalan di sini tidak boleh membatalkan import yang datanya
            // sudah masuk.
            if ($this->batch->berhasil > 0) {
                try {
                    app(RekapSnapshotService::class)->bekukan($this->batch->periode);
                } catch (\Throwable $e) {
                    Log::warning("Import batch {$this->batch->id}: gagal membekukan rekap periode {$this->batch->periode}: " . $e->getMessage());
                }
                $this->catatMemori('bekukan rekap');

                // Snapshot INDIVIDUAL pegawai periode ini + riwayat perubahan
                // terhadap periode sebelumnya yang punya snapshot. Idempoten:
                // impor ulang isi yang sama tidak menambah apa pun. Kegagalan
                // di sini tidak boleh membatalkan import yang datanya sudah masuk.
                try {
                    $hasilSnapshot = app(PegawaiSnapshotService::class)->bangun($this->batch->periode, $this->batch, 'import');
                    Log::info("Import batch {$this->batch->id}: snapshot individual {$this->batch->periode} {$hasilSnapshot['status']} (versi {$hasilSnapshot['versi']}, {$hasilSnapshot['jumlah_pegawai']} pegawai, {$hasilSnapshot['jumlah_perubahan']} perubahan dihitung).");
                } catch (\Throwable $e) {
                    Log::warning("Import batch {$this->batch->id}: gagal membuat snapshot individual periode {$this->batch->periode}: " . $e->getMessage());
                }
                $this->catatMemori('snapshot individual');
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

            // Ganti versi cache rekap supaya SEMUA cache rekap lama tidak
            // terpakai lagi (menggantikan daftar Cache::forget manual yang
            // dulu melewatkan beberapa key). Di finally, jadi berlaku juga
            // kalau import gagal di tengah jalan dan data sudah sebagian
            // berubah. Setelah pembekuan snapshot, supaya periodeAktif()
            // yang baru ikut terbaca.
            RekapCache::bump();
        }
    }
}