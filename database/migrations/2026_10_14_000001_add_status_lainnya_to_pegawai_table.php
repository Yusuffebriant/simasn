<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            // Nama asli status untuk pegawai berstatus LAINNYA
            // (mis. NABAN, Pegawai Titipan). Kosong untuk status lain.
            $table->string('status_lainnya', 50)->nullable()->after('status_kepegawaian');
        });
    }

    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn('status_lainnya');
        });
    }
};