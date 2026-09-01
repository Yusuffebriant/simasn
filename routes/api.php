<?php

use App\Http\Controllers\Api\RekapController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PegawaiController;
use App\Http\Controllers\Api\ReferensiController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

    // =========================================================
    // AUTH
    // =========================================================

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);


    // =========================================================
    // IMPORT
    // =========================================================

    Route::get('/imports', [ImportController::class, 'index']);

    Route::post('/imports', [ImportController::class, 'store'])
        ->middleware('throttle:10,1');

    Route::get('/imports/{batch}', [ImportController::class, 'show']);
    Route::get('/imports/{batch}/errors', [ImportController::class, 'errors']);


    // =========================================================
    // PEGAWAI CRUD
    // =========================================================

    Route::apiResource('pegawai', PegawaiController::class);


    // =========================================================
    // REFERENSI
    // =========================================================

    Route::get('/referensi/instansi', [
        ReferensiController::class,
        'instansi'
    ]);

    Route::get('/referensi/golongan', [
        ReferensiController::class,
        'golonganRuang'
    ]);

    Route::get('/referensi/eselon', [
        ReferensiController::class,
        'eselon'
    ]);

    Route::get('/referensi/agama', [
        ReferensiController::class,
        'agama'
    ]);

    Route::get('/referensi/pendidikan', [
        ReferensiController::class,
        'pendidikan'
    ]);


    // =========================================================
    // REKAP JSON
    // =========================================================

    Route::get('/rekap/agama', [
        RekapController::class,
        'agamaJson'
    ]);

    Route::get('/rekap/pendidikan', [
        RekapController::class,
        'pendidikanJson'
    ]);

    Route::get('/rekap/jabatan', [
        RekapController::class,
        'jabatanJson'
    ]);

    Route::get('/rekap/golongan', [
        RekapController::class,
        'golonganJson'
    ]);

<<<<<<< HEAD
=======
    Route::get('/rekap/eselon-golongan-gender', [
        RekapController::class,
        'eselonGolonganGenderJson'
    ]);

>>>>>>> origin/frontend/admin-pagev2

    // =========================================================
    // REKAP EXPORT EXCEL
    // =========================================================

    Route::get('/rekap/all/export', [
        RekapController::class,
        'exportAll'
    ]);

    Route::get('/rekap/agama/export', [
        RekapController::class,
        'exportAgama'
    ]);

    Route::get('/rekap/pendidikan/export', [
        RekapController::class,
        'exportPendidikan'
    ]);

    Route::get('/rekap/golongan/export', [
        RekapController::class,
        'exportGolongan'
    ]);

    Route::get('/rekap/jabatan/export', [
        RekapController::class,
        'exportJabatan'
    ]);

    Route::get('/rekap/eselon-golongan-gender/export', [
        RekapController::class,
        'exportEselonGolonganGender'
    ]);
});
