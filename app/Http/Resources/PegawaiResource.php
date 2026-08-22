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
            'jenis_kelamin' => $this->jenis_kelamin,
            'status_kepegawaian' => $this->status_kepegawaian,
            'golongan' => $this->golonganRuang?->kode,
            'eselon' => $this->eselon?->kode,
            'agama' => $this->agama?->nama,
            'pendidikan' => $this->pendidikan?->jenjang,
            'jabatan' => $this->jabatan,
            'status_aktif' => $this->status_aktif,
        ];
    }
}