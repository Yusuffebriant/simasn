<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            // Nilai resmi dari kolom JENIS_KEDUDUKAN di file mentah SIMPEG:
            // 'FUNGSIONAL', 'PELAKSANA', 'STRUKTURAL', atau NULL (data lama /
            // tidak dikenali). Dipakai langsung oleh RekapService::rekapJabatan()
            // supaya tidak perlu menebak dari teks kolom `jabatan`.
            $table->string('jenis_kedudukan')->nullable()->after('jabatan');
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn('jenis_kedudukan');
        });
    }
};
