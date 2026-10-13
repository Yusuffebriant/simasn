<?php

namespace Tests\Feature;

use App\Support\RekapCache;
use Tests\TestCase;

class RekapCacheTest extends TestCase
{
    public function test_bump_mengganti_key_cache_semua_rekap(): void
    {
        $sebelum = RekapCache::key('paruh-waktu', '2026-10');

        RekapCache::bump();

        $sesudah = RekapCache::key('paruh-waktu', '2026-10');

        $this->assertNotSame($sebelum, $sesudah);
        $this->assertStringEndsWith('.paruh-waktu.2026-10', $sesudah);
    }

    public function test_key_stabil_selama_belum_ada_bump(): void
    {
        RekapCache::bump();

        $this->assertSame(
            RekapCache::key('agama', '2026-10'),
            RekapCache::key('agama', '2026-10')
        );
    }
}