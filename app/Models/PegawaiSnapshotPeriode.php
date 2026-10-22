<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Metadata snapshot individual satu periode (versi, sumber, audit koreksi). */
class PegawaiSnapshotPeriode extends Model
{
    protected $table = 'pegawai_snapshot_periode';

    protected $guarded = [];

    protected $casts = [
        'riwayat' => 'array',
        'dibuat_pada' => 'datetime',
        'diperbarui_pada' => 'datetime',
    ];

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}