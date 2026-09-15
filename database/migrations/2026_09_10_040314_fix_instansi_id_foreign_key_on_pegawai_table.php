<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FIX: instansi_id di tabel pegawai sebelumnya cascadeOnDelete().
     * Artinya kalau 1 baris instansi dihapus, SEMUA pegawai di instansi
     * itu ikut terhapus permanen di level database (bukan soft delete,
     * bypass Eloquent SoftDeletes sama sekali).
     *
     * Ini tidak konsisten dengan agama_id & pendidikan_id yang sudah
     * lebih dulu diubah ke nullOnDelete() dengan alasan yang sama
     * (lihat migration add_constraints_and_indexes_to_reference_tables).
     *
     * instansi_id tidak bisa dibuat nullOnDelete() karena kolomnya
     * NOT NULL (instansi adalah data wajib untuk pegawai). Solusi yang
     * tepat adalah restrictOnDelete(): mencegah instansi dihapus selama
     * masih ada pegawai yang terhubung ke instansi tersebut. Kalau
     * memang instansi itu sudah tidak aktif, gunakan mekanisme
     * deactivate/nonaktifkan pada data instansi, bukan hapus baris.
     */
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropForeign(['instansi_id']);
        });

        Schema::table('pegawai', function (Blueprint $table) {
            $table->foreign('instansi_id')
                ->references('id')->on('instansi')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropForeign(['instansi_id']);
        });

        Schema::table('pegawai', function (Blueprint $table) {
            $table->foreign('instansi_id')
                ->references('id')->on('instansi')
                ->cascadeOnDelete();
        });
    }
};