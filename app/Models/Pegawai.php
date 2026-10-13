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
    'nip', 'nik', 'nama', 'instansi_id', 'unit', 'sub_unit',
    'jenis_kelamin', 'status_kepegawaian', 'status_lainnya',
    'golongan_ruang_id', 'eselon_id', 'agama_id', 'pendidikan_id',
    'jabatan', 'jenis_kedudukan', 'tanggal_lahir', 'tmt_pangkat',
    'tanggal_pensiun', 'tanggal_selesai_kontrak',
    'status_aktif', 'raw_import_id',
];

protected $casts = [
    'tanggal_lahir' => 'date',
    'tmt_pangkat' => 'date',
    'tanggal_pensiun' => 'date',
    'tanggal_selesai_kontrak' => 'date',
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
    
    /** PNS + PPPK. Dipakai semua laporan resmi yang sudah terverifikasi. */
public function scopeAsnPenuh($query)
{
    return $query->whereIn('pegawai.status_kepegawaian', ['PNS', 'PPPK']);
}

/** Semua status aktif (PNS, PPPK, PPPK Paruh Waktu, Lainnya). Dipakai khusus Dashboard Total. */
public function scopeTotalPegawai($query)
{
    return $query->whereIn('pegawai.status_kepegawaian', ['PNS', 'PPPK', 'PPPK_PW', 'LAINNYA']);
}

public function scopeParuhWaktu($query)
{
    return $query->where('pegawai.status_kepegawaian', 'PPPK_PW');
}

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nip', 'nama', 'golongan_ruang_id', 'eselon_id', 'status_aktif'])
            ->logOnlyDirty();
    }
}