<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super-admin', 'admin-instansi', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}