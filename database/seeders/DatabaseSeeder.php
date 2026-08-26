<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
{
    $this->call([
        RoleSeeder::class,
        GolonganRuangSeeder::class,
        EselonSeeder::class,
        AgamaSeeder::class,
        PendidikanSeeder::class,
        InstansiSeeder::class,
    ]);
}
}
