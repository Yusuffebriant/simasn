<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Instansi;
use App\Models\Pegawai;
use App\Models\RekapSnapshotData;
use App\Models\User;
use App\Services\RekapSnapshotService;
use App\Support\RekapCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RekapSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private Instansi $instansi;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->user = User::factory()->create();
        $this->instansi = Instansi::create(['nama' => 'Instansi Uji Snapshot']);
    }

    private function buatPegawai(int $urut, string $status = 'PNS'): void
    {
        Pegawai::create([
            'nip' => '3000010100000' . $urut,
            'nama' => 'Pegawai Snapshot ' . $urut,
            'instansi_id' => $this->instansi->id,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => $status,
            'jabatan' => 'Staff',
            'status_aktif' => 'aktif',
        ]);
    }

    private function buatBatchSelesai(string $periode, int $menitKeDepan = 0): ImportBatch
    {
        $batch = ImportBatch::create([
            'nama_file' => "data-{$periode}.xlsx",
            'periode' => $periode,
            'uploaded_by' => $this->user->id,
            'status' => 'selesai',
        ]);

        // created_at tidak di-fillable; atur langsung supaya urutan jelas.
        $batch->forceFill(['created_at' => now()->addMinutes($menitKeDepan)])->save();

        return $batch;
    }

    private function totalDashboard(string $periode): int
    {
        $response = $this->getJson("/api/rekap/dashboard?periode={$periode}");
        $response->assertStatus(200);

        return (int) $response->json('ringkasan_status.TOTAL');
    }

    public function test_periode_lama_tetap_tampil_setelah_data_berubah(): void
    {
        $this->buatPegawai(1);
        $this->buatPegawai(2);
        $this->buatBatchSelesai('2026-09', 0);

        (new RekapSnapshotService())->bekukan('2026-09');

        // Import periode berikutnya: data berubah jadi 3 pegawai.
        $this->buatPegawai(3);
        $this->buatBatchSelesai('2026-10', 1);
        RekapCache::bump();

        $this->assertSame(2, $this->totalDashboard('2026-09'), 'periode lama harus dari snapshot');
        $this->assertSame(3, $this->totalDashboard('2026-10'), 'periode aktif dihitung langsung');
    }

    public function test_periode_lama_tanpa_snapshot_dihitung_langsung(): void
    {
        $this->buatPegawai(1);
        $this->buatBatchSelesai('2026-10', 0);

        $this->assertSame(1, $this->totalDashboard('2026-07'));
    }

    public function test_bekukan_ulang_periode_sama_menimpa_snapshot(): void
    {
        $this->buatPegawai(1);
        $this->buatBatchSelesai('2026-09', 0);

        $service = new RekapSnapshotService();
        $service->bekukan('2026-09');

        $this->buatPegawai(2);
        $service->bekukan('2026-09');

        $this->buatBatchSelesai('2026-10', 1);
        RekapCache::bump();

        $this->assertSame(2, $this->totalDashboard('2026-09'));
        $this->assertSame(
            1,
            RekapSnapshotData::where('periode', '2026-09')->where('nama', 'dashboard')->count(),
            'tidak boleh ada baris ganda untuk periode+nama yang sama'
        );
    }

    public function test_semua_endpoint_rekap_dibekukan(): void
    {
        $this->buatPegawai(1);
        $this->buatBatchSelesai('2026-09', 0);

        $jumlah = (new RekapSnapshotService())->bekukan('2026-09');

        $this->assertSame($jumlah, RekapSnapshotData::where('periode', '2026-09')->count());
        $this->assertSame(16, $jumlah);
    }

    public function test_daftar_periode_menampilkan_periode_yang_dibekukan(): void
    {
        $this->buatPegawai(1);
        $service = new RekapSnapshotService();
        $service->bekukan('2026-08');
        $service->bekukan('2026-09');

        $daftar = array_column($service->daftarPeriode(), 'periode');

        $this->assertSame(['2026-09', '2026-08'], $daftar);
    }

    public function test_import_selesai_otomatis_membekukan_rekap_periodenya(): void
    {
        // QUEUE_CONNECTION=sync di phpunit.xml: job berjalan langsung.
        $file = UploadedFile::fake()->createWithContent(
            'data_mentah.xlsx',
            file_get_contents(base_path('tests/Fixtures/data_mentah.xlsx'))
        );

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/imports', ['file' => $file, 'periode' => '2026-08'])
            ->assertStatus(202);

        $this->assertSame('selesai', ImportBatch::latest('id')->value('status'));
        $this->assertSame(16, RekapSnapshotData::where('periode', '2026-08')->count());
    }

    public function test_endpoint_riwayat_periode_butuh_login(): void
    {
        $this->getJson('/api/periode/riwayat')->assertStatus(401);
    }

    public function test_command_bekukan_dan_validasi_periode(): void
    {
        $this->buatPegawai(1);

        $this->artisan('rekap:bekukan', ['periode' => '2026-09'])->assertExitCode(0);
        $this->assertSame(16, RekapSnapshotData::where('periode', '2026-09')->count());

        $this->artisan('rekap:bekukan', ['periode' => '2026-13'])->assertExitCode(1);
        $this->artisan('rekap:bekukan', ['periode' => 'abc'])->assertExitCode(1);
    }
}