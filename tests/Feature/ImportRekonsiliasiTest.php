<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Instansi;
use App\Models\Pegawai;
use App\Models\User;
use App\Services\ImportRekonsiliasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportRekonsiliasiTest extends TestCase
{
    use RefreshDatabase;

    protected ImportBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $user = User::factory()->create();
        $instansi = Instansi::create(['nama' => 'Instansi Uji Rekonsiliasi']);

        $this->batch = ImportBatch::create([
            'nama_file' => 'uji.xlsx',
            'periode' => '2026-10',
            'uploaded_by' => $user->id,
            'berhasil' => 2,
            'gagal' => 0,
            'status' => 'diproses',
        ]);

        foreach ([1, 2] as $urut) {
            Pegawai::create([
                'nip' => '1990010100000' . $urut,
                'nama' => 'Pegawai Rekon ' . $urut,
                'instansi_id' => $instansi->id,
                'jenis_kelamin' => 'L',
                'status_kepegawaian' => 'PNS',
                'jabatan' => 'Staff',
                'status_aktif' => 'aktif',
                'raw_import_id' => $this->batch->id,
            ]);
        }
    }

    protected function tallyCocok(): array
    {
        return [
            'total' => 2,
            'status_kepegawaian' => ['PNS' => 2],
            'status_aktif' => ['aktif' => 2],
            'status_perkawinan' => ['_null' => 2],
            'agama' => ['_null' => 2],
            'golongan' => ['_null' => 2],
        ];
    }

    public function test_tally_sama_dengan_database_dinyatakan_cocok(): void
    {
        $hasil = (new ImportRekonsiliasi())->bandingkan($this->batch, $this->tallyCocok());

        $this->assertSame('cocok', $hasil['status']);
        $this->assertSame([], $hasil['selisih']);
        $this->assertSame(2, $hasil['total']['db']);
        $this->assertSame(0, $hasil['ganda_dalam_file']);
    }

    public function test_baris_ganda_di_file_terdeteksi_sebagai_selisih(): void
    {
        // File berisi 3 baris PNS, tetapi 2 di antaranya NIP sama sehingga
        // di database hanya tersisa 2 pegawai.
        $tally = $this->tallyCocok();
        $tally['total'] = 3;
        $tally['status_kepegawaian'] = ['PNS' => 3];

        $hasil = (new ImportRekonsiliasi())->bandingkan($this->batch, $tally);

        $this->assertSame('selisih', $hasil['status']);
        $this->assertSame(1, $hasil['ganda_dalam_file']);
        $this->assertSame('Status kepegawaian', $hasil['selisih'][0]['kategori']);
        $this->assertSame('PNS', $hasil['selisih'][0]['nilai']);
        $this->assertSame(3, $hasil['selisih'][0]['file']);
        $this->assertSame(2, $hasil['selisih'][0]['db']);
    }
}