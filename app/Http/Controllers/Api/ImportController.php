<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPegawaiImport;
use App\Models\ImportBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImportController extends Controller
{
    /**
     * POST /api/imports
     * Upload file Excel, buat batch, dispatch job ke queue.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
            'periode' => ['required', 'date_format:Y-m'],
        ]);

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
     * Cek status & progres import (di-poll dari frontend).
     */
    public function show(ImportBatch $batch)
    {
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
     * Daftar baris gagal untuk ditinjau admin.
     */
    public function errors(ImportBatch $batch)
    {
        return response()->json(
            $batch->errors()->select('baris_ke', 'pesan', 'data_mentah')->get()
        );
    }

    /**
     * GET /api/imports
     * Riwayat semua batch import (untuk halaman histori).
     */
    public function index()
    {
        return response()->json(
            ImportBatch::latest()->paginate(15)
        );
    }
}