<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiDetail extends Model
{
    protected $table = 'pegawai_detail';

    protected $fillable = ['pegawai_id', 'nik', 'alamat', 'hp', 'email'];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }
}