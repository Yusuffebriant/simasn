<?php

namespace Database\Seeders;

use App\Models\Eselon;
use Illuminate\Database\Seeder;

class EselonSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['kode' => 'Non Eselon', 'urutan' => 1],
            ['kode' => 'IV B', 'urutan' => 2],
            ['kode' => 'IV A', 'urutan' => 3],
            ['kode' => 'III B', 'urutan' => 4],
            ['kode' => 'III A', 'urutan' => 5],
            ['kode' => 'II B', 'urutan' => 6],
            ['kode' => 'II A', 'urutan' => 7],
        ];
        foreach ($data as $d) {
            Eselon::create($d);
        }
    }
}