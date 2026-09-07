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
        // Jaga-jaga supaya instalasi baru tidak berakhir dengan akun tanpa
        // role sama sekali (yang bikin semua fitur ke-block middleware role).
        // Kalau belum ada satupun user yang punya role, jadikan user paling
        // lama terdaftar sebagai admin.
        $adaUserBerRole = User::query()->whereHas('roles')->exists();

        if (!$adaUserBerRole) {
            $userPertama = User::query()->oldest('id')->first();

            if ($userPertama) {
                $userPertama->syncRoles(['admin']);
            }
        }
    }
}
