<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Sistem ini cuma mengenal satu jenis akun login: admin (viewer tidak
     * punya akun, dashboard-nya publik). Supaya tidak ada lagi akun yang
     * "nyangkut" tanpa role — yang bikin menu Admin & Settings hilang
     * walau sudah login — setiap user baru otomatis di-assign role admin
     * di sini, terlepas dari lewat mana akun itu dibuat (UserController,
     * tinker, seeder lain, dll).
     */
    public function created(User $user): void
    {
        if (!$user->hasAnyRole(['admin'])) {
            $user->syncRoles(['admin']);
        }
    }
}
