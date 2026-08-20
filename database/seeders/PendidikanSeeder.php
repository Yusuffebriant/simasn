<?php

namespace Database\Seeders;
use App\Models\Pendidikan;
use Illuminate\Database\Seeder;

class PendidikanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
{
    $data = [
        ['jenjang' => 'SD', 'urutan' => 1],
        ['jenjang' => 'SLTP', 'urutan' => 2],
        ['jenjang' => 'SLTA', 'urutan' => 3],
        ['jenjang' => 'D I', 'urutan' => 4],
        ['jenjang' => 'D II', 'urutan' => 5],
        ['jenjang' => 'D III', 'urutan' => 6],
        ['jenjang' => 'D IV', 'urutan' => 7],
        ['jenjang' => 'S1', 'urutan' => 8],
        ['jenjang' => 'S2', 'urutan' => 9],
        ['jenjang' => 'S3', 'urutan' => 10],
    ];
    foreach ($data as $d) {
        Pendidikan::updateOrCreate(['jenjang' => $d['jenjang']], ['urutan' => $d['urutan']]);
    }
}
}
