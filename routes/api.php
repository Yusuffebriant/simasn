<?php

use App\Http\Controllers\Api\RekapController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    // Import
    Route::get('/imports', [ImportController::class, 'index']);
    Route::post('/imports', [ImportController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/imports/{batch}', [ImportController::class, 'show']);
    Route::get('/imports/{batch}/errors', [ImportController::class, 'errors']);

    // Rekap
    Route::get('/rekap/agama/export', [RekapController::class, 'exportAgama']);
    Route::get('/rekap/pendidikan/export', [RekapController::class, 'exportPendidikan']);
    Route::get('/rekap/golongan/export', [RekapController::class, 'exportGolongan']);
    Route::get('/rekap/jabatan/export', [RekapController::class, 'exportJabatan']);
    Route::get('/rekap/eselon-golongan-gender/export', [RekapController::class, 'exportEselonGolonganGender']);
});