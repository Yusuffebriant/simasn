<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PegawaiImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_valid_membuat_batch_dan_dispatch_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent(
            'data_mentah.xlsx',
            file_get_contents(base_path('tests/Fixtures/data_mentah.xlsx'))
        );

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/imports', [
            'file' => $file,
            'periode' => '2026-08',
        ]);

        $response->assertStatus(202);
        Queue::assertPushed(\App\Jobs\ProcessPegawaiImport::class);
    }

    public function test_upload_file_bukan_excel_ditolak(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('dokumen.pdf', 100);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/imports', [
            'file' => $file,
            'periode' => '2026-08',
        ]);

        $response->assertStatus(422);
    }
}