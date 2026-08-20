<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatchError extends Model
{
    protected $table = 'import_batch_errors';

    protected $fillable = ['import_batch_id', 'baris_ke', 'pesan', 'data_mentah'];

    protected $casts = [
        'data_mentah' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}