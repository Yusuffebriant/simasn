<?php

namespace Tests\Unit;

use App\Services\IndeksKumulatifService;
use App\Support\PerubahanAtribut;
use PHPUnit\Framework\TestCase;

class PerubahanAtributTest extends TestCase
{
    private function keadaan(array $o = []): array
    {
        return $o + [
            'nama' => 'Budi', 'kategori_jabatan' => 'JFT', 'jenis_kedudukan' => 'FUNGSIONAL', 'jabatan' => 'Guru',
            'eselon' => null, 'golongan' => 'III/a', 'tmt_pangkat' => '2024-04-01', 'instansi' => 'Dinas A',
            'unit' => 'Bidang A', 'sub_unit' => null, 'pendidikan' => 'S1', 'status_kepegawaian' => 'PNS',
            'status_lainnya' => null, 'status_aktif' => 'aktif', 'tanggal_pensiun' => null,
            'tanggal_selesai_kontrak' => null, 'agama' => 'Islam', 'jenis_kelamin' => 'L', 'tanggal_lahir' => '1990-01-01',
        ];
    }

    private function atribut(array $hasil): array
    {
        return array_column($hasil, null, 'atribut');
    }

    public function test_data_tidak_berubah_tidak_menghasilkan_perubahan(): void
    {
        $this->assertSame([], PerubahanAtribut::bandingkan($this->keadaan(), $this->keadaan()));
    }

    public function test_beda_huruf_besar_kecil_dan_spasi_bukan_perubahan(): void
    {
        $baru = $this->keadaan(['jabatan' => '  GURU ', 'unit' => 'bidang   a']);
        $this->assertSame([], PerubahanAtribut::bandingkan($this->keadaan(), $baru));
    }

    public function test_jft_menjadi_jfu_dengan_pangkat_tetap(): void
    {
        $baru = $this->keadaan(['kategori_jabatan' => 'JFU', 'jenis_kedudukan' => 'PELAKSANA']);
        $h = $this->atribut(PerubahanAtribut::bandingkan($this->keadaan(), $baru));

        $this->assertSame('JFT', $h['kategori_jabatan']['sebelum']);
        $this->assertSame('JFU', $h['kategori_jabatan']['sesudah']);
        $this->assertSame('ubah', $h['kategori_jabatan']['jenis']);
        $this->assertArrayNotHasKey('golongan', $h);
    }

    public function test_jfu_menjadi_jft(): void
    {
        $lama = $this->keadaan(['kategori_jabatan' => 'JFU', 'jenis_kedudukan' => 'PELAKSANA']);
        $h = $this->atribut(PerubahanAtribut::bandingkan($lama, $this->keadaan()));

        $this->assertSame(['JFU', 'JFT'], [$h['kategori_jabatan']['sebelum'], $h['kategori_jabatan']['sesudah']]);
    }

    public function test_pangkat_berubah_membawa_tanggal_efektif_dari_tmt(): void
    {
        $baru = $this->keadaan(['golongan' => 'III/b', 'tmt_pangkat' => '2026-04-01']);
        $h = $this->atribut(PerubahanAtribut::bandingkan($this->keadaan(), $baru));

        $this->assertSame('2026-04-01', $h['golongan']['tanggal_efektif']);
        $this->assertArrayNotHasKey('kategori_jabatan', $h);
    }

    public function test_pangkat_berubah_tanpa_tmt_baru_tidak_mengarang_tanggal_efektif(): void
    {
        $baru = $this->keadaan(['golongan' => 'III/b']);
        $h = $this->atribut(PerubahanAtribut::bandingkan($this->keadaan(), $baru));

        $this->assertNull($h['golongan']['tanggal_efektif']);
    }

    public function test_nilai_kosong_dibedakan_dari_perubahan_terkonfirmasi(): void
    {
        $h = $this->atribut(PerubahanAtribut::bandingkan(
            $this->keadaan(['pendidikan' => null]),
            $this->keadaan(['pendidikan' => 'S2', 'agama' => null])
        ));

        $this->assertSame('terisi', $h['pendidikan']['jenis']);
        $this->assertTrue($h['pendidikan']['perlu_verifikasi']);
        $this->assertSame('dikosongkan', $h['agama']['jenis']);
        $this->assertTrue($h['agama']['perlu_verifikasi']);
    }

    public function test_perubahan_terkonfirmasi_pada_identitas_kuat_tidak_perlu_verifikasi(): void
    {
        $h = PerubahanAtribut::bandingkan($this->keadaan(), $this->keadaan(['unit' => 'Bidang B']));

        $this->assertFalse($h[0]['perlu_verifikasi']);
    }

    public function test_bulan_terlewati_dan_identitas_lemah_menandai_verifikasi(): void
    {
        $h = PerubahanAtribut::bandingkan(
            $this->keadaan(),
            $this->keadaan(['unit' => 'Bidang B']),
            ['bulan_terlewati' => 1, 'identitas_lemah' => true]
        );

        $this->assertTrue($h[0]['perlu_verifikasi']);
        $this->assertStringContainsString('tidak punya snapshot', $h[0]['alasan']);
    }

    public function test_beberapa_atribut_berubah_sekaligus(): void
    {
        $baru = $this->keadaan(['unit' => 'Bidang B', 'golongan' => 'III/b', 'pendidikan' => 'S2']);

        $this->assertEqualsCanonicalizing(
            ['unit', 'golongan', 'pendidikan'],
            array_column(PerubahanAtribut::bandingkan($this->keadaan(), $baru), 'atribut')
        );
    }

    public function test_identitas_nip_nik_dan_kosong(): void
    {
        $this->assertSame(['NIP:199001012015011001', 'nip'], PerubahanAtribut::identitas('199001012015011001', null));
        $this->assertSame(['NIP:199001012015011001', 'nip'], PerubahanAtribut::identitas('19900101 201501 1 001', null));
        $this->assertSame('nip_tidak_standar', PerubahanAtribut::identitas('12345', null)[1]);
        $this->assertSame(['NIK:3471010101900001', 'nik'], PerubahanAtribut::identitas(null, '3471010101900001'));
        $this->assertSame([null, 'tanpa_identitas'], PerubahanAtribut::identitas('', '123'));
        $this->assertSame([null, 'tanpa_identitas'], PerubahanAtribut::identitas(null, null));
    }

    public function test_kategori_jabatan_sama_dengan_aturan_rekap(): void
    {
        $this->assertSame('STRUKTURAL', PerubahanAtribut::kategoriJabatan('STRUKTURAL', 'III A'));
        $this->assertSame('STRUKTURAL', PerubahanAtribut::kategoriJabatan('FUNGSIONAL', 'iii  b'));
        $this->assertSame('JFT', PerubahanAtribut::kategoriJabatan('FUNGSIONAL', 'Non Eselon'));
        $this->assertSame('JFU', PerubahanAtribut::kategoriJabatan('PELAKSANA', null));
        $this->assertSame('JFU', PerubahanAtribut::kategoriJabatan(null, null));
    }

    public function test_selisih_bulan_lintas_tahun(): void
    {
        $this->assertSame(1, PerubahanAtribut::selisihBulan('2026-12', '2027-01'));
        $this->assertSame(3, PerubahanAtribut::selisihBulan('2026-01', '2026-04'));
    }

    public function test_rumus_indeks_dan_pembagian_nol(): void
    {
        $this->assertSame([50, 55, 45, 60], IndeksKumulatifService::hitungDeret([50, 55, 45, 60], 'jumlah'));
        $this->assertSame([null, 5, -10, 15], IndeksKumulatifService::hitungDeret([50, 55, 45, 60], 'selisih'));
        $this->assertSame([0, 5, -5, 10], IndeksKumulatifService::hitungDeret([50, 55, 45, 60], 'kumulatif'));
        $indeks = IndeksKumulatifService::hitungDeret([50, 55, 45, 60], 'indeks');
        $this->assertEqualsWithDelta([100, 110, 90, 120], $indeks, 0.0001);
        // dasar 0 -> tidak ada indeks (bukan 0, bukan error)
        $this->assertSame([null, null], IndeksKumulatifService::hitungDeret([0, 5], 'indeks'));
        // jumlah bulan lalu 0 di tengah deret tidak membagi nol
        $this->assertEqualsWithDelta([100, 0, 50], IndeksKumulatifService::hitungDeret([10, 0, 5], 'indeks'), 0.0001);
        // bulan kosong dilewati dan tidak dianggap 0
        $this->assertSame([null, null, null, 10], IndeksKumulatifService::hitungDeret([null, 50, null, 60], 'selisih'));
        // periode dasar pilihan
        $this->assertSame([null, 0, -10, 5], IndeksKumulatifService::hitungDeret([50, 55, 45, 60], 'kumulatif', 1));
    }

    public function test_periode_dasar_eksplisit_di_berbagai_posisi(): void
    {
        $nilai = [50, 55, 45, 60];

        // dasar = 2 (45): bulan sebelum dasar tidak punya kumulatif/indeks
        $this->assertSame([null, null, 0, 15], IndeksKumulatifService::hitungDeret($nilai, 'kumulatif', 2));
        $indeks = IndeksKumulatifService::hitungDeret($nilai, 'indeks', 2);
        $this->assertSame([null, null], array_slice($indeks, 0, 2));
        $this->assertEqualsWithDelta([100, 60 / 45 * 100], array_slice($indeks, 2), 0.0001);

        // dasar = 3 (bulan terakhir): hanya bulan itu sendiri yang terisi
        $this->assertSame([null, null, null, 0], IndeksKumulatifService::hitungDeret($nilai, 'kumulatif', 3));
        $indeks = IndeksKumulatifService::hitungDeret($nilai, 'indeks', 3);
        $this->assertSame([null, null, null], array_slice($indeks, 0, 3));
        $this->assertEqualsWithDelta(100, $indeks[3], 0.0001);

        // dasar di luar rentang (atau negatif): tidak ada pembanding, jadi null, bukan error
        foreach ([4, 99, -1] as $luar) {
            $this->assertSame([null, null, null, null], IndeksKumulatifService::hitungDeret($nilai, 'kumulatif', $luar));
            $this->assertSame([null, null, null, null], IndeksKumulatifService::hitungDeret($nilai, 'indeks', $luar));
            // jumlah dan selisih tidak bergantung pada periode dasar
            $this->assertSame($nilai, IndeksKumulatifService::hitungDeret($nilai, 'jumlah', $luar));
            $this->assertSame([null, 5, -10, 15], IndeksKumulatifService::hitungDeret($nilai, 'selisih', $luar));
        }

        // periode dasar sendiri kosong: kumulatif dan indeks tidak dihitung
        $this->assertSame([null, null, null, null], IndeksKumulatifService::hitungDeret([null, 50, null, 60], 'kumulatif', 2));
        $this->assertSame([null, null, null, null], IndeksKumulatifService::hitungDeret([null, 50, null, 60], 'indeks', 2));

        // dasar 0 eksplisit = perilaku default (bulan pertama yang punya data)
        $this->assertSame(
            IndeksKumulatifService::hitungDeret($nilai, 'kumulatif'),
            IndeksKumulatifService::hitungDeret($nilai, 'kumulatif', 0)
        );
    }
}