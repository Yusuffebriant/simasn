<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alamat_fasilitas', function (Blueprint $table) {
            $table->id();
            $table->enum('jenis', ['nakes']); // siap diperluas kalau ada kategori lain nanti
            $table->string('nama')->unique();
            $table->text('alamat')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alamat_fasilitas');
    }
};