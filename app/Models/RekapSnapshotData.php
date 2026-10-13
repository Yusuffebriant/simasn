<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RekapSnapshotData extends Model
{
    protected $table = 'rekap_snapshot_data';

    protected $fillable = ['periode', 'nama', 'payload', 'dibekukan_pada'];

    protected $casts = [
        'payload' => 'array',
        'dibekukan_pada' => 'datetime',
    ];
}