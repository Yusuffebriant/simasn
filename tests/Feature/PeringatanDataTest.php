<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Instansi;
use App\Models\Pegawai;
use App\Models\User;
use App\Services\PeringatanDataService;
use App\Services\RekapSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeringatanDataTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Instansi $instansi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->user = User::factory()->create();
        $this->instansi = Instansi::create(['nama' => 'Instansi Uji Peringatan']);
    }

    private function batch(array $atribut = []): ImportBatch
    {
        return ImportBatch::create(array_merge([
            'nama_file' => 'data.xlsx',
            'periode' => '2026-10',
            'uploaded_by' => $this->user->id,
            'status' => 'selesai',
        ], $atribut));
    }

    private function pegawai(string $nip, string $nama, ?int $batchId, string $lahir = '1990-01-01'): Pegawai
    {
        return Pegawai::unguarded(fn () => Pegawai::create([
            'nip' => $nip,
            'nama' => $nama,
            'instansi_id' => $this->instansi->id,
            'jenis_kelamin' => 'L',
            // LAINNYA: hanya wajib jenis kelamin, tanggal lahir, dan perangkat daerah
            // (lihat KelengkapanPegawai::ATURAN). Dengan PNS, fixture ini dianggap
            // belum lengkap (agama, pendidikan, golongan, dst. kosong) sehingga
            // peringatan data_belum_lengkap membuat 'ok' jadi false.
            'status_kepegawaian' => 'LAINNYA',
            'jabatan' => 'Staff',
            'status_aktif' => 'aktif',
            'tanggal_lahir' => $lahir,
            'raw_import_id' => $batchId,
        ]));
    }

    private function kodeKode(array $hasil): array
    {
        return array_column($hasil['peringatan'], 'kode');
    }

    public function test_data_bersih_tidak_ada_peringatan(): void
    {
        $batch = $this->batch();
        $this->pegawai('4000000000000001', 'Andi', $batch->id, '1990-01-01');
        $this->pegawai('4000000000000002', 'Budi', $batch->id, '1991-02-02');
        (new RekapSnapshotService())->bekukan('2026-10');

        $hasil = (new PeringatanDataService())->periksa();

        $this->assertTrue($hasil['ok']);
        $this->assertSame([], $hasil['peringatan']);
        $this->assertSame('2026-10', $hasil['periode_aktif']);
    }

    public function test_pegawai_aktif_sisa_batch_lama_terdeteksi(): void
    {
        $batch = $this->batch();
        $this->pegawai('4000000000000001', 'Andi', $batch->id);
        $this->pegawai('4000000000000002', 'Sisa Lama', null, '1985-05-05');
        (new RekapSnapshotService())->bekukan('2026-10');

        $hasil = (new PeringatanDataService())->periksa();

        $this->assertFalse($hasil['ok']);
        $this->assertContains('sisa_batch_lama', $this->kodeKode($hasil));

        $item = collect($hasil['peringatan'])->firstWhere('kode', 'sisa_batch_lama');
        $this->assertSame('bahaya', $item['tingkat']);
        $this->assertSame(1, $item['jumlah']);
    }

    public function test_pegawai_nonaktif_tidak_dihitung_sebagai_sisa(): void
    {
        $batch = $this->batch();
        $this->pegawai('4000000000000001', 'Andi', $batch->id);
        $lama = $this->pegawai('4000000000000002', 'Sudah Nonaktif', null, '1985-05-05');
        $lama->forceFill(['status_aktif' => 'mutasi_keluar'])->save();
        (new RekapSnapshotService())->bekukan('2026-10');

        $this->assertTrue((new PeringatanDataService())->periksa()['ok']);
    }

    public function test_nama_dan_tanggal_lahir_sama_terdeteksi_dobel(): void
    {
        $batch = $this->batch();
        $this->pegawai('4000000000000001', 'Siti Aminah', $batch->id, '1992-03-03');
        $this->pegawai('4000000000000002', ' siti aminah ', $batch->id, '1992-03-03');
        (new RekapSnapshotService())->bekukan('2026-10');

        $hasil = (new PeringatanDataService())->periksa();

        $item = collect($hasil['peringatan'])->firstWhere('kode', 'kemungkinan_dobel');
        $this->assertNotNull($item);
        $this->assertSame(1, $item['jumlah']);
        $this->assertFalse($hasil['ok']);
    }

    public function test_baris_gagal_di_import_terakhir_terdeteksi(): void
    {
        $batch = $this->batch(['gagal' => 3, 'berhasil' => 10]);
        $this->pegawai('4000000000000001', 'Andi', $batch->id);
        (new RekapSnapshotService())->bekukan('2026-10');

        $hasil = (new PeringatanDataService())->periksa();

        $this->assertContains('baris_gagal', $this->kodeKode($hasil));
        $this->assertFalse($hasil['ok']);
    }

    public function test_snapshot_belum_ada_hanya_info_dan_tidak_membuat_status_tidak_ok(): void
    {
        $batch = $this->batch();
        $this->pegawai('4000000000000001', 'Andi', $batch->id);

        $hasil = (new PeringatanDataService())->periksa();

        $this->assertContains('snapshot_belum_ada', $this->kodeKode($hasil));
        $this->assertTrue($hasil['ok']);
    }

    public function test_belum_ada_import_hanya_info(): void
    {
        $hasil = (new PeringatanDataService())->periksa();

        $this->assertTrue($hasil['ok']);
        $this->assertSame(['belum_ada_import'], $this->kodeKode($hasil));
    }

    public function test_endpoint_butuh_login_dan_mengembalikan_hasil(): void
    {
        $this->getJson('/api/peringatan-data')->assertStatus(401);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/peringatan-data')
            ->assertStatus(200)
            ->assertJsonStructure(['ok', 'periode_aktif', 'diperiksa_pada', 'peringatan']);
    }
}