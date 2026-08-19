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
        ['jenjang' => 'SMP', 'urutan' => 2],
        ['jenjang' => 'SMA/SMK', 'urutan' => 3],
        ['jenjang' => 'D3', 'urutan' => 4],
        ['jenjang' => 'D4/S1', 'urutan' => 5],
        ['jenjang' => 'S2', 'urutan' => 6],
        ['jenjang' => 'S3', 'urutan' => 7],
    ];
    foreach ($data as $d) {
        Pendidikan::create($d);
    }
}
}
