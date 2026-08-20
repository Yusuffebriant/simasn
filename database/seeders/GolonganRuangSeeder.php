<?php

namespace Database\Seeders;

use App\Models\GolonganRuang;
use Illuminate\Database\Seeder;

class GolonganRuangSeeder extends Seeder
{
    public function run(): void
    {
        $pns = ['I/a','I/b','I/c','I/d','II/a','II/b','II/c','II/d','III/a','III/b','III/c','III/d','IV/a','IV/b','IV/c','IV/d','IV/e'];
        foreach ($pns as $i => $kode) {
            GolonganRuang::updateOrCreate(
                ['kode' => $kode, 'kelompok' => 'PNS'],
                ['urutan' => $i + 1]
            );
        }

        // PPPK: SIMPEG & laporan resmi cuma catat romawi polos, tanpa sub-grade
        $pppk = ['I','III','V','VII','IX','X','XI'];
        foreach ($pppk as $i => $kode) {
            GolonganRuang::updateOrCreate(
                ['kode' => $kode, 'kelompok' => 'PPPK'],
                ['urutan' => $i + 1]
            );
        }
    }
}