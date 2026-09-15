<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alamat_wilayah', function (Blueprint $table) {
            $table->id();
            $table->enum('jenis', ['kemantren', 'kelurahan']);
            $table->string('kemantren'); // nama kemantren, selalu diisi
            $table->string('kelurahan')->nullable(); // null kalau jenis='kemantren'
            $table->text('alamat')->nullable();
            $table->timestamps();

            $table->unique(['jenis', 'kemantren', 'kelurahan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alamat_wilayah');
    }
};