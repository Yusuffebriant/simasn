<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            // Hasil sinkron "pegawai sisa" setelah import:
            // dinonaktifkan | dilewati | tidak_ada_sisa
            $table->string('sinkron_status', 20)->nullable()->after('status');
            $table->unsignedInteger('sinkron_jumlah_sisa')->nullable()->after('sinkron_status');
            $table->unsignedInteger('sinkron_total_aktif')->nullable()->after('sinkron_jumlah_sisa');
            $table->string('sinkron_catatan')->nullable()->after('sinkron_total_aktif');
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn([
                'sinkron_status',
                'sinkron_jumlah_sisa',
                'sinkron_total_aktif',
                'sinkron_catatan',
            ]);
        });
    }
};