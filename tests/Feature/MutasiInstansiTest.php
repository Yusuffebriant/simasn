<?php

namespace Tests\Feature;

use App\Models\GolonganRuang;
use App\Models\Instansi;
use App\Models\Pegawai;
use App\Models\User;
use App\Services\MutasiInstansiService;
use App\Services\PegawaiSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MutasiInstansiTest extends TestCase
{
    use RefreshDatabase;

    private Instansi $a;
    private Instansi $b;
    private User $admin;
    private PegawaiSnapshotService $snapshot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::factory()->create();
        $this->a = Instansi::create(['nama' => 'Dinas A']);
        $this->b = Instansi::create(['nama' => 'Dinas B']);
        $this->snapshot = app(PegawaiSnapshotService::class);
    }

    private function pegawai(string $nip, Instansi $instansi, array $o = []): Pegawai
    {
        return Pegawai::create($o + [
            'nip' => $nip,
            'nama' => 'Pegawai ' . substr($nip, -3),
            'instansi_id' => $instansi->id,
            'unit' => 'Bidang A',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'PNS',
            'jabatan' => 'Guru',
            'jenis_kedudukan' => 'FUNGSIONAL',
            'golongan_ruang_id' => GolonganRuang::where('kode', 'III/a')->value('id'),
            'status_aktif' => 'aktif',
        ]);
    }

    /**
     * Januari: Dinas A = 3, Dinas B = 1.
     * Februari: 1 pegawai A pindah ke B, 1 pegawai A pensiun, 1 pegawai baru di B.
     */
    private function skenario(): void
    {
        $p1 = $this->pegawai('199001010000000001', $this->a);
        $p2 = $this->pegawai('199001010000000002', $this->a);
        $this->pegawai('199001010000000003', $this->a);
        $this->pegawai('199001010000000004', $this->b);
        $this->snapshot->bangun('2026-01');

        $p1->update(['instansi_id' => $this->b->id]);
        $p2->update(['status_aktif' => 'pensiun']);
        $this->pegawai('199001010000000005', $this->b);
        $this->snapshot->bangun('2026-02');
    }

    private function instansi(array $hasil, string $nama): array
    {
        return collect($hasil['instansi'])->firstWhere('nama', $nama);
    }

    public function test_jumlah_per_instansi_dan_total_tiap_bulan(): void
    {
        $this->skenario();
        $hasil = app(MutasiInstansiService::class)->susun('2026-01', '2026-02');

        $this->assertSame([3, 1], $this->instansi($hasil, 'Dinas A')['jumlah']);
        $this->assertSame([1, 3], $this->instansi($hasil, 'Dinas B')['jumlah']);
        $this->assertSame([4, 4], $hasil['total']['jumlah']);
    }

    public function test_penjelasan_pindah_baru_dan_berhenti(): void
    {
        $this->skenario();
        $hasil = app(MutasiInstansiService::class)->susun('2026-01', '2026-02');

        $a = $this->instansi($hasil, 'Dinas A');
        $this->assertNull($a['mutasi'][0]);
        $this->assertSame(3, $a['mutasi'][1]['awal']);
        $this->assertSame([['ke' => 'Dinas B', 'jumlah' => 1]], $a['mutasi'][1]['keluar']);
        $this->assertSame([['alasan' => 'Pensiun', 'jumlah' => 1]], $a['mutasi'][1]['berhenti']);
        $this->assertSame(0, $a['mutasi'][1]['baru']);
        $this->assertSame(0, $a['mutasi'][1]['tak_terlacak']);
        $this->assertSame(1, $a['mutasi'][1]['akhir']);

        $b = $this->instansi($hasil, 'Dinas B');
        $this->assertSame([['dari' => 'Dinas A', 'jumlah' => 1]], $b['mutasi'][1]['masuk']);
        $this->assertSame(1, $b['mutasi'][1]['baru']);
        $this->assertSame(0, $b['mutasi'][1]['tak_terlacak']);
        $this->assertSame(3, $b['mutasi'][1]['akhir']);

        // Pindah antar instansi tidak mengubah total.
        $total = $hasil['total']['mutasi'][1];
        $this->assertSame(1, $total['pindah']);
        $this->assertSame(1, $total['baru']);
        $this->assertSame(4, $total['awal']);
        $this->assertSame(4, $total['akhir']);
    }

    public function test_rincian_nama_pegawai_tersedia(): void
    {
        $this->skenario();
        $hasil = app(MutasiInstansiService::class)->susun('2026-01', '2026-02');

        $jenis = collect($hasil['rincian']['2026-02'])->pluck('jenis')->sort()->values()->all();
        $this->assertSame(['baru', 'berhenti', 'pindah'], $jenis);

        $pindah = collect($hasil['rincian']['2026-02'])->firstWhere('jenis', 'pindah');
        $this->assertSame('Dinas A', $pindah['dari']);
        $this->assertSame('Dinas B', $pindah['ke']);
    }

    public function test_bulan_tanpa_snapshot_kosong_dan_pembanding_bulan_terdekat(): void
    {
        $p = $this->pegawai('199001010000000001', $this->a);
        $this->snapshot->bangun('2026-01');
        $p->update(['instansi_id' => $this->b->id]);
        $this->snapshot->bangun('2026-03');

        $hasil = app(MutasiInstansiService::class)->susun('2026-01', '2026-03');

        $a = $this->instansi($hasil, 'Dinas A');
        $this->assertSame([1, null, 0], $a['jumlah']);
        $this->assertNull($a['mutasi'][1]);
        $this->assertSame('2026-01', $a['mutasi'][2]['sejak']);
        $this->assertSame(1, $a['mutasi'][2]['bulan_terlewati']);
        $this->assertSame([false, true], [$hasil['bulan'][1]['tersedia'], $hasil['bulan'][2]['tersedia']]);
    }

    public function test_pppk_paruh_waktu_tidak_dihitung(): void
    {
        $this->pegawai('199001010000000001', $this->a);
        $this->pegawai('199001010000000002', $this->a, ['status_kepegawaian' => 'PPPK_PW']);
        $this->snapshot->bangun('2026-01');

        $hasil = app(MutasiInstansiService::class)->susun('2026-01', '2026-01');

        $this->assertSame([1], $this->instansi($hasil, 'Dinas A')['jumlah']);
    }

    public function test_endpoint_butuh_login_dan_memvalidasi_rentang(): void
    {
        $url = '/api/indeks-kumulatif/instansi?awal=2026-01&akhir=2026-02';

        $this->getJson($url)->assertStatus(401);

        $this->actingAs($this->admin, 'sanctum');
        $this->getJson('/api/indeks-kumulatif/instansi')->assertStatus(422);
        $this->getJson('/api/indeks-kumulatif/instansi?awal=2026-05&akhir=2026-02')->assertStatus(422);

        $this->skenario();
        $this->getJson($url)
            ->assertStatus(200)
            ->assertJsonPath('total.jumlah', [4, 4]);
    }
}