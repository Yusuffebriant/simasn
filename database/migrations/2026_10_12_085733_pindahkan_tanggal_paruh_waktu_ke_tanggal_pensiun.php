<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Urutan di MySQL: assignment dievaluasi dari kiri ke kanan, jadi
        // tanggal_pensiun diisi dulu, baru tanggal_selesai_kontrak dikosongkan.
        // Syarat whereNull('tanggal_pensiun') membuat migration aman dijalankan ulang.
        DB::table('pegawai')
            ->where('status_kepegawaian', 'PPPK_PW')
            ->whereNotNull('tanggal_selesai_kontrak')
            ->whereNull('tanggal_pensiun')
            ->update([
                'tanggal_pensiun' => DB::raw('tanggal_selesai_kontrak'),
                'tanggal_selesai_kontrak' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('pegawai')
            ->where('status_kepegawaian', 'PPPK_PW')
            ->whereNotNull('tanggal_pensiun')
            ->whereNull('tanggal_selesai_kontrak')
            ->update([
                'tanggal_selesai_kontrak' => DB::raw('tanggal_pensiun'),
                'tanggal_pensiun' => null,
            ]);
    }
};