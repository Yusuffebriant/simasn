<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('golongan_ruang', function (Blueprint $table) {
            $table->id();
            $table->string('kode');
            $table->enum('kelompok', ['PNS', 'PPPK']);
            $table->integer('urutan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('golongan_ruang');
    }
};