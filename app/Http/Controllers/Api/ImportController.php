<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPegawaiImport;
use App\Exports\PerubahanPegawaiExport;
use App\Models\ImportBatch;
use App\Models\ImportPerubahan;
use App\Models\Pegawai;
use App\Models\User;
use App\Services\ImportBatchCleaner;
use App\Services\ImportPengaman;
use App\Services\PerubahanPegawaiService;
use App\Support\AlasanImport;
use App\Support\KelengkapanPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportBatchResource; 

class ImportController extends Controller
{
    /**
     * POST /api/imports
     * Upload file Excel, buat batch, dispatch job ke queue.
     */
    public function store(StoreImportRequest $request, ImportPengaman $pengaman)
    {
        $periode = $request->input('periode');

        $path = $request->file('file')->store('imports');

        // Pengaman: pratinjau sebelum mengganti. Kalau jumlah pegawai di file
        // turun melewati ambang, upload ditahan sampai admin mengonfirmasi
        // (kirim ulang dengan konfirmasi_penurunan=1).
        if (! $request->boolean('konfirmasi_penurunan')) {
            try {
                $pratinjau = $pengaman->pratinjau(Storage::path($path), $periode);
            } catch (\Throwable $e) {
                Storage::delete($path);
                Log::warning('Pratinjau import gagal membaca file: ' . $e->getMessage());

                return response()->json([
                    'message' => 'File Excel tidak bisa dibaca. Pastikan file tidak rusak dan formatnya benar.',
                ], 422);
            }

            if ($pratinjau['melewati_ambang']) {
                Storage::delete($path);

                return response()->json([
                    'message' => 'Jumlah pegawai di file turun jauh dibanding data sekarang. '
                        . 'Konfirmasi dulu sebelum data lama diganti.',
                    'perlu_konfirmasi' => true,
                    'pratinjau' => $pratinjau,
                ], 409);
            }
        }

        $batch = ImportBatch::create([
            'nama_file' => $request->file('file')->getClientOriginalName(),
            'periode' => $periode,
            'uploaded_by' => Auth::id(),
            'status' => 'diproses',
        ]);

        ProcessPegawaiImport::dispatch($batch, $path);

        return response()->json([
            'message' => 'File diterima, sedang diproses.',
            'batch_id' => $batch->id,
        ], 202);
    }

    /**
     * GET /api/imports/{batch}/kelengkapan
     * Seleksi data: pegawai dari batch ini yang isiannya masih kosong.
     */
    public function kelengkapan(ImportBatch $batch)
    {
        $this->authorizeBatch($batch);

        return response()->json(KelengkapanPegawai::ringkasan($batch->id));
    }

    /**
     * GET /api/imports/{batch}
     * Cek status & progres import.
     */
    public function show(ImportBatch $batch, ImportBatchCleaner $cleaner)
    {
        $this->authorizeBatch($batch);

        return response()->json([
            'id' => $batch->id,
            'nama_file' => $batch->nama_file,
            'periode' => $batch->periode,
            'status' => $batch->status,
            'total_baris' => $batch->total_baris,
            'berhasil' => $batch->berhasil,
            'gagal' => $batch->gagal,
            'jumlah_dihapus' => $batch->jumlah_dihapus,
            'jumlah_dilindungi' => $batch->jumlah_dilindungi,
            'backup_path' => $batch->backup_path,
            // cocok | selisih | tidak_tersedia, beserta rincian (termasuk
            // baris ganda). Dibaca layar hasil import (ImportResult.jsx).
            'rekonsiliasi_status' => $batch->rekonsiliasi_status,
            'rekonsiliasi' => $batch->rekonsiliasi,
            // Dipakai dialog konfirmasi hapus: apa saja yang ikut terhapus.
            'akan_dihapus' => $cleaner->ringkasan($batch),
            // Data yang tersimpan kosong dan tidak ikut rekap terkait.
            'peringatan' => [
                'tanpa_pendidikan' => Pegawai::where('raw_import_id', $batch->id)
                    ->whereNull('pendidikan_id')->count(),
                'tanpa_jenis_kedudukan' => Pegawai::where('raw_import_id', $batch->id)
                    ->whereNull('jenis_kedudukan')->count(),
            ],
        ]);
    }

    /**
     * GET /api/imports/{batch}/perubahan?jenis=&q=&page=
     * Catatan perubahan pegawai dibanding import sebelumnya:
     * ringkasan per jenis + daftar nama (paginated, bisa difilter).
     */
    public function perubahan(Request $request, ImportBatch $batch)
    {
        $this->authorizeBatch($batch);

        $label = PerubahanPegawaiService::LABEL;

        $perJenis = ImportPerubahan::where('import_batch_id', $batch->id)
            ->select('jenis', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('jenis')
            ->pluck('jumlah', 'jenis');

        $ringkasan = [];
        foreach ($label as $kunci => $nama) {
            $ringkasan[] = [
                'jenis' => $kunci,
                'label' => $nama,
                'jumlah' => (int) ($perJenis[$kunci] ?? 0),
            ];
        }

        $pembanding = ImportBatch::where('id', '<', $batch->id)
            ->where('status', 'selesai')
            ->latest('id')
            ->first(['id', 'periode', 'nama_file']);

        $query = ImportPerubahan::where('import_batch_id', $batch->id);

        if ($jenis = $request->query('jenis')) {
            $query->where('jenis', $jenis);
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($w) use ($q) {
                $w->where('nama', 'like', "%{$q}%")->orWhere('nip', 'like', "%{$q}%");
            });
        }

        $daftar = $query->orderBy('jenis')->orderBy('nama')
            ->paginate(50, ['jenis', 'nip', 'nama', 'unit', 'status_kepegawaian', 'dari', 'ke', 'keterangan']);

        $daftar->getCollection()->transform(function ($r) use ($label) {
            $r->jenis_label = $label[$r->jenis] ?? $r->jenis;

            return $r;
        });

        return response()->json([
            // selesai | tanpa_pembanding | gagal | null (import lama / belum ada catatan)
            'status' => $batch->perubahan_status,
            'periode' => $batch->periode,
            'pembanding' => $pembanding,
            'total' => (int) $perJenis->sum(),
            'ringkasan' => $ringkasan,
            'daftar' => $daftar,
        ]);
    }

    /**
     * GET /api/imports/{batch}/perubahan/export
     * Unduh daftar perubahan pegawai (Excel).
     */
    public function perubahanExport(ImportBatch $batch)
    {
        $this->authorizeBatch($batch);

        $nama = 'perubahan-pegawai-' . ($batch->periode ?: 'batch-' . $batch->id) . '.xlsx';

        return Excel::download(new PerubahanPegawaiExport($batch), $nama);
    }

    /**
     * GET /api/imports/{batch}/ganda
     * Baris di file yang saling menimpa (NIP/NIK sama). Setiap baris menyebut
     * siapa yang tertimpa (hilang dari database) dan apakah namanya berbeda
     * (NIK sama, nama berbeda = perlu dicek manual). Dibaca Riwayat Import.
     */
    public function ganda(ImportBatch $batch)
    {
        $this->authorizeBatch($batch);

        $rekon = $batch->rekonsiliasi ?? [];

        // "NIK sama, nama berbeda" di atas, lalu menurut nomor baris.
        $daftar = $rekon['ganda_detail'] ?? [];
        usort($daftar, function ($a, $b) {
            $beda = (int) !empty($b['nik_nama_beda']) <=> (int) !empty($a['nik_nama_beda']);

            return $beda !== 0 ? $beda : ((int) ($a['baris'] ?? 0) <=> (int) ($b['baris'] ?? 0));
        });

        return response()->json([
            'nama_file' => $batch->nama_file,
            'periode' => $batch->periode,
            // false = import lama: nama yang tertimpa belum dicatat. Upload ulang
            // file yang sama supaya daftar ini terisi lengkap.
            'tersedia' => array_key_exists('ganda_nik_beda_nama', $rekon),
            'total_ganda' => (int) ($rekon['ganda_dalam_file'] ?? 0),
            'nik_beda_nama' => (int) ($rekon['ganda_nik_beda_nama'] ?? 0),
            'daftar' => $daftar,
        ]);
    }

    /**
     * GET /api/imports/{batch}/saran-perbaikan
     * Semua baris gagal dikelompokkan per masalah: kolom Excel mana yang
     * perlu diperbaiki, berapa baris, contoh nomor barisnya, dan caranya.
     */
    public function saranPerbaikan(ImportBatch $batch)
    {
        $this->authorizeBatch($batch);

        return response()->json([
            'total_gagal' => (int) $batch->gagal,
            'saran' => AlasanImport::ringkasSaran(
                $batch->errors()->select('import_batch_id', 'baris_ke', 'pesan', 'data_mentah')->orderBy('baris_ke')->cursor()
            ),
        ]);
    }

    /**
     * GET /api/imports/{batch}/errors
     * Daftar baris gagal.
     */
    public function errors(ImportBatch $batch)
    {
        $this->authorizeBatch($batch);

        $halaman = $batch->errors()->select('import_batch_id', 'baris_ke', 'pesan', 'data_mentah')->paginate(50);

        // Tambahkan uraian yang mudah dipahami: penyebab, isian yang kurang, dan saran
        // perbaikan (mis. NIK terpotong karena format angka di Excel). Dihitung saat
        // dibaca, jadi berlaku juga untuk import lama.
        $halaman->getCollection()->transform(function ($baris) {
            try {
                $uraian = AlasanImport::uraikanGagal($baris->pesan, $baris->data_mentah);
            } catch (\Throwable $e) {
                // Penguraian hanya tambahan: kalau gagal, tampilkan pesan asli.
                Log::warning("Import batch {$baris->import_batch_id}: gagal menguraikan baris {$baris->baris_ke}: " . $e->getMessage());
                $uraian = ['penyebab' => $baris->pesan, 'kurang' => [], 'saran' => null, 'data_lama_dipertahankan' => false];
            }

            $baris->setAttribute('penyebab', $uraian['penyebab']);
            $baris->setAttribute('kurang', $uraian['kurang']);
            $baris->setAttribute('saran', $uraian['saran']);
            $baris->setAttribute('data_lama_dipertahankan', $uraian['data_lama_dipertahankan']);

            return $baris;
        });

        return response()->json($halaman);
    }

    /**
     * DELETE /api/imports/{batch}?paksa=1
     * Hapus TOTAL satu import: riwayat, baris gagal, catatan perubahan,
     * data pegawai hasil import ini (+ detail & log aktivitasnya), rekap
     * beku periodenya, file cadangan/upload, dan job antre.
     * Lihat ImportBatchCleaner. Permanen, tidak bisa dibatalkan.
     * File CSV cadangan TIDAK ikut dihapus. Semua import boleh dihapus,
     * termasuk yang sedang aktif (import selesai terakhir).
     *
     * Batch berstatus "diproses":
     * - tanpa ?paksa=1 : ditolak (409) selama belum lewat 1 jam.
     * - dengan ?paksa=1: boleh dihapus (untuk import yang macet / worker
     *   mati), KECUALI job-nya benar-benar sedang dikerjakan worker.
     */
    public function destroy(Request $request, ImportBatch $batch, ImportBatchCleaner $cleaner)
    {
        $this->authorizeBatch($batch);

        if ($batch->status === 'diproses') {
            $macet = $batch->created_at?->lte(now()->subHour());
            $paksa = $request->boolean('paksa');

            if (!$macet && !$paksa) {
                return response()->json([
                    'message' => 'Import ini masih diproses, tunggu sampai selesai sebelum dihapus.',
                    'bisa_paksa' => true,
                ], 409);
            }

            // Job yang sedang jalan masih menulis ke batch ini: jangan dihapus.
            if ($cleaner->jobSedangDikerjakan($batch)) {
                return response()->json([
                    'message' => 'Import ini sedang dikerjakan worker. Hentikan worker '
                        . '(Ctrl+C pada terminal queue:work) atau tunggu beberapa menit, lalu coba lagi.',
                    'bisa_paksa' => false,
                ], 409);
            }
        }

        $hasil = $cleaner->hapusSemua($batch);

        return response()->json([
            'message' => 'Import dan seluruh datanya berhasil dihapus.',
            'dihapus' => $hasil,
        ]);
    }

    /**
     * Otorisasi akses batch import.
     */
    protected function authorizeBatch(ImportBatch $batch): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        abort_if(!$user, 403, 'Anda harus login untuk mengakses ini.');

        // role admin sudah tidak dipakai; super-admin & admin-instansi
        // yang sekarang punya akses penuh ke semua batch import.
        if ($user->hasAnyRole(['super-admin', 'admin-instansi'])) {
            return;
        }

        if ($batch->uploaded_by !== $user->id) {
            abort(403, 'Anda tidak berhak mengakses data import ini.');
        }
    }

    /**
     * GET /api/imports
     * Riwayat semua batch import.
     */
    public function index()
    {
        return ImportBatchResource::collection(
        ImportBatch::with('uploader')->latest()->paginate(15)
        );
    }
}