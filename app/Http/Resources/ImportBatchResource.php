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
            'sinkron_status' => $this->sinkron_status,
            'sinkron_jumlah_sisa' => $this->sinkron_jumlah_sisa,
            'sinkron_total_aktif' => $this->sinkron_total_aktif,
            'sinkron_catatan' => $this->sinkron_catatan,
            'rekonsiliasi_status' => $this->rekonsiliasi_status,
            'diupload_oleh' => $this->uploader?->name,
            'dibuat_pada' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}