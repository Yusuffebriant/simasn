<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GolonganRuang;

class GolonganRuangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pns = [
            // Golongan I
            ['kode' => 'I/a', 'urutan' => 1],
            ['kode' => 'I/b', 'urutan' => 2],
            ['kode' => 'I/c', 'urutan' => 3],
            ['kode' => 'I/d', 'urutan' => 4],

            // Golongan II
            ['kode' => 'II/a', 'urutan' => 5],
            ['kode' => 'II/b', 'urutan' => 6],
            ['kode' => 'II/c', 'urutan' => 7],
            ['kode' => 'II/d', 'urutan' => 8],

            // Golongan III
            ['kode' => 'III/a', 'urutan' => 9],
            ['kode' => 'III/b', 'urutan' => 10],
            ['kode' => 'III/c', 'urutan' => 11],
            ['kode' => 'III/d', 'urutan' => 12],

            // Golongan IV
            ['kode' => 'IV/a', 'urutan' => 13],
            ['kode' => 'IV/b', 'urutan' => 14],
            ['kode' => 'IV/c', 'urutan' => 15],
            ['kode' => 'IV/d', 'urutan' => 16],
            ['kode' => 'IV/e', 'urutan' => 17],
        ];

        foreach ($pns as $g) {
            GolonganRuang::create([
                'kode' => $g['kode'],
                'kelompok' => 'PNS',
                'urutan' => $g['urutan'],
            ]);
        }

        // PPPK
        $pppk = [
            'V/1a',
            'VI/1c',
            'VII/2c',
            'VIII/2d',
            'IX/3a',
            'X/3c',
            'XI/4a',
        ];

        foreach ($pppk as $i => $kode) {
            GolonganRuang::create([
                'kode' => $kode,
                'kelompok' => 'PPPK',
                'urutan' => 100 + $i,
            ]);
        }
    }
}