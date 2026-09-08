<?php

namespace Database\Seeders;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Role 'admin' SUDAH TIDAK DIPAKAI LAGI di sistem ini. Kendali yang
     * dulu dipegang admin sekarang diambil alih oleh super-admin dan
     * admin-instansi — keduanya levelnya SAMA (bukan dibedakan dari
     * instansi_id).
     *
     * User yang masih ke-assign role 'admin' (dari data lama) dipindah ke
     * KEDUA role sekaligus (super-admin + admin-instansi), lalu role
     * 'admin' dihapus dari tabel roles.
     */
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'super-admin']);
        Role::firstOrCreate(['name' => 'admin-instansi']);
        Role::firstOrCreate(['name' => 'viewer']);

        $roleAdminLama = Role::where('name', 'admin')->first();

        if ($roleAdminLama) {
            foreach ($roleAdminLama->users as $user) {
                $user->syncRoles(['super-admin', 'admin-instansi']);
            }
            $roleAdminLama->delete();
        }

        // Jaga-jaga supaya tidak ada akun yang "nyangkut" tanpa role sama
        // sekali (yang bikin menu Admin & Settings hilang walau sudah login),
        // misalnya akun lama dari sebelum Spatie Permission dipasang, atau
        // akun yang dibuat lewat seeder/tinker tanpa syncRoles().
        $userTanpaRole = User::query()->whereDoesntHave('roles')->get();

        foreach ($userTanpaRole as $user) {
            $user->syncRoles(['super-admin', 'admin-instansi']);
        }
    }
}
