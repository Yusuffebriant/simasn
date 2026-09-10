<?php

namespace Tests\Feature;

use App\Models\GolonganRuang;
use App\Models\Instansi;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatistikPensiunanPNSTest extends TestCase
{
    use RefreshDatabase;

    protected Instansi $instansi;

    protected function setUp(): void
    {
        parent::setUp();

        // Butuh data referensi (golongan_ruang, dll).
        $this->seed();

        $this->instansi = Instansi::create(['nama' => 'Instansi Uji Coba']);
    }

    /**
     * Helper untuk membuat 1 baris pegawai dengan default yang masuk akal,
     * supaya tiap test tinggal override field yang relevan saja.
     */
    protected function buatPegawai(array $override = []): Pegawai
    {
        static $urut = 0;
        $urut++;

        return Pegawai::create(array_merge([
            'nip' => '19800101' . str_pad((string) $urut, 6, '0', STR_PAD_LEFT),
            'nama' => 'Pegawai Uji ' . $urut,
            'instansi_id' => $this->instansi->id,
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'PNS',
            'golongan_ruang_id' => null,
            'jabatan' => 'Staff',
            'tanggal_pensiun' => null,
            'status_aktif' => 'aktif',
        ], $override));
    }

    protected function golongan(string $kode): int
    {
        return GolonganRuang::where('kode', $kode)->where('kelompok', 'PNS')->firstOrFail()->id;
    }

    public function test_pns_pensiun_15_februari_2024_dihitung_pada_2024(): void
    {
        $this->buatPegawai([
            'tanggal_pensiun' => '2024-02-15',
            'golongan_ruang_id' => $this->golongan('III/a'),
        ]);

        $service = app(\App\Services\RekapService::class);
        $data = $service->statistikPensiunanPNS(2024);

        $this->assertSame(1, $data['jumlah_pensiunan_pns']);
    }

    public function test_pns_pensiun_31_desember_2024_dihitung_pada_2024(): void
    {
        $this->buatPegawai([
            'tanggal_pensiun' => '2024-12-31',
            'golongan_ruang_id' => $this->golongan('III/a'),
        ]);

        $data = app(\App\Services\RekapService::class)->statistikPensiunanPNS(2024);

        $this->assertSame(1, $data['jumlah_pensiunan_pns']);
    }

    public function test_pns_pensiun_1_januari_2025_tidak_dihitung_pada_2024(): void
    {
        $this->buatPegawai([
            'tanggal_pensiun' => '2025-01-01',
            'golongan_ruang_id' => $this->golongan('III/a'),
        ]);

        $data = app(\App\Services\RekapService::class)->statistikPensiunanPNS(2024);

        $this->assertSame(0, $data['jumlah_pensiunan_pns']);
    }

    public function test_pns_pensiun_1_januari_2025_dihitung_pada_2025(): void
    {
        $this->buatPegawai([
            'tanggal_pensiun' => '2025-01-01',
            'golongan_ruang_id' => $this->golongan('III/a'),
        ]);

        $data = app(\App\Services\RekapService::class)->statistikPensiunanPNS(2025);

        $this->assertSame(1, $data['jumlah_pensiunan_pns']);
    }

    public function test_pppk_tidak_dihitung_sebagai_pensiunan_pns(): void
    {
        $this->buatPegawai([
            'status_kepegawaian' => 'PPPK',
            'tanggal_pensiun' => '2024-06-01',
            'golongan_ruang_id' => GolonganRuang::where('kode', 'III')->where('kelompok', 'PPPK')->firstOrFail()->id,
        ]);

        $data = app(\App\Services\RekapService::class)->statistikPensiunanPNS(2024);

        $this->assertSame(0, $data['jumlah_pensiunan_pns']);
    }

    public function test_pegawai_dengan_tanggal_pensiun_null_tidak_dihitung(): void
    {
        $this->buatPegawai([
            'tanggal_pensiun' => null,
            'golongan_ruang_id' => $this->golongan('III/a'),
        ]);

        $data = app(\App\Services\RekapService::class)->statistikPensiunanPNS(2024);

        $this->assertSame(0, $data['jumlah_pensiunan_pns']);
    }

    public function test_pensiunan_golongan_i_masuk_golongan_i(): void
    {
        $this->buatPegawai([
            'tanggal_pensiun' => '2024-03-01',
            'golongan_ruang_id' => $this->golongan('I/a'),
        ]);

        $data = app(\App\Services\RekapService::class)->statistikPensiunanPNS(2024);

        $this->assertSame(1, $data['golongan_I']['total']);
        $this->assertSame(0, $data['golongan_II']['total']);
    }

    public function test_pensiunan_golongan_ii_masuk_golongan_ii(): void
    {
        $this->buatPegawai([
            'tanggal_pensiun' => '2024-03-01',
            'golongan_ruang_id' => $this->golongan('II/b'),
        ]);

        $data = app(\App\Services\RekapService::class)->statistikPensiunanPNS(2024);

        $this->assertSame(1, $data['golongan_II']['total']);
    }

    public function test_pensiunan_golongan_iii_masuk_golongan_iii(): void
    {
        $this->buatPegawai([
            'tanggal_pensiun' => '2024-03-01',
            'golongan_ruang_id' => $this->golongan('III/c'),
        ]);

        $data = app(\App\Services\RekapService::class)->statistikPensiunanPNS(2024);

        $this->assertSame(1, $data['golongan_III']['total']);
    }

    public function test_pensiunan_golongan_iv_masuk_golongan_iv(): void
    {
        $this->buatPegawai([
            'tanggal_pensiun' => '2024-03-01',
            'golongan_ruang_id' => $this->golongan('IV/d'),
        ]);

        $data = app(\App\Services\RekapService::class)->statistikPensiunanPNS(2024);

        $this->assertSame(1, $data['golongan_IV']['total']);
    }

    public function test_total_pensiunan_sesuai_jumlah_seluruh_pns_pada_tahun_tersebut(): void
    {
        $this->buatPegawai(['tanggal_pensiun' => '2024-01-01', 'golongan_ruang_id' => $this->golongan('I/a')]);
        $this->buatPegawai(['tanggal_pensiun' => '2024-06-15', 'golongan_ruang_id' => $this->golongan('II/a')]);
        $this->buatPegawai(['tanggal_pensiun' => '2024-06-15', 'golongan_ruang_id' => $this->golongan('III/a')]);
        $this->buatPegawai(['tanggal_pensiun' => '2024-12-31', 'golongan_ruang_id' => $this->golongan('IV/a')]);
        // Golongan kosong/tidak valid: tetap masuk total, tapi tidak masuk bucket manapun.
        $this->buatPegawai(['tanggal_pensiun' => '2024-05-05', 'golongan_ruang_id' => null]);
        // Di luar tahun 2024, tidak boleh ikut terhitung.
        $this->buatPegawai(['tanggal_pensiun' => '2025-01-01', 'golongan_ruang_id' => $this->golongan('I/a')]);
        $this->buatPegawai(['tanggal_pensiun' => '2023-12-31', 'golongan_ruang_id' => $this->golongan('I/a')]);

        $data = app(\App\Services\RekapService::class)->statistikPensiunanPNS(2024);

        $this->assertSame(5, $data['jumlah_pensiunan_pns']);
        $this->assertSame(
            4,
            $data['golongan_I']['total'] + $data['golongan_II']['total']
                + $data['golongan_III']['total'] + $data['golongan_IV']['total']
        );
    }

    public function test_endpoint_statistik_pensiunan_pns_bisa_diakses_dengan_parameter_tahun(): void
    {
        $this->buatPegawai([
            'tanggal_pensiun' => '2024-07-01',
            'golongan_ruang_id' => $this->golongan('III/a'),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/statistik/pensiunan-pns?tahun=2024');

        $response->assertStatus(200);
        $response->assertJsonPath('data.tahun', 2024);
        $response->assertJsonPath('data.jumlah_pensiunan_pns', 1);
    }

    public function test_endpoint_menolak_parameter_tahun_yang_tidak_valid(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/statistik/pensiunan-pns?tahun=abcd');

        $response->assertStatus(422);
    }
}