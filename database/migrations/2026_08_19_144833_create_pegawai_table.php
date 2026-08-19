<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pegawai', function (Blueprint $table) {
            $table->id();

            $table->string('nip')->unique();

            $table->string('nama');

            $table->foreignId('instansi_id')
                ->constrained('instansi')
                ->cascadeOnDelete();

            $table->string('unit')->nullable();

            $table->string('sub_unit')->nullable();

            $table->enum('jenis_kelamin', ['L', 'P']);

            $table->enum('status_kepegawaian', ['PNS', 'PPPK']);

            $table->foreignId('golongan_ruang_id')
                ->nullable()
                ->constrained('golongan_ruang')
                ->nullOnDelete();

            $table->foreignId('eselon_id')
                ->nullable()
                ->constrained('eselon')
                ->nullOnDelete();

            $table->foreignId('agama_id')
                ->constrained('agama')
                ->cascadeOnDelete();

            $table->foreignId('pendidikan_id')
                ->constrained('pendidikan')
                ->cascadeOnDelete();

            $table->string('jabatan');

            $table->date('tanggal_lahir')->nullable();

            $table->date('tmt_pangkat')->nullable();

            $table->date('tanggal_pensiun')->nullable();

            $table->enum('status_aktif', [
                'aktif',
                'pensiun',
                'mutasi_keluar',
                'meninggal'
            ])->default('aktif');

            $table->foreignId('raw_import_id')
                ->nullable()
                ->constrained('import_batches')
                ->nullOnDelete();

            $table->softDeletes();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pegawai');
    }
};