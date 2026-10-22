<?php

namespace Tests\Feature;

use App\Exports\IndeksKumulatifExport;
use App\Models\GolonganRuang;
use App\Models\ImportBatch;
use App\Models\Instansi;
use App\Models\Pegawai;
use App\Models\PegawaiPerubahanRiwayat;
use App\Models\PegawaiSnapshotPeriode;
use App\Models\RekapSnapshotData;
use App\Models\User;
use App\Services\IndeksKumulatifService;
use App\Services\PegawaiSnapshotService;
use App\Services\PerbandinganPeriodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndeksKumulatifPerubahanTest extends TestCase
{
    use RefreshDatabase;

    private const NIP = '199001012015011001';

    private Instansi $instansi;
    private User $admin;
    private PegawaiSnapshotService $snapshot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::factory()->create();
        $this->instansi = Instansi::create(['nama' => 'Dinas Uji']);
        $this->snapshot = app(PegawaiSnapshotService::class);
    }

    private function gol(string $kode): int
    {
        return GolonganRuang::where('kode', $kode)->value('id');
    }

    private function pegawai(string $nip, array $o = []): Pegawai
    {
        return Pegawai::create($o + [
            'nip' => $nip,
            'nama' => 'Pegawai ' . substr($nip, -3),
            'instansi_id' => $this->instansi->id,
            'unit' => 'Bidang A',
            'jenis_kelamin' => 'L',
            'status_kepegawaian' => 'PNS',
            'jabatan' => 'Guru',
            'jenis_kedudukan' => 'FUNGSIONAL',
            'golongan_ruang_id' => $this->gol('III/a'),
            'status_aktif' => 'aktif',
        ]);
    }

    private function riwayat(string $dari, string $ke)
    {
        return PegawaiPerubahanRiwayat::where('periode_dari', $dari)->where('periode_ke', $ke)->get()->keyBy('atribut');
    }

    /** Januari JFT III/a Bidang A -> Feb III/b -> Mar JFU Bidang B -> Apr III/c. */
    private function skenarioContoh(): Pegawai
    {
        $p = $this->pegawai(self::NIP);
        $this->snapshot->bangun('2026-01');

        $p->update(['golongan_ruang_id' => $this->gol('III/b')]);
        $this->snapshot->bangun('2026-02');

        $p->update(['jenis_kedudukan' => 'PELAKSANA', 'unit' => 'Bidang B']);
        $this->snapshot->bangun('2026-03');

        $p->update(['golongan_ruang_id' => $this->gol('III/c')]);
        $this->snapshot->bangun('2026-04');

        return $p;
    }

    public function test_riwayat_berurutan_sesuai_contoh(): void
    {
        $this->skenarioContoh();

        $janFeb = $this->riwayat('2026-01', '2026-02');
        $this->assertSame(['golongan'], $janFeb->keys()->all());
        $this->assertSame(['III/a', 'III/b'], [$janFeb['golongan']->nilai_sebelum, $janFeb['golongan']->nilai_sesudah]);

        $febMar = $this->riwayat('2026-02', '2026-03');
        $this->assertEqualsCanonicalizing(['kategori_jabatan', 'jenis_kedudukan', 'unit'], $febMar->keys()->all());
        $this->assertSame('JFT', $febMar['kategori_jabatan']->nilai_sebelum);
        $this->assertSame('JFU', $febMar['kategori_jabatan']->nilai_sesudah);
        $this->assertSame(['Bidang A', 'Bidang B'], [$febMar['unit']->nilai_sebelum, $febMar['unit']->nilai_sesudah]);

        $marApr = $this->riwayat('2026-03', '2026-04');
        $this->assertSame(['golongan'], $marApr->keys()->all());
    }

    public function test_perbandingan_kondisi_awal_akhir_sesuai_contoh(): void
    {
        $this->skenarioContoh();
        $service = app(PerbandinganPeriodeService::class);

        $janMar = collect($service->semuaKondisi('2026-01', '2026-03'))->keyBy('atribut');
        $this->assertEqualsCanonicalizing(['golongan', 'kategori_jabatan', 'jenis_kedudukan', 'unit'], $janMar->keys()->all());
        $this->assertSame(['III/a', 'III/b'], [$janMar['golongan']['nilai_sebelum'], $janMar['golongan']['nilai_sesudah']]);

        $janApr = collect($service->semuaKondisi('2026-01', '2026-04'))->keyBy('atribut');
        $this->assertSame('III/c', $janApr['golongan']['nilai_sesudah']);
        $this->assertSame('JFU', $janApr['kategori_jabatan']['nilai_sesudah']);

        $febApr = collect($service->semuaKondisi('2026-02', '2026-04'))->keyBy('atribut');
        $this->assertSame(['III/b', 'III/c'], [$febApr['golongan']['nilai_sebelum'], $febApr['golongan']['nilai_sesudah']]);
        $this->assertSame('Bidang B', $febApr['unit']['nilai_sesudah']);
    }

    public function test_atribut_berubah_lalu_kembali_tidak_dihitung_di_kondisi_tetapi_tercatat_di_kejadian(): void
    {
        $p = $this->pegawai(self::NIP);
        $this->snapshot->bangun('2026-01');
        $p->update(['unit' => 'Bidang B']);
        $this->snapshot->bangun('2026-02');
        $p->update(['unit' => 'Bidang A']);
        $this->snapshot->bangun('2026-03');

        $service = app(PerbandinganPeriodeService::class);

        $this->assertSame([], $service->semuaKondisi('2026-01', '2026-03'));
        $this->assertCount(2, $service->semuaKejadian('2026-01', '2026-03'));

        $ringkasan = $service->ringkasan('2026-01', '2026-03');
        $this->assertSame(0, $ringkasan['kondisi']['kejadian_perubahan']);
        $this->assertSame(2, $ringkasan['kejadian']['kejadian_perubahan']);
        $this->assertSame(1, $ringkasan['kejadian']['pegawai_unik_berubah']);
    }

    public function test_data_tidak_berubah_tidak_menghasilkan_riwayat(): void
    {
        $this->pegawai(self::NIP);
        $this->snapshot->bangun('2026-01');
        $this->snapshot->bangun('2026-02');

        $this->assertSame(0, PegawaiPerubahanRiwayat::count());
    }

    public function test_jft_ke_jfu_dihitung_dari_data_individual(): void
    {
        $this->skenarioContoh();
        $this->pegawai('199002022016022002', ['jenis_kedudukan' => 'FUNGSIONAL']);

        $ringkasan = app(PerbandinganPeriodeService::class)->ringkasan('2026-01', '2026-04');

        $this->assertSame(
            [['dari' => 'JFT', 'ke' => 'JFU', 'jumlah' => 1]],
            $ringkasan['kondisi']['perpindahan_jabatan']
        );
    }

    public function test_bulan_tanpa_snapshot_ditandai_dan_tidak_dikarang(): void
    {
        $p = $this->pegawai(self::NIP);
        $this->snapshot->bangun('2026-01');
        $p->update(['unit' => 'Bidang B']);
        $this->snapshot->bangun('2026-03');

        $janMar = $this->riwayat('2026-01', '2026-03');
        $this->assertSame(1, (int) $janMar['unit']->bulan_terlewati);
        $this->assertTrue($janMar['unit']->perlu_verifikasi);

        $status = collect(app(PerbandinganPeriodeService::class)->statusPeriode('2026-01', '2026-03'))->keyBy('periode');
        $this->assertSame('tersedia', $status['2026-01']['status']);
        $this->assertSame('tidak_ada_data', $status['2026-02']['status']);

        $deret = app(PerbandinganPeriodeService::class)->deret('2026-01', '2026-03');
        $this->assertSame('tidak_tersedia', $deret['berurutan'][1]['status']);
        $this->assertSame(1, $deret['berurutan'][2]['bulan_terlewati']);
    }

    public function test_periode_tanpa_snapshot_individual_tidak_bisa_dibandingkan(): void
    {
        $this->pegawai(self::NIP);
        $this->snapshot->bangun('2026-03');

        $ringkasan = app(PerbandinganPeriodeService::class)->ringkasan('2026-01', '2026-03');

        $this->assertFalse($ringkasan['dapat_dibandingkan']);
        $this->assertNull($ringkasan['kondisi']);
        $this->assertNotNull($ringkasan['pesan']);
    }

    public function test_impor_ulang_isi_sama_tidak_menggandakan_apa_pun(): void
    {
        $p = $this->pegawai(self::NIP);
        $this->snapshot->bangun('2026-01');
        $p->update(['unit' => 'Bidang B']);
        $this->snapshot->bangun('2026-02');

        $jumlahSnapshot = \DB::table('pegawai_snapshots')->count();
        $jumlahRiwayat = PegawaiPerubahanRiwayat::count();

        $hasil = $this->snapshot->bangun('2026-02');

        $this->assertSame('tidak_berubah', $hasil['status']);
        $this->assertSame($jumlahSnapshot, \DB::table('pegawai_snapshots')->count());
        $this->assertSame($jumlahRiwayat, PegawaiPerubahanRiwayat::count());
        $this->assertSame(1, (int) PegawaiSnapshotPeriode::where('periode', '2026-02')->value('versi'));
    }

    public function test_koreksi_menaikkan_versi_dan_menghitung_ulang_tanpa_ganda(): void
    {
        $p = $this->pegawai(self::NIP);
        $this->snapshot->bangun('2026-01');
        $p->update(['unit' => 'Bidang B']);
        $this->snapshot->bangun('2026-02');
        $p->update(['unit' => 'Bidang C']); // koreksi data Februari
        $hasil = $this->snapshot->bangun('2026-02');

        $this->assertSame('diperbarui', $hasil['status']);

        $meta = PegawaiSnapshotPeriode::where('periode', '2026-02')->first();
        $this->assertSame(2, (int) $meta->versi);
        $this->assertCount(2, $meta->riwayat);

        $riwayat = $this->riwayat('2026-01', '2026-02');
        $this->assertCount(1, $riwayat);
        $this->assertSame('Bidang C', $riwayat['unit']->nilai_sesudah);
        $this->assertSame(1, PegawaiPerubahanRiwayat::count());
    }

    public function test_menyisipkan_periode_di_tengah_menyambung_ulang_rantai(): void
    {
        $p = $this->pegawai(self::NIP);
        $this->snapshot->bangun('2026-01');
        $p->update(['unit' => 'Bidang B']);
        $this->snapshot->bangun('2026-03');
        $this->assertSame(1, (int) $this->riwayat('2026-01', '2026-03')['unit']->bulan_terlewati);

        $this->snapshot->bangun('2026-02'); // Februari ikut berisi Bidang B

        $this->assertSame(0, PegawaiPerubahanRiwayat::where('periode_dari', '2026-01')->where('periode_ke', '2026-03')->count());
        $this->assertSame(1, $this->riwayat('2026-01', '2026-02')->count());
        $this->assertSame(0, $this->riwayat('2026-02', '2026-03')->count());
    }

    public function test_pegawai_tidak_ditemukan_ditandai_bukan_disimpulkan_pensiun(): void
    {
        $p = $this->pegawai(self::NIP, ['tanggal_pensiun' => '2026-01-31']);
        $this->snapshot->bangun('2026-01');
        $p->forceDelete();
        $this->pegawai('199002022016022002');
        $this->snapshot->bangun('2026-02');

        $hilang = PegawaiPerubahanRiwayat::where('kunci', 'NIP:' . self::NIP)->first();

        $this->assertSame('tidak_ditemukan', $hilang->jenis);
        $this->assertTrue($hilang->perlu_verifikasi);
        $this->assertSame('muncul', PegawaiPerubahanRiwayat::where('kunci', 'NIP:199002022016022002')->value('jenis'));
        $this->assertNotContains('pensiun', PegawaiPerubahanRiwayat::pluck('jenis')->all());
        $this->assertSame(2, PegawaiPerubahanRiwayat::where('periode_dari', '2026-01')->where('periode_ke', '2026-02')->count());
    }

    public function test_nip_kosong_duplikat_dan_tanpa_identitas_tidak_dicocokkan_sembarangan(): void
    {
        $this->pegawai('19900101 201501 1 001');           // kunci sama setelah dinormalkan
        $this->pegawai('199001012015011001');               // -> duplikat
        $this->pegawai('', ['nip' => null, 'nik' => '3471010101900001', 'nama' => 'Hanya NIK']);
        $this->pegawai('', ['nip' => null, 'nama' => 'Tanpa Identitas']);
        $this->snapshot->bangun('2026-01');

        $meta = PegawaiSnapshotPeriode::where('periode', '2026-01')->first();
        $this->assertSame(4, (int) $meta->jumlah_pegawai);
        $this->assertSame(2, (int) $meta->jumlah_duplikat);
        $this->assertSame(1, (int) $meta->jumlah_tanpa_identitas);
        $this->assertSame(0, (int) $meta->jumlah_identitas_valid);
        $this->assertSame(4, (int) $meta->jumlah_perlu_verifikasi);

        $this->assertSame(
            ['duplikat', 'duplikat', 'nik', 'tanpa_identitas'],
            \DB::table('pegawai_snapshots')->orderBy('status_identitas')->pluck('status_identitas')->all()
        );
        $this->assertSame(3, \DB::table('pegawai_snapshots')->whereNull('kunci')->count());
    }

    public function test_pegawai_dicocokkan_lewat_nik_saat_nip_berubah_dan_ditandai_verifikasi(): void
    {
        $p = $this->pegawai(self::NIP, ['nik' => '3471010101900001']);
        $this->snapshot->bangun('2026-01');
        $p->update(['nip' => null, 'unit' => 'Bidang B']);
        $this->snapshot->bangun('2026-02');

        $r = PegawaiPerubahanRiwayat::where('periode_ke', '2026-02')->get()->keyBy('atribut');

        $this->assertArrayHasKey('nip', $r->all());
        $this->assertTrue($r['unit']->perlu_verifikasi);
        $this->assertSame('NIP:' . self::NIP, $r['unit']->kunci_sebelumnya);
        $this->assertNotContains('tidak_ditemukan', $r->pluck('jenis')->all());
    }

    public function test_periode_lintas_tahun(): void
    {
        $this->assertSame(['2026-11', '2026-12', '2027-01', '2027-02'], PerbandinganPeriodeService::daftarBulan('2026-11', '2027-02'));

        $p = $this->pegawai(self::NIP);
        $this->snapshot->bangun('2026-12');
        $p->update(['unit' => 'Bidang B']);
        $this->snapshot->bangun('2027-01');

        $r = $this->riwayat('2026-12', '2027-01');
        $this->assertSame(0, (int) $r['unit']->bulan_terlewati);
        $this->assertFalse($r['unit']->perlu_verifikasi);
        $this->assertCount(1, app(PerbandinganPeriodeService::class)->semuaKondisi('2026-12', '2027-01'));
    }

    public function test_rekap_agregat_membedakan_bulan_tanpa_arsip_dari_bulan_kosong(): void
    {
        $lama = ImportBatch::create(['nama_file' => 'jan.xlsx', 'periode' => '2026-01', 'uploaded_by' => $this->admin->id, 'status' => 'selesai']);
        $lama->forceFill(['created_at' => now()->subDays(2)])->save();
        $baru = ImportBatch::create(['nama_file' => 'mar.xlsx', 'periode' => '2026-03', 'uploaded_by' => $this->admin->id, 'status' => 'selesai']);
        $baru->forceFill(['created_at' => now()])->save();

        $data = app(IndeksKumulatifService::class)->susunRentang('2026-01', '2026-03');
        $sumber = array_column($data['bulan'], 'sumber', 'periode');

        $this->assertSame('tanpa_snapshot', $sumber['2026-01']);
        $this->assertSame('kosong', $sumber['2026-02']);
        $this->assertSame('langsung', $sumber['2026-03']);
        $this->assertNull($data['dimensi']['agama']['total'][0]);
    }

    public function test_rekap_rentang_lintas_tahun_memakai_semua_bulan(): void
    {
        $data = app(IndeksKumulatifService::class)->susunRentang('2026-11', '2027-02');

        $this->assertSame(['2026-11', '2026-12', '2027-01', '2027-02'], array_column($data['bulan'], 'periode'));
    }

    // ------------------------------------------------------------------
    // API
    // ------------------------------------------------------------------

    public function test_endpoint_butuh_login_dan_role_admin(): void
    {
        $url = '/api/indeks-kumulatif/perubahan/ringkasan?awal=2026-01&akhir=2026-02';

        $this->getJson($url)->assertStatus(401);

        $viewer = User::factory()->create();
        $viewer->syncRoles(['viewer']);
        $this->actingAs($viewer, 'sanctum')->getJson($url)->assertStatus(403);

        $this->actingAs($this->admin, 'sanctum')->getJson($url)->assertStatus(200);
    }

    public function test_validasi_rentang_periode(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/indeks-kumulatif/perubahan/ringkasan?awal=2026-05&akhir=2026-02')->assertStatus(422);
        $this->getJson('/api/indeks-kumulatif/perubahan/ringkasan?awal=abc&akhir=2026-02')->assertStatus(422);
        $this->getJson('/api/indeks-kumulatif/perubahan/ringkasan')->assertStatus(422);
        $this->getJson('/api/indeks-kumulatif/perubahan?awal=2026-01&akhir=2026-02&per_page=5')->assertStatus(422);
    }

    public function test_daftar_perubahan_filter_pencarian_dan_halaman(): void
    {
        $this->skenarioContoh();
        $lain = $this->pegawai('199003032017033003', ['nama' => 'Siti Lain']);
        // Siti ada sejak Januari? tidak: ia baru dibuat setelah skenario, jadi ikut muncul sebagai 'muncul' di Apr.
        $this->snapshot->bangun('2026-04');
        $lain->update(['unit' => 'Bidang Z']);
        $this->snapshot->bangun('2026-05');

        $this->actingAs($this->admin, 'sanctum');
        $dasar = '/api/indeks-kumulatif/perubahan?awal=2026-01&akhir=2026-04&mode=kejadian';

        $semua = $this->getJson($dasar)->assertStatus(200)->json();
        $this->assertTrue($semua['dapat_dibandingkan']);
        $this->assertGreaterThanOrEqual(4, $semua['total']);

        $kategori = $this->getJson($dasar . '&kategori=pangkat_golongan')->json();
        $this->assertSame(2, $kategori['total']);

        $cari = $this->getJson($dasar . '&q=' . self::NIP)->json();
        $this->assertSame($semua['total'] - collect($semua['data'])->where('nip', '!=', self::NIP)->count(), $cari['total']);

        $hal = $this->getJson($dasar . '&per_page=10&page=1')->json();
        $this->assertLessThanOrEqual(10, count($hal['data']));
        $this->assertSame(1, $hal['current_page']);

        $kondisi = $this->getJson('/api/indeks-kumulatif/perubahan?awal=2026-01&akhir=2026-04&mode=kondisi')->json();
        $this->assertSame('kondisi', $kondisi['mode']);
    }

    public function test_perubahan_tanpa_snapshot_mengembalikan_status_tidak_tersedia(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $r = $this->getJson('/api/indeks-kumulatif/perubahan?awal=2026-01&akhir=2026-02')->assertStatus(200)->json();

        $this->assertFalse($r['dapat_dibandingkan']);
        $this->assertSame([], $r['data']);
    }

    public function test_detail_riwayat_pegawai(): void
    {
        $this->skenarioContoh();
        $this->actingAs($this->admin, 'sanctum');

        $r = $this->getJson('/api/indeks-kumulatif/perubahan/pegawai?kunci=NIP:' . self::NIP . '&awal=2026-01&akhir=2026-04')
            ->assertStatus(200)->json();

        $this->assertCount(4, $r['garis_waktu']);
        $this->assertSame('ada', $r['garis_waktu'][0]['status']);
        $this->assertCount(5, $r['kejadian']); // golongan, kategori_jabatan, jenis_kedudukan, unit, golongan
        $this->assertNotEmpty($r['kondisi']);

        $this->getJson('/api/indeks-kumulatif/perubahan/pegawai?kunci=NIP:000&awal=2026-01&akhir=2026-04')->assertStatus(404);
    }

    public function test_export_perubahan_dan_konsistensi_dengan_halaman(): void
    {
        $this->skenarioContoh();
        $this->actingAs($this->admin, 'sanctum');

        $url = '/api/indeks-kumulatif/perubahan/export?awal=2026-01&akhir=2026-04&mode=kondisi';
        $this->get($url)->assertStatus(200)->assertHeader('content-disposition');

        // Jumlah baris di Excel = total di halaman untuk filter dan mode yang sama.
        $halaman = $this->getJson('/api/indeks-kumulatif/perubahan?awal=2026-01&akhir=2026-04&mode=kondisi')->json();
        $this->assertCount($halaman['total'], app(PerbandinganPeriodeService::class)->semuaKondisi('2026-01', '2026-04'));

        $this->get('/api/indeks-kumulatif/perubahan/export?awal=2026-01&akhir=2026-09')->assertStatus(404);
    }

    public function test_endpoint_rekap_lama_tetap_jalan_dan_menerima_rentang(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $this->getJson('/api/indeks-kumulatif?tahun=2026')->assertStatus(200)->assertJsonStructure(['tahun', 'bulan', 'dimensi', 'tahun_tersedia']);
        $this->getJson('/api/indeks-kumulatif?awal=2026-11&akhir=2027-02')->assertStatus(200)->assertJsonCount(4, 'bulan');
        $this->getJson('/api/indeks-kumulatif/export?tahun=2026')->assertStatus(404);
    }

    public function test_export_rentang_lintas_tahun(): void
    {
        // Islam per bulan: 18, 19, 21, 20 (pria 10 + wanita sisanya) untuk kelima dimensi.
        $jumlah = ['2026-11' => 18, '2026-12' => 19, '2027-01' => 21, '2027-02' => 20];
        foreach ($jumlah as $periode => $total) {
            foreach (array_column(IndeksKumulatifService::DIMENSI, 'snapshot') as $nama) {
                RekapSnapshotData::create([
                    'periode' => $periode,
                    'nama' => $nama,
                    'payload' => [['pria' => ['Islam' => 10], 'wanita' => ['Islam' => $total - 10]]],
                    'dibekukan_pada' => now(),
                ]);
            }
        }

        $this->actingAs($this->admin, 'sanctum');

        $respons = $this->get('/api/indeks-kumulatif/export?awal=2026-11&akhir=2027-02')->assertStatus(200);
        $this->assertStringContainsString(
            'indeks-kumulatif-2026-11-sd-2027-02.xlsx',
            $respons->headers->get('content-disposition')
        );

        // Isi workbook: Ringkasan, Keterangan, lalu sheet per dimensi (agama = sheet ke-3).
        $sheets = (new IndeksKumulatifExport('2026', '2026-11', '2027-02'))->sheets();
        $this->assertCount(7, $sheets);
        $this->assertSame('Agama', $sheets[2]->title());

        $baris = collect($sheets[2]->array());

        // Label bulan memuat tahun karena rentang melewati pergantian tahun.
        $header = $baris->first(fn ($r) => ($r[0] ?? null) === 'Agama');
        $this->assertSame(['Agama', 'Nov 2026', 'Des 2026', 'Jan 2027', 'Feb 2027'], $header);
        $this->assertStringContainsString('November 2026 s.d. Februari 2027', $baris[1][0]);

        // Baris "Islam" muncul empat kali, sesuai urutan blok: jumlah, selisih, kumulatif, indeks.
        $islam = $baris->filter(fn ($r) => ($r[0] ?? null) === 'Islam')->values();
        $this->assertCount(4, $islam);
        $this->assertSame(['Islam', 18, 19, 21, 20], $islam[0]);
        $this->assertSame(['Islam', '-', 1, 2, -1], $islam[1]);
        $this->assertSame(['Islam', 0, 1, 3, 2], $islam[2]); // periode dasar = awal rentang (Nov 2026)
        $this->assertSame(100.0, $islam[3][1]);
        $this->assertEqualsWithDelta(20 / 18 * 100, $islam[3][4], 0.0001);

        // Rentang lintas tahun tanpa data sama sekali: 404, bukan file kosong.
        $this->get('/api/indeks-kumulatif/export?awal=2028-11&akhir=2029-02')->assertStatus(404);
    }

    public function test_command_snapshot_menolak_periode_bukan_aktif(): void
    {
        $this->pegawai(self::NIP);
        ImportBatch::create(['nama_file' => 'okt.xlsx', 'periode' => '2026-10', 'uploaded_by' => $this->admin->id, 'status' => 'selesai']);

        $this->artisan('pegawai:snapshot', ['periode' => '2026-09'])->assertExitCode(1);
        $this->artisan('pegawai:snapshot', ['periode' => 'abc'])->assertExitCode(1);
        $this->artisan('pegawai:snapshot')->assertExitCode(0);

        $this->assertSame(1, PegawaiSnapshotPeriode::where('periode', '2026-10')->count());
    }

    public function test_rekap_snapshot_agregat_lama_tidak_tersentuh(): void
    {
        RekapSnapshotData::create(['periode' => '2026-01', 'nama' => 'agama', 'payload' => [], 'dibekukan_pada' => now()]);
        $this->pegawai(self::NIP);
        $this->snapshot->bangun('2026-01');

        $this->assertSame(1, RekapSnapshotData::where('periode', '2026-01')->count());
    }
}