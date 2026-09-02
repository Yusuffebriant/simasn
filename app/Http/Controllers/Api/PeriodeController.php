<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;

class PeriodeController extends Controller
{
        public function aktif()
        {
            $periode = ImportBatch::query()
                ->where('status', 'selesai')
                ->latest()
                ->value('periode');

            return response()->json(['periode' => $periode]);
        }
}
