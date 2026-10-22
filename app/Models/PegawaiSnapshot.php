<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Nilai atribut satu pegawai pada satu periode (salinan, tidak ikut berubah). */
class PegawaiSnapshot extends Model
{
    protected $table = 'pegawai_snapshots';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'dibuat_pada' => 'datetime',
    ];
}