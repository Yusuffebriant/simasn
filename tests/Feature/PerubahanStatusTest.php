<?php

namespace Tests\Unit;

use App\Support\PerubahanAtribut;
use PHPUnit\Framework\TestCase;

class PerubahanStatusTest extends TestCase
{
    public function test_arah_status_mengikuti_urutan_pppk_pw_pppk_pns(): void
    {
        $this->assertSame('naik', PerubahanAtribut::arahStatus('PPPK_PW', 'PPPK'));
        $this->assertSame('naik', PerubahanAtribut::arahStatus('PPPK_PW', 'PNS'));
        $this->assertSame('naik', PerubahanAtribut::arahStatus('PPPK', 'PNS'));
        $this->assertSame('turun', PerubahanAtribut::arahStatus('PNS', 'PPPK'));
        $this->assertSame('turun', PerubahanAtribut::arahStatus('PPPK', 'PPPK_PW'));
    }

    public function test_status_di_luar_urutan_dianggap_perubahan_lain(): void
    {
        $this->assertSame('lain', PerubahanAtribut::arahStatus('LAINNYA', 'PNS'));
        $this->assertSame('lain', PerubahanAtribut::arahStatus('PNS', null));
        $this->assertSame('lain', PerubahanAtribut::arahStatus('PNS', 'PNS'));
    }

    public function test_susun_perpindahan_status_memberi_label_dan_mengurutkan(): void
    {
        $hasil = PerubahanAtribut::susunPerpindahanStatus([
            ['sebelum' => 'PNS', 'sesudah' => 'PPPK', 'jumlah' => 1],
            ['sebelum' => 'PPPK', 'sesudah' => 'PNS', 'jumlah' => 2],
            ['sebelum' => 'PPPK_PW', 'sesudah' => 'PPPK', 'jumlah' => 5],
        ]);

        $this->assertSame('PPPK Paruh Waktu', $hasil[0]['dari']);
        $this->assertSame('naik', $hasil[0]['arah']);
        $this->assertSame(5, $hasil[0]['jumlah']);
        $this->assertSame('naik', $hasil[1]['arah']);
        $this->assertSame('turun', $hasil[2]['arah']);
        $this->assertSame('Turun status', $hasil[2]['arah_label']);
    }
}