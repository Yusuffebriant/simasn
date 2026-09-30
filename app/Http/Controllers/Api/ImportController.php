<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPegawaiImport;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     * GET /api/imports/{batch}
     * Cek status & progres import.
     */
    public function show(ImportBatch $batch)
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
        ]);
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
     * DELETE /api/imports/{batch}
     * Hapus satu riwayat import beserta catatan baris gagalnya.
     *
     * Data pegawai TIDAK ikut terhapus: kolom pegawai.raw_import_id
     * bertipe nullOnDelete, jadi hanya referensinya yang dikosongkan.
     * Angka rekapitulasi juga tidak berubah.
     */
    public function destroy(ImportBatch $batch)
    {
        $this->authorizeBatch($batch);

        // Batch yang sedang diproses jangan dihapus (job masih menulis ke batch ini).
        // Kalau sudah lebih dari 1 jam masih "diproses" dianggap macet dan boleh dihapus.
        if ($batch->status === 'diproses' && $batch->created_at?->gt(now()->subHour())) {
            return response()->json([
                'message' => 'Import ini masih diproses, tunggu sampai selesai sebelum dihapus.',
            ], 409);
        }

        DB::transaction(function () use ($batch) {
            $batch->errors()->delete();
            $batch->delete();
        });

        return response()->json([
            'message' => 'Riwayat import berhasil dihapus.',
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