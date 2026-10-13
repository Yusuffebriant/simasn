<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $table = 'import_batches';

    protected $fillable = [
        'nama_file', 'periode', 'uploaded_by',
        'total_baris', 'berhasil', 'gagal',
        'mapping_kolom', 'status',
        'sinkron_status', 'sinkron_jumlah_sisa',
        'sinkron_total_aktif', 'sinkron_catatan',
        'rekonsiliasi_status', 'rekonsiliasi',
    ];

    protected $casts = [
        'mapping_kolom' => 'array',
        'rekonsiliasi' => 'array',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function errors()
    {
        return $this->hasMany(ImportBatchError::class);
    }

    public function pegawai()
    {
        return $this->hasMany(Pegawai::class, 'raw_import_id');
    }
}