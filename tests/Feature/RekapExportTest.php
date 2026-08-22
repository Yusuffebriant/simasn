<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_agama_mengembalikan_file_excel(): void
    {
        $this->seed(); // butuh data referensi (agama, dll)
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->get('/api/rekap/agama/export?periode=2026-08');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}