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
use App\Services\PerubahanPegawaiService;
use App\Support\KelengkapanPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportBatchResource; 

class ImportController extends Controller
{
    /**
     * POST /api/imports
     * Upload file Excel, buat batch, dispatch job ke queue.
     */
    public function store(StoreImportRequest $request)
    {
        $path = $request->file('file')->store('imports');

        $batch = ImportBatch::create([
            'nama_file' => $request->file('file')->getClientOriginalName(),
            'periode' => $request->input('periode'),
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
     * GET /api/imports/{batch}/errors
     * Daftar baris gagal.
     */
    public function errors(ImportBatch $batch)
    {
        $this->authorizeBatch($batch);

        return response()->json(
            $batch->errors()->select('baris_ke', 'pesan', 'data_mentah')->paginate(50)
        );
    }

    /**
     * DELETE /api/imports/{batch}?paksa=1
     * Hapus TOTAL satu import: riwayat, baris gagal, catatan perubahan,
     * data pegawai hasil import ini (+ detail & log aktivitasnya), rekap
     * beku periodenya, file cadangan/upload, dan job antre.
     * Lihat ImportBatchCleaner. Permanen, tidak bisa dibatalkan.
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