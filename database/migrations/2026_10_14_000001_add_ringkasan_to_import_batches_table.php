<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->unsignedInteger('jumlah_dihapus')->default(0);
            $table->unsignedInteger('jumlah_dilindungi')->default(0);
            $table->string('backup_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn(['jumlah_dihapus', 'jumlah_dilindungi', 'backup_path']);
        });
    }
};