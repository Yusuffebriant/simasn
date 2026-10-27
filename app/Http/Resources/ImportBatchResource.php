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
            'jumlah_dihapus' => $this->jumlah_dihapus,
            'jumlah_dilindungi' => $this->jumlah_dilindungi,
            // cocok | selisih | tidak_tersedia | null. Dipakai Riwayat Import.
            'rekonsiliasi_status' => $this->rekonsiliasi_status,
            // Baris ber-NIK sama tetapi nama berbeda (pegawai tertimpa/hilang).
            // null = import dibuat sebelum pencatatan ini ada.
            'ganda_nik_beda_nama' => $this->rekonsiliasi['ganda_nik_beda_nama'] ?? null,
            'ganda_dalam_file' => $this->rekonsiliasi['ganda_dalam_file'] ?? 0,
            'diupload_oleh' => $this->uploader?->name,
            'dibuat_pada' => $this->created_at?->format('Y-m-d H:i'),
        ];
    }
}