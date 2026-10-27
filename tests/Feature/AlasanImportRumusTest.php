<?php

namespace Tests\Feature;

use App\Support\AlasanImport;
use Tests\TestCase;

/**
 * Penjelasan import untuk sel Excel yang isinya RUMUS (=VLOOKUP(...), ="123").
 */
class AlasanImportRumusTest extends TestCase
{
    private const VLOOKUP = '=VLOOKUP($B3,[1]Simpeg!$B$3:$AJ$5128,35,FALSE)';

    private const PESAN_TERLALU_PANJANG = "SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'status_perkawinan' at row 1";

    public function test_kolom_berumus_dikenali_dan_nilai_biasa_diabaikan(): void
    {
        $hasil = AlasanImport::kolomBerumus([
            'NAMA' => 'SUHARYANTI',
            'NIP' => '="197902052025211049"',
            'STATUS_PERKAWINAN' => self::VLOOKUP,
            'RT' => 6,
            'AGAMA' => 'Islam',
            'ALAMAT' => '=',
        ]);

        $this->assertSame(['NIP', 'STATUS_PERKAWINAN'], array_keys($hasil));
    }

    public function test_jenis_rumus_membedakan_teks_dibungkus_dan_rumus_lain(): void
    {
        $this->assertSame('teks_dibungkus', AlasanImport::jenisRumus('="197902052025211049"'));
        $this->assertSame('rumus', AlasanImport::jenisRumus(self::VLOOKUP));
    }

    public function test_gagal_simpan_karena_rumus_dijelaskan_sebagai_rumus_excel(): void
    {
        $uraian = AlasanImport::uraikanGagal(self::PESAN_TERLALU_PANJANG, [
            'NAMA' => 'SUHARYANTI',
            'STATUS_PERKAWINAN' => self::VLOOKUP,
        ]);

        $this->assertSame(['rumus_excel'], $uraian['masalah']);
        $this->assertSame(['STATUS_PERKAWINAN'], $uraian['kolom_rumus']);
        $this->assertStringContainsString('STATUS_PERKAWINAN', $uraian['penyebab']);
        $this->assertStringContainsString('Paste Special', $uraian['saran']);
    }

    public function test_gagal_simpan_lain_tidak_dituduhkan_ke_rumus(): void
    {
        $uraian = AlasanImport::uraikanGagal(
            "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '123' for key 'pegawai_nik_unique'",
            ['NAMA' => 'X', 'STATUS_PERKAWINAN' => self::VLOOKUP]
        );

        $this->assertSame(['gagal_simpan'], $uraian['masalah']);
    }

    public function test_ringkas_saran_menyebut_kolom_yang_berisi_rumus(): void
    {
        $baris = [];
        foreach ([3, 5, 6] as $n) {
            $baris[] = (object) [
                'baris_ke' => $n,
                'pesan' => self::PESAN_TERLALU_PANJANG,
                'data_mentah' => ['NAMA' => "Pegawai {$n}", 'STATUS_PERKAWINAN' => self::VLOOKUP],
            ];
        }

        $saran = AlasanImport::ringkasSaran($baris);

        $this->assertCount(1, $saran);
        $this->assertSame('rumus_excel', $saran[0]['jenis']);
        $this->assertSame('STATUS_PERKAWINAN', $saran[0]['kolom']);
        $this->assertSame(3, $saran[0]['jumlah']);
    }
}