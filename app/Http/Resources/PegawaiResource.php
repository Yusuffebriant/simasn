<?php

namespace App\Http\Resources;

use App\Support\KelengkapanPegawai;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PegawaiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nip' => $this->nip,
            'nama' => $this->nama,
            'instansi' => $this->instansi?->nama,
            'unit' => $this->unit,
            'sub_unit' => $this->sub_unit,
            'instansi_id' => $this->instansi_id,
            'jenis_kelamin' => $this->jenis_kelamin,
            'status_kepegawaian' => $this->status_kepegawaian,
            'golongan_ruang_id' => $this->golongan_ruang_id,
            'agama_id' => $this->agama_id,
            'pendidikan_id' => $this->pendidikan_id,
            'jenis_kedudukan' => $this->jenis_kedudukan,
            'tanggal_lahir' => $this->tanggal_lahir?->format('Y-m-d'),
            'golongan' => $this->golonganRuang?->kode,
            'eselon_id' => $this->eselon_id,
            'eselon' => $this->eselon?->kode,
            'agama' => $this->agama?->nama,
            'pendidikan' => $this->pendidikan?->jenjang,
            'jabatan' => $this->jabatan,
            'status_aktif' => $this->status_aktif,
            // PNS dan PPPK_PW menyimpan tanggalnya di tanggal_pensiun (batas usia
            // pensiun), PPPK di tanggal_selesai_kontrak. Data kontrak asli
            // PPPK_PW belum ada di file sumber, jadi tanggal_selesai_kontrak-nya
            // kosong.
            'tanggal_akhir' => ($this->tanggal_pensiun ?? $this->tanggal_selesai_kontrak)?->format('Y-m-d'),
            'jenis_tanggal_akhir' => match ($this->status_kepegawaian) {
                'PNS', 'PPPK_PW' => 'PENSIUN',
                'PPPK' => 'KONTRAK',
                default => null,
            },
            'status_perkawinan' => $this->whenLoaded('detail', fn () => $this->detail?->status_perkawinan),
            // Isian yang masih kosong (lihat App\Support\KelengkapanPegawai).
            'kosong' => $this->whenLoaded('detail', fn () => KelengkapanPegawai::kosong($this->resource)),
        ];
    }
}