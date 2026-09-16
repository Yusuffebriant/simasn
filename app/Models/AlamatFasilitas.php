<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlamatFasilitas extends Model
{
    protected $table = 'alamat_fasilitas';

    protected $fillable = ['jenis', 'nama', 'alamat'];
}