<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPegawaiImport;
use App\Models\ImportBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreImportRequest;

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
     * Otorisasi akses batch import.
     */
    protected function authorizeBatch(ImportBatch $batch): void
    {
        $user = Auth::user();

        if ($user->hasRole('super-admin')) {
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
        return response()->json(
            ImportBatch::latest()->paginate(15)
        );
    }
}