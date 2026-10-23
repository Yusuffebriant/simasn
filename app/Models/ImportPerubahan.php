<?php

namespace App\Models;

use App\Support\PerubahanAtribut;
use Illuminate\Database\Eloquent\Model;

class ImportPerubahan extends Model
{
    protected $table = 'import_perubahan';

    protected $guarded = [];

    /** Nama status untuk tampilan (PPPK_PW -> PPPK Paruh Waktu). */
    protected $appends = ['status_label'];

    public function getStatusLabelAttribute(): ?string
    {
        return PerubahanAtribut::labelStatus($this->attributes['status_kepegawaian'] ?? null);
    }

    /**
     * Pada perubahan 'ganti_status', kolom dari/ke berisi kode status
     * (mis. "PPPK_PW"). Dirapikan saat dibaca, jadi catatan lama juga ikut
     * tampil sebagai "PPPK Paruh Waktu" tanpa mengubah isi database.
     */
    public function getDariAttribute($nilai): ?string
    {
        return $this->rapikanStatus($nilai);
    }

    public function getKeAttribute($nilai): ?string
    {
        return $this->rapikanStatus($nilai);
    }

    private function rapikanStatus(?string $teks): ?string
    {
        if ($teks === null || ($this->attributes['jenis'] ?? null) !== 'ganti_status') {
            return $teks;
        }

        return str_replace('PPPK_PW', PerubahanAtribut::STATUS_LABEL['PPPK_PW'], $teks);
    }

    public function batch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}