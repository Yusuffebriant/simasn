<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\ImportBatchCleaner;
use App\Support\RekapCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menu "Perawatan Sistem": menjelaskan perintah (artisan/terminal) yang ada
 * di sistem dan menjalankan yang aman langsung dari layar admin.
 *
 * Hanya perintah di daftar PERINTAH yang bisa dijalankan (daftar putih).
 * Perintah yang berbahaya atau hanya cocok lewat terminal (migrasi, build,
 * queue:work) hanya dijelaskan, tidak bisa dijalankan dari sini.
 */
class PerawatanController extends Controller
{
    /** Perintah yang boleh dijalankan dari layar. */
    private const PERINTAH = [
        'cache-rekap' => [
            'kelompok' => 'Cache & tampilan',
            'judul' => 'Segarkan cache rekap',
            'perintah' => null, // bukan perintah artisan: RekapCache::bump()
            'penjelasan' => 'Membuat semua hasil rekap yang tersimpan sementara (cache 10 menit) dihitung ulang dari data pegawai sekarang. Pakai kalau angka di Rekapitulasi/Statistik terasa belum berubah setelah import atau koreksi data. Aman: tidak ada data yang dihapus.',
            'risiko' => 'aman',
        ],
        'cache-clear' => [
            'kelompok' => 'Cache & tampilan',
            'judul' => 'Bersihkan semua cache aplikasi',
            'perintah' => 'php artisan cache:clear',
            'penjelasan' => 'Mengosongkan seluruh cache aplikasi (bukan hanya rekap). Pakai kalau data di layar terasa usang dan "Segarkan cache rekap" belum membantu. Aman: data pegawai, riwayat import, dan login pengguna tidak terpengaruh; halaman hanya terasa sedikit lebih lambat saat pertama dibuka lagi.',
            'risiko' => 'aman',
        ],
        'optimize-clear' => [
            'kelompok' => 'Cache & tampilan',
            'judul' => 'Hapus cache konfigurasi, rute, dan tampilan',
            'perintah' => 'php artisan optimize:clear',
            'penjelasan' => 'Menghapus cache konfigurasi (.env), daftar rute, tampilan, dan cache aplikasi. Pakai setelah mengganti file kode atau .env di server supaya perubahan terbaca. Aman: tidak menyentuh data.',
            'risiko' => 'aman',
        ],
        'queue-restart' => [
            'kelompok' => 'Antrean import',
            'judul' => 'Mulai ulang worker antrean',
            'perintah' => 'php artisan queue:restart',
            'penjelasan' => 'Memberi tahu worker antrean agar berhenti setelah pekerjaan yang sedang jalan selesai, lalu dijalankan ulang oleh pengawas proses (supervisor/layanan). Wajib setelah memperbarui kode import, karena worker lama masih memakai kode lama. Import yang sedang berjalan tidak terputus. Catatan: kalau worker dijalankan manual (queue:work di terminal), jalankan ulang perintah itu sendiri setelah berhenti.',
            'risiko' => 'aman',
        ],
        'rekap-bekukan' => [
            'kelompok' => 'Arsip bulanan',
            'judul' => 'Bekukan rekap periode aktif',
            'perintah' => 'php artisan rekap:bekukan',
            'penjelasan' => 'Menyimpan rekap kondisi data sekarang sebagai arsip periode aktif. Normalnya sudah otomatis tiap import selesai; pakai kalau arsip suatu periode belum ada atau perlu dibuat ulang. Hanya untuk periode aktif, supaya arsip tidak salah label. Bisa memakan waktu beberapa menit.',
            'risiko' => 'sedang',
            'tolak_saat_import' => true,
        ],
        'pegawai-snapshot' => [
            'kelompok' => 'Arsip bulanan',
            'judul' => 'Buat snapshot pegawai periode aktif',
            'perintah' => 'php artisan pegawai:snapshot',
            'penjelasan' => 'Menyalin data tiap pegawai periode aktif sebagai arsip, lalu menghitung perubahannya terhadap bulan sebelumnya (menu Indeks Kumulatif dan Perubahan Pegawai). Normalnya otomatis tiap import selesai; pakai untuk periode yang diimport sebelum fitur ini ada. Mengulang dengan isi sama tidak menambah apa pun.',
            'risiko' => 'sedang',
            'tolak_saat_import' => true,
        ],
        'pegawai-snapshot-hitung-ulang' => [
            'kelompok' => 'Arsip bulanan',
            'judul' => 'Hitung ulang riwayat perubahan semua bulan',
            'perintah' => 'php artisan pegawai:snapshot --hitung-ulang',
            'penjelasan' => 'Menghitung ulang catatan perubahan antar bulan dari arsip yang sudah ada, tanpa membuat arsip baru. Pakai kalau halaman perubahan antar bulan terlihat tidak sesuai arsipnya (misalnya setelah koreksi atau urutan upload yang tertukar).',
            'risiko' => 'sedang',
            'tolak_saat_import' => true,
        ],
        'bersihkan-sisa' => [
            'kelompok' => 'Pembersihan data',
            'judul' => 'Hapus data sisa import',
            'perintah' => 'php artisan import:bersihkan-sisa',
            'penjelasan' => 'Menghapus PERMANEN pegawai, arsip rekap, dan file upload yang tidak terhubung ke riwayat import mana pun (sisa dari import yang sudah dihapus). Pegawai yang terhubung ke riwayat import tidak dihapus, dan CSV cadangan tidak ikut dihapus. Tidak bisa dibatalkan.',
            'risiko' => 'bahaya',
            'tolak_saat_import' => true,
            'perlu_konfirmasi' => true,
        ],
    ];

    /** Perintah yang hanya dijelaskan (tidak bisa dijalankan dari layar). */
    private const HANYA_TERMINAL = [
        [
            'judul' => 'Jalankan worker antrean',
            'perintah' => 'php artisan queue:work',
            'penjelasan' => 'Proses yang membaca file Excel dan menyimpannya ke database. Harus selalu berjalan; tanpa ini upload akan berhenti di status "diproses". Di server biasanya dijaga supervisor atau layanan sistem.',
        ],
        [
            'judul' => 'Perbarui struktur database',
            'perintah' => 'php artisan migrate',
            'penjelasan' => 'Menambah tabel/kolom baru setelah kode diperbarui. Dijalankan sekali tiap ada file migrasi baru. Tidak disediakan di layar karena mengubah struktur database; buat cadangan database dulu.',
        ],
        [
            'judul' => 'Isi data referensi awal',
            'perintah' => 'php artisan db:seed',
            'penjelasan' => 'Mengisi data awal (agama, pendidikan, golongan, eselon, instansi, wilayah, peran pengguna). Biasanya hanya saat pemasangan pertama.',
        ],
        [
            'judul' => 'Bangun ulang tampilan (frontend)',
            'perintah' => 'npm run build',
            'penjelasan' => 'Mengompilasi kode tampilan (React) menjadi file di public/build. Jalankan setelah mengganti file .jsx, lalu muat ulang halaman dengan Ctrl+F5.',
        ],
        [
            'judul' => 'Lihat antrean yang gagal',
            'perintah' => 'php artisan queue:failed',
            'penjelasan' => 'Menampilkan pekerjaan import yang gagal beserta pesan kesalahannya. Jumlahnya juga terlihat di kartu "Status antrean" di atas.',
        ],
    ];

    /**
     * GET /api/perawatan
     * Daftar perintah + status sistem.
     */
    public function index(ImportBatchCleaner $cleaner)
    {
        $perintah = [];
        foreach (self::PERINTAH as $kunci => $p) {
            $perintah[] = [
                'kunci' => $kunci,
                'kelompok' => $p['kelompok'],
                'judul' => $p['judul'],
                'perintah' => $p['perintah'],
                'penjelasan' => $p['penjelasan'],
                'risiko' => $p['risiko'],
                'perlu_konfirmasi' => $p['perlu_konfirmasi'] ?? false,
            ];
        }

        return response()->json([
            'status' => [
                'periode_aktif' => RekapCache::periodeAktif(),
                'import_berjalan' => ImportBatch::where('status', 'diproses')->count(),
                'antrean_menunggu' => $this->hitungTabel('jobs'),
                'antrean_gagal' => $this->hitungTabel('failed_jobs'),
                'sisa' => $this->hitungSisa($cleaner),
            ],
            'perintah' => $perintah,
            'hanya_terminal' => self::HANYA_TERMINAL,
        ]);
    }

    /**
     * POST /api/perawatan/{kunci}/jalankan
     */
    public function jalankan(Request $request, string $kunci, ImportBatchCleaner $cleaner)
    {
        $p = self::PERINTAH[$kunci] ?? null;

        abort_if($p === null, 404, 'Perintah tidak dikenal.');

        if (! empty($p['tolak_saat_import']) && ImportBatch::where('status', 'diproses')->exists()) {
            return response()->json([
                'message' => 'Ada import yang sedang diproses. Tunggu sampai selesai, lalu coba lagi.',
            ], 409);
        }

        if (! empty($p['perlu_konfirmasi']) && trim((string) $request->input('konfirmasi')) !== 'HAPUS') {
            return response()->json([
                'message' => 'Ketik HAPUS untuk mengonfirmasi tindakan ini.',
            ], 422);
        }

        @set_time_limit(600);

        try {
            $keluaran = match ($kunci) {
                'cache-rekap' => $this->segarkanCacheRekap(),
                'cache-clear' => $this->artisan('cache:clear'),
                'optimize-clear' => $this->artisan('optimize:clear'),
                'queue-restart' => $this->artisan('queue:restart'),
                'rekap-bekukan' => $this->artisan('rekap:bekukan'),
                'pegawai-snapshot' => $this->artisan('pegawai:snapshot'),
                'pegawai-snapshot-hitung-ulang' => $this->artisan('pegawai:snapshot', ['--hitung-ulang' => true]),
                'bersihkan-sisa' => $this->artisan('import:bersihkan-sisa', ['--force' => true]),
            };
        } catch (\Throwable $e) {
            Log::error("Perawatan: perintah {$kunci} gagal: " . $e->getMessage());

            return response()->json([
                'message' => 'Perintah gagal dijalankan: ' . $e->getMessage(),
            ], 500);
        }

        Log::info("Perawatan: perintah {$kunci} dijalankan oleh user #" . Auth::id());

        return response()->json([
            'message' => $p['judul'] . ' selesai.',
            'keluaran' => $keluaran,
            'status' => [
                'periode_aktif' => RekapCache::periodeAktif(),
                'sisa' => $this->hitungSisa($cleaner),
            ],
        ]);
    }

    private function segarkanCacheRekap(): string
    {
        RekapCache::bump();

        return 'Cache rekap diganti versinya. Rekap akan dihitung ulang saat dibuka.';
    }

    private function artisan(string $perintah, array $opsi = []): string
    {
        $kode = Artisan::call($perintah, $opsi);
        $teks = trim(Artisan::output());

        if ($kode !== 0) {
            throw new \RuntimeException($teks !== '' ? $teks : "Perintah {$perintah} berakhir dengan kode {$kode}.");
        }

        return $teks !== '' ? $teks : 'Selesai.';
    }

    private function hitungTabel(string $tabel): ?int
    {
        try {
            return DB::table($tabel)->count();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function hitungSisa(ImportBatchCleaner $cleaner): ?array
    {
        try {
            return $cleaner->hitungSisa();
        } catch (\Throwable $e) {
            return null;
        }
    }
}