<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            // cocok | selisih | tidak_tersedia
            $table->string('rekonsiliasi_status', 20)->nullable()->after('sinkron_catatan');
            $table->json('rekonsiliasi')->nullable()->after('rekonsiliasi_status');
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn(['rekonsiliasi_status', 'rekonsiliasi']);
        });
    }
};