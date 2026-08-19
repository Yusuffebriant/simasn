<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GolonganRuang extends Model
{
    protected $table = 'golongan_ruang';

    protected $fillable = ['kode', 'kelompok', 'urutan'];

    public function pegawai()
    {
        return $this->hasMany(Pegawai::class);
    }
}