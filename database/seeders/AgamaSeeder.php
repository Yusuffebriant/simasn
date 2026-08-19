<?php

namespace Database\Seeders;

use App\Models\Agama;
use Illuminate\Database\Seeder;

class AgamaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
{
    foreach (['Islam','Kristen','Katholik','Hindu','Budha','Konghucu'] as $nama) {
        Agama::create(['nama' => $nama]);
    }
}
}
