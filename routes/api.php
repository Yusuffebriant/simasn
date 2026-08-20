<?php
use App\Http\Controllers\Api\RekapController;
use App\Http\Controllers\Api\ImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/imports', [ImportController::class, 'index']);
    Route::post('/imports', [ImportController::class, 'store']);
    Route::get('/imports/{batch}', [ImportController::class, 'show']);
    Route::get('/imports/{batch}/errors', [ImportController::class, 'errors']);
    Route::get('/rekap/agama/export', [RekapController::class, 'exportAgama']);
});