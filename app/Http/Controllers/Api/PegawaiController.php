<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use Illuminate\Http\Request;

class PegawaiController extends Controller
{
    public function index(Request $request)
    {
        $query = Pegawai::with(['instansi', 'golonganRuang', 'eselon', 'agama', 'pendidikan'])
            ->where('status_aktif', 'aktif');

        if ($request->filled('instansi_id')) {
            $query->where('instansi_id', $request->instansi_id);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama', 'like', "%{$request->search}%")
                  ->orWhere('nip', 'like', "%{$request->search}%");
            });
        }

        return response()->json($query->paginate(20));
    }

    public function show(Pegawai $pegawai)
    {
        return response()->json(
            $pegawai->load(['instansi', 'golonganRuang', 'eselon', 'agama', 'pendidikan', 'detail'])
        );
    }

    public function update(Request $request, Pegawai $pegawai)
    {
        $validated = $request->validate([
            'nama' => ['sometimes', 'string', 'max:255'],
            'jabatan' => ['sometimes', 'nullable', 'string', 'max:255'],
            'jenis_kelamin' => ['sometimes', 'in:L,P'],
            'status_kepegawaian' => ['sometimes', 'in:PNS,PPPK'],
            'instansi_id' => ['sometimes', 'exists:instansi,id'],
            'golongan_ruang_id' => ['sometimes', 'nullable', 'exists:golongan_ruang,id'],
            'eselon_id' => ['sometimes', 'exists:eselon,id'],
            'agama_id' => ['sometimes', 'nullable', 'exists:agama,id'],
            'pendidikan_id' => ['sometimes', 'nullable', 'exists:pendidikan,id'],
            'status_aktif' => ['sometimes', 'in:aktif,pensiun,mutasi_keluar,meninggal'],
        ]);

        $pegawai->update($validated);

        return response()->json($pegawai->fresh(['instansi', 'golonganRuang', 'eselon']));
    }

    public function destroy(Pegawai $pegawai)
    {
        $pegawai->delete(); // soft delete, sesuai desain awal (jangan hard-delete data kepegawaian)

        return response()->json(['message' => 'Data pegawai berhasil dihapus (soft delete).']);
    }
}