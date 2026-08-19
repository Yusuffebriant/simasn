<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekap_snapshots', function (Blueprint $table) {
            $table->id();

            $table->string('periode', 7);

            $table->timestamp('dikunci_pada')->nullable();

            $table->foreignId('generated_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('file_path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_snapshots');
    }
};