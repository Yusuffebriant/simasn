<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RekapSnapshot extends Model
{
    protected $table = 'rekap_snapshots';

    protected $fillable = ['periode', 'dikunci_pada', 'generated_by', 'file_path'];

    protected $casts = [
        'dikunci_pada' => 'datetime',
    ];

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}