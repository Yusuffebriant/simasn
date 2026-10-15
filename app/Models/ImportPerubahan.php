<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportPerubahan extends Model
{
    protected $table = 'import_perubahan';

    protected $guarded = [];

    public function batch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}