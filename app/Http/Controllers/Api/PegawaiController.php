<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PegawaiResource;
use App\Models\Pegawai;
use Illuminate\Http\Request;

class PegawaiController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status_kepegawaian' => ['nullable', 'in:PNS,PPPK,PPPK_PW,LAINNYA'],
            'status_perkawinan' => ['nullable', 'in:KAWIN,BELUM_KAWIN,CERAI_HIDUP,CERAI_MATI,JANDA,DUDA,KOSONG'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // 'detail' dimuat agar status perkawinan ikut tampil tanpa N+1.
        $query = Pegawai::with(['instansi', 'golonganRuang', 'eselon', 'agama', 'pendidikan', 'detail'])
            ->where('status_aktif', 'aktif');

        if ($request->filled('instansi_id')) {
            $query->where('instansi_id', $request->instansi_id);
        }

        if ($request->filled('status_kepegawaian')) {
            $query->where('status_kepegawaian', $request->status_kepegawaian);
        }

        // KOSONG = pegawai yang status perkawinannya belum terisi
        // (tidak punya baris detail, atau kolomnya NULL).
        if ($request->filled('status_perkawinan')) {
            if ($request->status_perkawinan === 'KOSONG') {
                $query->where(function ($q) {
                    $q->whereDoesntHave('detail')
                      ->orWhereHas('detail', fn ($d) => $d->whereNull('status_perkawinan'));
                });
            } else {
                $query->whereHas('detail', fn ($d) => $d->where('status_perkawinan', $request->status_perkawinan));
            }
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama', 'like', "%{$request->search}%")
                  ->orWhere('nip', 'like', "%{$request->search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 20);

        return PegawaiResource::collection(
            $query->orderBy('nama')->paginate($perPage)
        );
    }

    public function show(Pegawai $pegawai)
    {
        return new PegawaiResource(
            $pegawai->load(['instansi', 'golonganRuang', 'eselon', 'agama', 'pendidikan', 'detail'])
        );
    }

    public function update(Request $request, Pegawai $pegawai)
    {
        $validated = $request->validate([
            'nama' => ['sometimes', 'string', 'max:255'],
            'jabatan' => ['sometimes', 'nullable', 'string', 'max:255'],
            'jenis_kelamin' => ['sometimes', 'in:L,P'],
            'status_kepegawaian' => ['sometimes', 'in:PNS,PPPK,PPPK_PW,LAINNYA'],
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