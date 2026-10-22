<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu perubahan atribut satu pegawai antara dua periode berurutan. */
class PegawaiPerubahanRiwayat extends Model
{
    protected $table = 'pegawai_perubahan_riwayat';

    protected $guarded = [];

    protected $casts = [
        'perlu_verifikasi' => 'boolean',
        'dihitung_pada' => 'datetime',
    ];
}