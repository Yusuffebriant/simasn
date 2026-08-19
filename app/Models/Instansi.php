<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Instansi extends Model
{
    protected $table = 'instansi';

    protected $fillable = ['nama', 'kode'];

    public function pegawai()
    {
        return $this->hasMany(Pegawai::class);
    }
}