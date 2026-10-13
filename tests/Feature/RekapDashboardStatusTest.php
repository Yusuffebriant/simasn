<?php

namespace Tests\Feature;

use App\Models\Instansi;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapDashboardStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_menyertakan_ringkasan_status_semua_status(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $instansi = Instansi::create(['nama' => 'Instansi Uji Dashboard']);

        $urut = 0;
        foreach (['PNS', 'PPPK', 'PPPK_PW', 'PPPK_PW', 'LAINNYA'] as $status) {
            $urut++;
            Pegawai::create([
                'nip' => '2000010100000' . $urut,
                'nama' => 'Pegawai Dashboard ' . $urut,
                'instansi_id' => $instansi->id,
                'jenis_kelamin' => 'P',
                'status_kepegawaian' => $status,
                'jabatan' => 'Staff',
                'status_aktif' => 'aktif',
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/rekap/dashboard?periode=2026-10');

        $response->assertStatus(200);
        $response->assertJsonPath('ringkasan_status.PNS', 1);
        $response->assertJsonPath('ringkasan_status.PPPK', 1);
        $response->assertJsonPath('ringkasan_status.PPPK_PW', 2);
        $response->assertJsonPath('ringkasan_status.LAINNYA', 1);
        $response->assertJsonPath('ringkasan_status.TOTAL', 5);
    }
}