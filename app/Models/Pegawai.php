<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;


class Pegawai extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'pegawai';

    protected $fillable = [
        'nip', 'nama', 'instansi_id', 'unit', 'sub_unit',
        'jenis_kelamin', 'status_kepegawaian',
        'golongan_ruang_id', 'eselon_id', 'agama_id', 'pendidikan_id',
        'jabatan', 'jenis_kedudukan', 'tanggal_lahir', 'tmt_pangkat', 'tanggal_pensiun',
        'status_aktif', 'raw_import_id',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tmt_pangkat' => 'date',
        'tanggal_pensiun' => 'date',
    ];

    public function instansi()
    {
        return $this->belongsTo(Instansi::class);
    }

    public function golonganRuang()
    {
        return $this->belongsTo(GolonganRuang::class);
    }

    public function eselon()
    {
        return $this->belongsTo(Eselon::class);
    }

    public function agama()
    {
        return $this->belongsTo(Agama::class);
    }

    public function pendidikan()
    {
        return $this->belongsTo(Pendidikan::class);
    }

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class, 'raw_import_id');
    }

    public function detail()
    {
        return $this->hasOne(PegawaiDetail::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nip', 'nama', 'golongan_ruang_id', 'eselon_id', 'status_aktif'])
            ->logOnlyDirty();
    }
}