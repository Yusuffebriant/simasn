<?php

use App\Http\Controllers\Api\PeriodeController;
use App\Http\Controllers\Api\RekapController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PegawaiController;
use App\Http\Controllers\Api\ReferensiController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\StatistikPejabatStrukturalController;
use App\Http\Controllers\Api\StatistikPejabatFungsionalController;
use App\Http\Controllers\Api\StatistikPnsGolonganController;
use App\Http\Controllers\Api\StatistikPppkGolonganController;
use App\Http\Controllers\Api\StatistikAsnPendidikanController;
use App\Http\Controllers\Api\StatistikPnsPendidikanController;
use App\Http\Controllers\Api\StatistikStafDinasPendidikanController;
use App\Http\Controllers\Api\StatistikStafDinasGolonganController;
use App\Http\Controllers\Api\StatistikPppkPendidikanController;
use App\Http\Controllers\Api\StatistikPejabatStrukturalDinasController;
use App\Http\Controllers\Api\StatistikPejabatFungsionalDinasController;
use App\Http\Controllers\Api\StatistikPensiunanDinasController;
use App\Http\Controllers\Api\StatistikAsnPerangkatDaerahJenisKelaminController;
use App\Http\Controllers\Api\StatistikPenjabatPerangkatDaerahController;
use App\Http\Controllers\Api\StatistikAsnKemantrenPendidikanController;
use App\Http\Controllers\Api\StatistikPnsKemantrenPendidikanController;
use App\Http\Controllers\Api\StatistikPppkKemantrenPendidikanController;
use App\Http\Controllers\Api\StatistikPnsKemantrenGolonganController;
use App\Http\Controllers\Api\StatistikPppkKemantrenGolonganController;
use App\Http\Controllers\Api\StatistikPensiunanPNSController;
use App\Http\Controllers\Api\StatistikPnsKelurahanController;
use App\Http\Controllers\Api\StatistikPppkKelurahanController;
use App\Http\Controllers\Api\StatistikAllController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

// =========================================================
// DASHBOARD (VIEWER) — publik, tidak perlu login.
// Ini satu-satunya data yang boleh dilihat viewer.
// =========================================================

Route::get('/rekap/dashboard', [RekapController::class, 'dashboardJson'])
    ->middleware('throttle:60,1');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

    // =========================================================
    // STATISTIK PEJABAT
    // =========================================================

    // Export Semua Statistik — satu file Excel berisi seluruh tabel di
    // halaman Statistik (satu sheet per tabel). Ditaruh paling atas
    // (bukan di bawah salah satu sub-bagian) karena cakupannya lintas
    // semua sub-bagian statistik.php di bawah ini, sama seperti
    // /rekap/all/export di halaman Admin yang juga tidak terikat satu
    // sub-bagian rekap.
    Route::get('/statistik/all/export', [
        StatistikAllController::class,
        'export'
    ]);

    Route::get('/statistik/pejabat-struktural', [
        StatistikPejabatStrukturalController::class,
        'index'
    ]);

    Route::get('/statistik/pejabat-struktural/export', [
        StatistikPejabatStrukturalController::class,
        'export'
    ]);

    Route::get('/statistik/pejabat-fungsional', [
        StatistikPejabatFungsionalController::class,
        'index'
    ]);

    Route::get('/statistik/pejabat-fungsional/export', [
        StatistikPejabatFungsionalController::class,
        'export'
    ]);

    Route::get('/statistik/pejabat-fungsional-dinas', [
        StatistikPejabatFungsionalDinasController::class,
        'index'
    ]);

    Route::get('/statistik/pensiunan-dinas', [
        StatistikPensiunanDinasController::class,
        'index'
    ]);

    Route::get('/statistik/penjabat-perangkat-daerah', [
        StatistikPenjabatPerangkatDaerahController::class,
        'index'
    ]);

    Route::get('/statistik/penjabat-perangkat-daerah/export', [
        StatistikPenjabatPerangkatDaerahController::class,
        'export'
    ]);

    Route::get('/statistik/asn-kemantren-pendidikan', [
        StatistikAsnKemantrenPendidikanController::class,
        'index'
    ]);

    Route::get('/statistik/asn-kemantren-pendidikan/export', [
        StatistikAsnKemantrenPendidikanController::class,
        'export'
    ]);

    Route::get('/statistik/pns-kemantren-pendidikan', [
        StatistikPnsKemantrenPendidikanController::class,
        'index'
    ]);

    Route::get('/statistik/pns-kemantren-pendidikan/export', [
        StatistikPnsKemantrenPendidikanController::class,
        'export'
    ]);

    Route::get('/statistik/pppk-kemantren-pendidikan', [
        StatistikPppkKemantrenPendidikanController::class,
        'index'
    ]);

    Route::get('/statistik/pppk-kemantren-pendidikan/export', [
        StatistikPppkKemantrenPendidikanController::class,
        'export'
    ]);

    Route::get('/statistik/pns-kemantren-golongan', [
        StatistikPnsKemantrenGolonganController::class,
        'index'
    ]);

    Route::get('/statistik/pns-kemantren-golongan/export', [
        StatistikPnsKemantrenGolonganController::class,
        'export'
    ]);

    Route::get('/statistik/pppk-kemantren-golongan', [
        StatistikPppkKemantrenGolonganController::class,
        'index'
    ]);

    Route::get('/statistik/pppk-kemantren-golongan/export', [
        StatistikPppkKemantrenGolonganController::class,
        'export'
    ]);

    Route::get('/statistik/pns-kelurahan', [
        StatistikPnsKelurahanController::class,
        'index'
    ]);

    Route::get('/statistik/pns-kelurahan/export', [
        StatistikPnsKelurahanController::class,
        'export'
    ]);

    Route::get('/statistik/pppk-kelurahan', [
        StatistikPppkKelurahanController::class,
        'index'
    ]);

    Route::get('/statistik/pppk-kelurahan/export', [
        StatistikPppkKelurahanController::class,
        'export'
    ]);

    // =========================================================
    // STATISTIK ASN BERDASARKAN JENIS DAN PENDIDIKAN
    // =========================================================

    Route::get('/statistik/pns-golongan', [
        StatistikPnsGolonganController::class,
        'index'
    ]);

    Route::get('/statistik/pns-golongan/export', [
        StatistikPnsGolonganController::class,
        'export'
    ]);

    Route::get('/statistik/pppk-golongan', [
        StatistikPppkGolonganController::class,
        'index'
    ]);

    Route::get('/statistik/pppk-golongan/export', [
        StatistikPppkGolonganController::class,
        'export'
    ]);

    Route::get('/statistik/asn-pendidikan', [
        StatistikAsnPendidikanController::class,
        'index'
    ]);

    Route::get('/statistik/asn-pendidikan/export', [
        StatistikAsnPendidikanController::class,
        'export'
    ]);

    Route::get('/statistik/pns-pendidikan', [
        StatistikPnsPendidikanController::class,
        'index'
    ]);

    Route::get('/statistik/pns-pendidikan/export', [
        StatistikPnsPendidikanController::class,
        'export'
    ]);

    Route::get('/statistik/staf-dinas/pendidikan', [
        StatistikStafDinasPendidikanController::class,
        'index'
    ]);

    // Satu tombol Export untuk SELURUH panel "Pegawai Berdasarkan
    // Pendidikan & SKPD" (pendidikan + golongan + eselon + ringkasan),
    // makanya path-nya /staf-dinas/export, bukan /staf-dinas/pendidikan/export.
    Route::get('/statistik/staf-dinas/export', [
        StatistikStafDinasPendidikanController::class,
        'export'
    ]);

    Route::get('/statistik/pppk-pendidikan', [
        StatistikPppkPendidikanController::class,
        'index'
    ]);

    Route::get('/statistik/pppk-pendidikan/export', [
        StatistikPppkPendidikanController::class,
        'export'
    ]);

    Route::get('/statistik/staf-dinas/golongan', [
        StatistikStafDinasGolonganController::class,
        'index'
    ]);

    Route::get('/statistik/pejabat-struktural-dinas', [
        StatistikPejabatStrukturalDinasController::class,
        'index'
    ]);

    Route::get('/statistik/asn-perangkat-daerah-jenis-kelamin', [
        StatistikAsnPerangkatDaerahJenisKelaminController::class,
        'index'
    ]);

    Route::get('/statistik/asn-perangkat-daerah-jenis-kelamin/export', [
        StatistikAsnPerangkatDaerahJenisKelaminController::class,
        'export'
    ]);

    // =========================================================
    // STATISTIK PENSIUN
    // =========================================================

    Route::get('/statistik/pensiunan-pns', [
        StatistikPensiunanPNSController::class,
        'index'
    ]);

    Route::get('/statistik/pensiunan-pns/export', [
        StatistikPensiunanPNSController::class,
        'export'
    ]);


    // =========================================================
    // AUTH — boleh diakses semua user yang sudah login
    // =========================================================

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // =========================================================
    // ADMIN + SETTINGS — role admin SUDAH TIDAK DIPAKAI.
    // Kendali diambil alih oleh super-admin dan admin-instansi.
    // =========================================================

    Route::middleware(['role:super-admin|admin-instansi'])->group(function () {

        Route::get('/periode/aktif', [PeriodeController::class, 'aktif']);

        // ---- IMPORT ----
        Route::get('/imports', [ImportController::class, 'index']);
        Route::post('/imports', [ImportController::class, 'store'])
            ->middleware('throttle:10,1');
        Route::get('/imports/{batch}', [ImportController::class, 'show']);
        Route::get('/imports/{batch}/errors', [ImportController::class, 'errors']);

        // ---- PEGAWAI CRUD ----
        Route::apiResource('pegawai', PegawaiController::class)
            ->only(['index', 'show', 'update', 'destroy']);

        // ---- REFERENSI ----
        Route::get('/referensi/instansi', [ReferensiController::class, 'instansi']);
        Route::get('/referensi/golongan', [ReferensiController::class, 'golonganRuang']);
        Route::get('/referensi/eselon', [ReferensiController::class, 'eselon']);
        Route::get('/referensi/agama', [ReferensiController::class, 'agama']);
        Route::get('/referensi/pendidikan', [ReferensiController::class, 'pendidikan']);

        // ---- REKAP JSON ----
        Route::get('/rekap/agama', [RekapController::class, 'agamaJson']);
        Route::get('/rekap/nakes', [RekapController::class, 'nakesJson']);
        Route::get('/rekap/sd', [RekapController::class, 'sdJson']);
        Route::get('/rekap/smp', [RekapController::class, 'smpJson']);
        Route::get('/rekap/kecamatan', [RekapController::class, 'kecamatanJson']);
        Route::get('/rekap/pendidikan', [RekapController::class, 'pendidikanJson']);
        Route::get('/rekap/jabatan', [RekapController::class, 'jabatanJson']);
        Route::get('/rekap/golongan', [RekapController::class, 'golonganJson']);
        Route::get('/rekap/eselon-golongan-gender', [RekapController::class, 'eselonGolonganGenderJson']);

        // ---- REKAP EXPORT EXCEL ----
        Route::get('/rekap/all/export', [RekapController::class, 'exportAll']);
        Route::get('/rekap/agama/export', [RekapController::class, 'exportAgama']);
        Route::get('/rekap/sd/export', [RekapController::class, 'exportSd']);
        Route::get('/rekap/smp/export', [RekapController::class, 'exportSmp']); 
        Route::get('/rekap/kecamatan/export', [RekapController::class, 'exportKecamatan']);
        Route::get('/rekap/nakes/export', [RekapController::class, 'exportNakes']);
        Route::get('/rekap/pendidikan/export', [RekapController::class, 'exportPendidikan']);
        Route::get('/rekap/golongan/export', [RekapController::class, 'exportGolongan']);
        Route::get('/rekap/jabatan/export', [RekapController::class, 'exportJabatan']);
        Route::get('/rekap/eselon-golongan-gender/export', [RekapController::class, 'exportEselonGolonganGender']);

        // ---- SETTINGS (manajemen akun pengguna) ----
        Route::apiResource('users', UserController::class)->except(['show']);
    });
});