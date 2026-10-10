<?php

namespace App\Http\Resources;

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
            'golongan' => $this->golonganRuang?->kode,
            'eselon' => $this->eselon?->kode,
            'agama' => $this->agama?->nama,
            'pendidikan' => $this->pendidikan?->jenjang,
            'jabatan' => $this->jabatan,
            'status_aktif' => $this->status_aktif,
            // PNS menyimpan tanggalnya di tanggal_pensiun, selain PNS di
            // tanggal_selesai_kontrak. Khusus PPPK_PW, isi file sumber terbukti
            // tanggal lahir + 58/60 tahun (batas usia pensiun), BUKAN akhir
            // kontrak. Karena itu jenisnya dibedakan agar label di layar benar.
            'tanggal_akhir' => ($this->tanggal_pensiun ?? $this->tanggal_selesai_kontrak)?->format('Y-m-d'),
            'jenis_tanggal_akhir' => match ($this->status_kepegawaian) {
                'PNS', 'PPPK_PW' => 'PENSIUN',
                'PPPK' => 'KONTRAK',
                default => null,
            },
            'status_perkawinan' => $this->whenLoaded('detail', fn () => $this->detail?->status_perkawinan),
        ];
    }
}