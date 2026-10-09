<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            // PPPK paruh waktu + NABAN/Pegawai Titipan.
            $table->enum('status_kepegawaian', ['PNS', 'PPPK', 'PPPK_PW', 'LAINNYA'])->change();

            // NIP boleh kosong (jaga-jaga untuk paruh waktu). Unique index tetap.
            $table->string('nip')->nullable()->change();

            // Kunci cadangan: unik, tapi boleh NULL untuk data lama.
            $table->string('nik', 32)->nullable()->unique()->after('nip');

            // PPPK dan paruh waktu punya akhir kontrak, bukan pensiun.
            $table->date('tanggal_selesai_kontrak')->nullable()->after('tanggal_pensiun');
        });

        Schema::table('pegawai_detail', function (Blueprint $table) {
            $table->string('status_perkawinan', 20)->nullable()->after('email');
        });

        // Backfill NIK dari pegawai_detail untuk data lama.
        // Hanya NIK yang muncul sekali, supaya unique index tidak bentrok.
        DB::statement("
            UPDATE pegawai p
            JOIN (
                SELECT MIN(pegawai_id) AS pegawai_id, nik
                FROM pegawai_detail
                WHERE nik IS NOT NULL AND nik <> ''
                GROUP BY nik
                HAVING COUNT(*) = 1
            ) d ON d.pegawai_id = p.id
            SET p.nik = d.nik
        ");
    }

    public function down(): void
    {
        Schema::table('pegawai_detail', function (Blueprint $table) {
            $table->dropColumn('status_perkawinan');
        });

        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropUnique(['nik']);
            $table->dropColumn(['nik', 'tanggal_selesai_kontrak']);
        });
    }
};