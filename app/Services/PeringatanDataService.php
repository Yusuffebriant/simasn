<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Pegawai;
use App\Models\RekapSnapshotData;
use App\Support\AlasanImport;
use App\Support\KelengkapanPegawai;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pemeriksaan kesehatan data pegawai (kondisi SAAT INI), untuk banner
 * peringatan di halaman Admin.
 *
 * Berbeda dengan sinkron/rekonsiliasi di ProcessPegawaiImport yang hanya
 * melaporkan hasil SATU import, pemeriksaan ini membaca isi database
 * sekarang, jadi masalah tetap terlihat walaupun tidak ada yang membuka
 * Riwayat Import.
 *
 * Tingkat: 'bahaya' (angka rekap hampir pasti salah), 'peringatan'
 * (perlu dicek), 'info' (hanya pemberitahuan).
 */
class PeringatanDataService
{
    public function periksa(): array
    {
        $batch = ImportBatch::query()
            ->where('status', 'selesai')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $peringatan = [];

        if (! $batch) {
            $peringatan[] = $this->item(
                'belum_ada_import',
                'info',
                'Belum ada import',
                'Belum ada import yang selesai, jadi data belum bisa diperiksa.'
            );

            return $this->hasil($peringatan, null);
        }

        $this->cekSisaBatchLama($batch, $peringatan);
        $this->cekKemungkinanDobel($peringatan);
        $this->cekImportTerakhir($batch, $peringatan);
        $this->cekKelengkapan($peringatan);
        $this->cekSnapshot($batch, $peringatan);

        return $this->hasil($peringatan, $batch->periode);
    }

    /**
     * Pegawai aktif yang BUKAN dari import terakhir (batch lama, atau
     * batch yang riwayatnya sudah dihapus sehingga raw_import_id NULL).
     * Inilah penyebab total 6.733 vs 6.367 sebelumnya.
     */
    private function cekSisaBatchLama(ImportBatch $batch, array &$peringatan): void
    {
        $jumlah = Pegawai::where('status_aktif', 'aktif')
            ->where(function ($q) use ($batch) {
                $q->whereNull('raw_import_id')
                  ->orWhere('raw_import_id', '!=', $batch->id);
            })
            ->count();

        if ($jumlah === 0) {
            return;
        }

        $pesan = "{$jumlah} pegawai berstatus aktif tetapi bukan berasal dari import terakhir "
            . "(batch #{$batch->id}, periode {$batch->periode}). Kemungkinan sisa data lama, "
            . 'sehingga total di rekap bisa berlebih.';

        if ($batch->sinkron_status === 'dilewati' && $batch->sinkron_catatan) {
            $pesan .= ' Penonaktifan otomatis dilewati: ' . $batch->sinkron_catatan;
        }

        $peringatan[] = $this->item(
            'sisa_batch_lama',
            'bahaya',
            'Ada pegawai aktif sisa import lama',
            $pesan,
            $jumlah
        );
    }

    /**
     * Pegawai aktif dengan nama dan tanggal lahir sama. NIP/NIK berbeda
     * (jadi lolos dari pencocokan import) tetapi hampir pasti orang yang
     * sama.
     */
    private function cekKemungkinanDobel(array &$peringatan): void
    {
        $kelompok = Pegawai::where('status_aktif', 'aktif')
            ->whereNotNull('tanggal_lahir')
            ->select(DB::raw('COUNT(*) as n'))
            ->groupBy(DB::raw('UPPER(TRIM(nama))'), 'tanggal_lahir')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('n');

        if ($kelompok->isEmpty()) {
            return;
        }

        $orang = $kelompok->count();
        $kelebihan = (int) $kelompok->sum() - $orang;

        $peringatan[] = $this->item(
            'kemungkinan_dobel',
            'peringatan',
            'Kemungkinan pegawai tercatat dobel',
            "{$orang} orang muncul lebih dari satu kali dengan nama dan tanggal lahir yang sama "
            . "({$kelebihan} baris berlebih). Periksa apakah NIP/NIK-nya berbeda karena salah input.",
            $kelebihan
        );
    }

    /** Hasil sinkron/rekonsiliasi/baris gagal dari import terakhir. */
    private function cekImportTerakhir(ImportBatch $batch, array &$peringatan): void
    {
        if ((int) $batch->gagal > 0) {
            [$rincian, $penjelasan] = $this->uraikanBarisGagal($batch);

            $peringatan[] = $this->item(
                'baris_gagal',
                'peringatan',
                'Import terakhir punya baris gagal',
                "{$batch->gagal} baris di import terakhir (periode {$batch->periode}) gagal diproses, jadi belum masuk ke data."
                . ($rincian ? ' Alasan dan bagian file Excel yang perlu diperbaiki ada di bawah.' : ' Buka Riwayat Import untuk melihat daftar barisnya.'),
                (int) $batch->gagal,
                null,
                null,
                $rincian,
                $penjelasan
            );
        }

        if ($batch->rekonsiliasi_status === 'selisih') {
            $peringatan[] = $this->item(
                'rekonsiliasi_selisih',
                'peringatan',
                'Isi file tidak sama dengan hasil import periode ' . $batch->periode,
                'Pengecekan otomatis pada import periode ' . $batch->periode . ' menunjukkan selisih antara isi file '
                . 'dan data yang berhasil masuk (biasanya ada baris ganda di file). Lihat detailnya di Riwayat Import.'
            );
        }
    }

    /**
     * Kelompokkan baris gagal import terakhir per masalah (kolom Excel yang harus
     * diperbaiki, jumlah, contoh baris, cara memperbaiki) dan buat kalimat yang
     * menjelaskan kenapa jumlahnya bisa sebanyak itu. Hasilnya di-cache per batch
     * karena daftar error batch yang sudah selesai tidak berubah.
     *
     * @return array{0: array, 1: ?string} [rincian, penjelasan]
     */
    private function uraikanBarisGagal(ImportBatch $batch): array
    {
        try {
            $rincian = Cache::remember(
                "peringatan.gagal.{$batch->id}.{$batch->gagal}",
                now()->addHour(),
                fn () => AlasanImport::ringkasSaran(
                    $batch->errors()
                        ->select('import_batch_id', 'baris_ke', 'pesan', 'data_mentah')
                        ->orderBy('baris_ke')
                        ->cursor(),
                    5
                )
            );
        } catch (\Throwable $e) {
            Log::warning("Peringatan data: gagal menguraikan baris gagal batch {$batch->id}: " . $e->getMessage());

            return [[], null];
        }

        if ($rincian === []) {
            return [[], null];
        }

        $total = (int) $batch->gagal;
        $utama = $rincian[0];
        $persen = $total > 0 ? min(100, (int) round($utama['jumlah'] / $total * 100)) : 0;

        foreach ($rincian as &$r) {
            $r['persen'] = $total > 0 ? min(100, (int) round($r['jumlah'] / $total * 100)) : 0;
        }
        unset($r);

        $penjelasan = ($total >= 20
                ? 'Jumlahnya besar karena kesalahan yang sama berulang di banyak baris. '
                : '')
            . "Penyebab terbanyak: {$utama['judul']} (kolom {$utama['kolom']}), "
            . number_format($utama['jumlah'], 0, ',', '.') . " baris atau {$persen}% dari yang gagal."
            . ($persen >= 50 ? ' Memperbaiki kolom itu di file Excel akan menyelesaikan sebagian besar baris gagal.' : '')
            . ' Satu baris bisa punya lebih dari satu masalah, jadi jumlah per masalah bisa lebih besar dari total.';

        return [$rincian, $penjelasan];
    }

    /** Periode aktif belum punya snapshot rekap. */
    private function cekSnapshot(ImportBatch $batch, array &$peringatan): void
    {
        if (blank($batch->periode)) {
            return;
        }

        $ada = RekapSnapshotData::where('periode', $batch->periode)
            ->where('nama', 'dashboard')
            ->exists();

        if (! $ada) {
            $peringatan[] = $this->item(
                'snapshot_belum_ada',
                'info',
                'Rekap periode aktif belum dibekukan',
                "Rekap periode {$batch->periode} belum disimpan sebagai arsip. Jalankan "
                . "\"php artisan rekap:bekukan\" supaya periode ini tetap bisa dibuka setelah import berikutnya."
            );
        }
    }

    /** Pegawai aktif yang isiannya belum lengkap (seleksi kelengkapan data). */
    private function cekKelengkapan(array &$peringatan): void
    {
        $ringkasan = KelengkapanPegawai::ringkasan();

        if ($ringkasan['total_belum_lengkap'] === 0) {
            return;
        }

        $rincian = collect($ringkasan['per_kolom'])
            ->take(4)
            ->map(fn ($k) => "{$k['label']} ({$k['jumlah']})")
            ->implode(', ');

        $peringatan[] = $this->item(
            'data_belum_lengkap',
            'peringatan',
            'Data pegawai belum lengkap',
            "Ada pegawai aktif yang isiannya masih kosong. Terbanyak: {$rincian}. "
                . 'Cukup periksa perangkat daerah yang tercantum di bawah, lalu lengkapi lewat Daftar Pegawai.',
            $ringkasan['total_belum_lengkap'],
            ['label' => 'Lengkapi sekarang', 'href' => '/admin?tab=pegawai&kelengkapan=belum'],
            $ringkasan['per_instansi'] ?? []
        );
    }

    /**
     * @param array|null $rincianInstansi Perangkat daerah yang punya kekurangan
     *        (khusus peringatan data belum lengkap), lihat KelengkapanPegawai::perInstansi().
     */
    private function item(string $kode, string $tingkat, string $judul, string $pesan, ?int $jumlah = null, ?array $tautan = null, ?array $rincianInstansi = null, ?array $rincianGagal = null, ?string $penjelasan = null): array
    {
        return [
            'kode' => $kode,
            'tingkat' => $tingkat,
            'judul' => $judul,
            'pesan' => $pesan,
            'jumlah' => $jumlah,
            'tautan' => $tautan,
            'rincian_instansi' => $rincianInstansi,
            // Khusus peringatan baris gagal: kelompok masalah + kalimat penjelasan.
            'rincian_gagal' => $rincianGagal,
            'penjelasan' => $penjelasan,
        ];
    }

    private function hasil(array $peringatan, ?string $periodeAktif): array
    {
        $perluTindakan = array_filter(
            $peringatan,
            fn ($p) => in_array($p['tingkat'], ['bahaya', 'peringatan'], true)
        );

        return [
            'ok' => $perluTindakan === [],
            'periode_aktif' => $periodeAktif,
            'diperiksa_pada' => now()->toDateTimeString(),
            'peringatan' => array_values($peringatan),
        ];
    }
}