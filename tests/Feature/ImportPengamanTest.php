<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Instansi;
use App\Models\Pegawai;
use App\Models\User;
use App\Services\PegawaiNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportPengamanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Pembuatan User memicu observer yang memberi role super-admin.
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->user = User::factory()->create();
    }

    private function file(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'data_mentah.xlsx',
            file_get_contents(base_path('tests/Fixtures/data_mentah.xlsx'))
        );
    }

    private function batchSelesai(string $periode): ImportBatch
    {
        return ImportBatch::create([
            'nama_file' => 'lama.xlsx',
            'periode' => $periode,
            'uploaded_by' => $this->user->id,
            'status' => 'selesai',
        ]);
    }

    private function buatPegawai(int $jumlah): void
    {
        $instansi = Instansi::create(['nama' => 'Instansi Uji Pengaman']);

        for ($i = 1; $i <= $jumlah; $i++) {
            Pegawai::unguarded(fn () => Pegawai::create([
                'nip' => sprintf('5%015d', $i),
                'nama' => "Pegawai {$i}",
                'instansi_id' => $instansi->id,
                'jenis_kelamin' => 'L',
                'status_kepegawaian' => 'LAINNYA',
                'jabatan' => 'Staff',
                'status_aktif' => 'aktif',
                'tanggal_lahir' => '1990-01-01',
            ]));
        }
    }

    public function test_periode_lebih_lama_ditolak(): void
    {
        Queue::fake();
        $this->batchSelesai('2026-10');

        $this->actingAs($this->user, 'sanctum')->postJson('/api/imports', [
            'file' => $this->file(),
            'periode' => '2026-09',
        ])->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_periode_sama_dengan_periode_aktif_diterima(): void
    {
        Queue::fake();
        $this->batchSelesai('2026-10');

        $this->actingAs($this->user, 'sanctum')->postJson('/api/imports', [
            'file' => $this->file(),
            'periode' => '2026-10',
        ])->assertStatus(202);
    }

    public function test_penurunan_melewati_ambang_ditahan_sampai_dikonfirmasi(): void
    {
        Queue::fake();
        // Fixture hanya berisi beberapa baris; 100 pegawai sekarang => turun > 90%.
        $this->buatPegawai(100);

        $res = $this->actingAs($this->user, 'sanctum')->postJson('/api/imports', [
            'file' => $this->file(),
            'periode' => '2026-10',
        ]);

        $res->assertStatus(409)
            ->assertJsonPath('perlu_konfirmasi', true)
            ->assertJsonPath('pratinjau.jumlah_sekarang', 100)
            ->assertJsonPath('pratinjau.melewati_ambang', true);

        Queue::assertNothingPushed();
        $this->assertSame(0, ImportBatch::count());

        $this->actingAs($this->user, 'sanctum')->postJson('/api/imports', [
            'file' => $this->file(),
            'periode' => '2026-10',
            'konfirmasi_penurunan' => 1,
        ])->assertStatus(202);

        Queue::assertPushed(\App\Jobs\ProcessPegawaiImport::class);
    }

    public function test_status_aktif_tak_dikenal_ditolak_tapi_kosong_dianggap_aktif(): void
    {
        $normalizer = new PegawaiNormalizer();

        $this->assertSame('pensiun', $normalizer->resolveStatusAktif('Pensiun'));
        $this->assertSame('aktif', $normalizer->resolveStatusAktif(null));
        $this->assertSame('aktif', $normalizer->resolveStatusAktif('  '));

        $this->expectException(\InvalidArgumentException::class);
        $normalizer->resolveStatusAktif('CUTI BESAR');
    }
}