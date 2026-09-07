<?php

namespace Database\Seeders;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Sistem ini cuma pakai 2 role:
     * - viewer : cuma boleh lihat dashboard
     * - admin  : boleh lihat dashboard, akses Admin, dan akses Settings
     */
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $roleLama = Role::whereIn('name', ['super-admin', 'admin-instansi', 'viewer'])->get();

        if ($roleLama->isNotEmpty()) {
            foreach ($roleLama as $role) {
                foreach ($role->users as $user) {
                    $user->syncRoles(['admin']);   // user yang masih ke-assign role lama → jadi admin
                }
                $role->delete();                    // role lama dihapus dari tabel roles
            }
        }
        // Jaga-jaga supaya tidak ada akun yang "nyangkut" tanpa role sama
        // sekali (yang bikin menu Admin & Settings hilang walau sudah login),
        // misalnya akun lama dari sebelum Spatie Permission dipasang, atau
        // akun yang dibuat lewat seeder/tinker tanpa syncRoles().
        // Semua akun yang belum punya role apapun otomatis dijadikan admin,
        // karena sistem ini memang cuma mengenal satu jenis akun login: admin.
        $userTanpaRole = User::query()->whereDoesntHave('roles')->get();

        foreach ($userTanpaRole as $user) {
            $user->syncRoles(['admin']);
        }
    }
}
