<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agama', function (Blueprint $table) {
            $table->unique('nama');
        });
        Schema::table('eselon', function (Blueprint $table) {
            $table->unique('kode');
        });
        Schema::table('pendidikan', function (Blueprint $table) {
            $table->unique('jenjang');
        });
        Schema::table('golongan_ruang', function (Blueprint $table) {
            $table->unique(['kode', 'kelompok']);
        });

        // Index untuk kolom yang sering dipakai di WHERE/JOIN RekapService
        Schema::table('pegawai', function (Blueprint $table) {
            $table->index('status_aktif');
            $table->index('instansi_id');
        });

        // Ganti cascadeOnDelete -> nullOnDelete untuk agama_id & pendidikan_id.
        // Kalau admin hapus 1 record agama/pendidikan, pegawai TIDAK BOLEH ikut
        // terhapus (cascade) — cukup field itu jadi null.
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropForeign(['agama_id']);
            $table->dropForeign(['pendidikan_id']);
        });
        Schema::table('pegawai', function (Blueprint $table) {
            $table->foreign('agama_id')->references('id')->on('agama')->nullOnDelete();
            $table->foreign('pendidikan_id')->references('id')->on('pendidikan')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agama', fn (Blueprint $table) => $table->dropUnique(['nama']));
        Schema::table('eselon', fn (Blueprint $table) => $table->dropUnique(['kode']));
        Schema::table('pendidikan', fn (Blueprint $table) => $table->dropUnique(['jenjang']));
        Schema::table('golongan_ruang', fn (Blueprint $table) => $table->dropUnique(['kode', 'kelompok']));

        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropIndex(['status_aktif']);
            $table->dropIndex(['instansi_id']);
            $table->dropForeign(['agama_id']);
            $table->dropForeign(['pendidikan_id']);
        });
        Schema::table('pegawai', function (Blueprint $table) {
            $table->foreign('agama_id')->references('id')->on('agama')->cascadeOnDelete();
            $table->foreign('pendidikan_id')->references('id')->on('pendidikan')->cascadeOnDelete();
        });
    }
};