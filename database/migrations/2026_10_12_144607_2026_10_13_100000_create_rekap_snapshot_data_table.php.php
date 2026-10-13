<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Isi rekap yang "dibekukan" per periode.
     *
     * Satu baris = hasil satu endpoint rekap (mis. 'dashboard', 'agama')
     * untuk satu periode, disimpan sebagai JSON. Tabel lama
     * rekap_snapshots tidak dipakai/diubah.
     */
    public function up(): void
    {
        Schema::create('rekap_snapshot_data', function (Blueprint $table) {
            $table->id();
            $table->string('periode', 7);
            $table->string('nama', 60);
            $table->longText('payload');
            $table->timestamp('dibekukan_pada')->nullable();
            $table->timestamps();

            $table->unique(['periode', 'nama']);
            $table->index('periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_snapshot_data');
    }
};