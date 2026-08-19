<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pendidikan extends Model
{
    protected $table = 'pendidikan';

    protected $fillable = ['jenjang', 'urutan'];

    public function pegawai()
    {
        return $this->hasMany(Pegawai::class);
    }
}