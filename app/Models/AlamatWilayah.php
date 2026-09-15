<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlamatWilayah extends Model
{
    protected $table = 'alamat_wilayah';

    protected $fillable = ['jenis', 'kemantren', 'kelurahan', 'alamat'];
}