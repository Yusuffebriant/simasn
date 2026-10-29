<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Pegawai;
use App\Models\RekapSnapshotData;
use App\Support\AlasanImport;
use App\Support\KelengkapanPegawai;
use App\Support\KlasifikasiJft;
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
        $this->cekJabatanBelumTerdaftar($peringatan);
        $this->cekJenisKelaminNip($peringatan);
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
     * JFT yang jabatannya belum dikenal KlasifikasiJft. Mereka tetap dihitung
     * sebagai Teknis, tetapi dilaporkan di sini beserta nama pegawainya supaya
     * admin bisa memutuskan masuk kelompok mana dan menambahkannya di
     * App\Support\KlasifikasiJft. Tidak muncul kalau semua jabatan sudah dikenal.
     */
    private function cekJabatanBelumTerdaftar(array &$peringatan): void
    {
        $rows = Pegawai::asnPenuh()
            ->where('status_aktif', 'aktif')
            ->whereRaw("UPPER(TRIM(jenis_kedudukan)) = 'FUNGSIONAL'")
            ->select('nip', 'nama', 'jabatan', 'unit')
            ->orderBy('nama')
            ->get();

        $perJabatan = [];

        foreach ($rows as $row) {
            if (KlasifikasiJft::terdaftar($row->jabatan)) {
                continue;
            }

            $nama = KlasifikasiJft::normalisasi($row->jabatan);
            $nama = $nama === '' ? '(JABATAN KOSONG)' : $nama;

            $perJabatan[$nama][] = [
                'nip' => $row->nip,
                'nama' => $row->nama,
                'unit' => $row->unit,
            ];
        }

        if ($perJabatan === []) {
            return;
        }

        $rincian = [];
        $totalPegawai = 0;

        foreach ($perJabatan as $jabatan => $pegawai) {
            $totalPegawai += count($pegawai);
            $rincian[] = [
                'jabatan' => $jabatan,
                'jumlah' => count($pegawai),
                'pegawai' => $pegawai,
            ];
        }

        usort($rincian, fn ($a, $b) => $b['jumlah'] <=> $a['jumlah']);

        $jumlahJabatan = count($rincian);

        $peringatan[] = $this->item(
            'jabatan_belum_terdaftar',
            'peringatan',
            'Jabatan fungsional belum terdaftar di sistem',
            "{$jumlahJabatan} jabatan ({$totalPegawai} pegawai) belum ada di daftar pengelompokan JFT, "
                . 'sehingga sementara dihitung sebagai Teknis. Jika ternyata bidang Pendidikan atau Kesehatan, '
                . 'tambahkan di app/Support/KlasifikasiJft.php. Setelah itu catatan ini hilang sendiri.',
            $totalPegawai,
            null,
            null,
            null,
            null,
            $rincian
        );
    }

    /**
     * Jenis kelamin di data tidak cocok dengan digit ke-15 NIP (1 = laki-laki,
     * 2 = perempuan). Hanya NIP 18 digit milik PNS dan PPPK yang diperiksa.
     * Angka Pria/Wanita di rekap mengikuti kolom jenis kelamin, jadi
     * ketidakcocokan ini bisa menggeser angka rekap. Perbaiki di data sumber.
     */
    private function cekJenisKelaminNip(array &$peringatan): void
    {
        $rows = Pegawai::asnPenuh()
            ->where('status_aktif', 'aktif')
            ->whereNotNull('jenis_kelamin')
            ->whereRaw('LENGTH(TRIM(nip)) = 18')
            ->select('nip', 'nama', 'jenis_kelamin', 'unit')
            ->orderBy('nama')
            ->get();

        $tidakCocok = [];

        foreach ($rows as $row) {
            $nip = trim((string) $row->nip);

            if (! ctype_digit($nip)) {
                continue;
            }

            $dariNip = match ($nip[14]) {
                '1' => 'L',
                '2' => 'P',
                default => null,
            };

            if ($dariNip !== null && $dariNip !== $row->jenis_kelamin) {
                $tidakCocok[] = $row;
            }
        }

        if ($tidakCocok === []) {
            return;
        }

        $jumlah = count($tidakCocok);
        $contoh = collect($tidakCocok)
            ->take(10)
            ->map(fn ($r) => $r->nama . ' (' . ($r->jenis_kelamin === 'L' ? 'tertulis pria' : 'tertulis wanita') . ')')
            ->implode(', ');
        $sisa = $jumlah > 10 ? ' dan ' . ($jumlah - 10) . ' lainnya' : '';

        $peringatan[] = $this->item(
            'jenis_kelamin_beda_nip',
            'peringatan',
            'Jenis kelamin tidak cocok dengan NIP',
            "{$jumlah} pegawai punya jenis kelamin di data yang berbeda dari digit ke-15 NIP: {$contoh}{$sisa}. "
                . 'Salah satunya keliru, dan bila data yang salah, angka Pria/Wanita di rekap ikut bergeser. '
                . 'Periksa lalu perbaiki di data sumber.',
            $jumlah,
            ['label' => 'Buka Daftar Pegawai', 'href' => '/admin?tab=pegawai']
        );
    }

    /**
     * @param array|null $rincianInstansi Perangkat daerah yang punya kekurangan
     *        (khusus peringatan data belum lengkap), lihat KelengkapanPegawai::perInstansi().
     */
    private function item(string $kode, string $tingkat, string $judul, string $pesan, ?int $jumlah = null, ?array $tautan = null, ?array $rincianInstansi = null, ?array $rincianGagal = null, ?string $penjelasan = null, ?array $rincianJabatan = null): array
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
            // Khusus peringatan jabatan belum terdaftar: jabatan + nama pegawainya.
            'rincian_jabatan' => $rincianJabatan,
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