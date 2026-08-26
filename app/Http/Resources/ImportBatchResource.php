<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama_file' => $this->nama_file,
            'periode' => $this->periode,
            'status' => $this->status,
            'total_baris' => $this->total_baris,
            'berhasil' => $this->berhasil,
            'gagal' => $this->gagal,
            'diupload_oleh' => $this->uploader?->name,
            'dibuat_pada' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}