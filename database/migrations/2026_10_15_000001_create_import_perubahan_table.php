<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan perubahan pegawai antar import (mis. Februari -> Maret).
     *
     * Satu baris = satu perubahan pada satu pegawai (pegawai yang sama bisa
     * punya beberapa baris: naik pangkat + pindah unit). Nama, NIP, dan unit
     * disalin ke baris ini supaya catatan tetap terbaca walaupun pegawainya
     * sudah tidak ada lagi di tabel pegawai (pensiun / keluar).
     */
    public function up(): void
    {
        Schema::create('import_perubahan', function (Blueprint $table) {
            $table->id();

            $table->foreignId('import_batch_id')
                ->constrained('import_batches')
                ->cascadeOnDelete();

            $table->string('periode', 7)->nullable();
            $table->string('jenis', 30);

            // Bukan foreign key: pegawai yang keluar sudah dihapus dari tabel pegawai.
            $table->unsignedBigInteger('pegawai_id')->nullable();
            $table->string('nip')->nullable();
            $table->string('nama');
            $table->string('unit')->nullable();
            $table->string('status_kepegawaian', 20)->nullable();

            $table->string('dari')->nullable();
            $table->string('ke')->nullable();
            $table->string('keterangan')->nullable();

            $table->timestamps();

            $table->index(['import_batch_id', 'jenis']);
            $table->index('periode');
        });

        Schema::table('import_batches', function (Blueprint $table) {
            // selesai | tanpa_pembanding | gagal
            $table->string('perubahan_status', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn('perubahan_status');
        });

        Schema::dropIfExists('import_perubahan');
    }
};