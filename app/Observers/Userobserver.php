<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Role 'admin' TIDAK dipakai lagi di sistem ini. Kendali yang dulu
     * dipegang admin sekarang diambil alih oleh super-admin dan
     * admin-instansi — keduanya levelnya SAMA (tidak dibedakan otomatis
     * dari instansi_id), jadi setiap user baru langsung dapat kedua role
     * sekaligus.
     *
     * Supaya tidak ada akun yang "nyangkut" tanpa role — yang bikin menu
     * Admin & Settings hilang walau sudah login — setiap user baru otomatis
     * di-assign role di sini, terlepas dari lewat mana akun itu dibuat
     * (UserController, tinker, seeder lain, dll).
     */
    public function created(User $user): void
    {
        if (!$user->hasAnyRole(['super-admin', 'admin-instansi'])) {
            $user->syncRoles(['super-admin', 'admin-instansi']);
        }
    }
}
