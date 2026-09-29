# SIMASN — Rekapitulasi Data Kepegawaian

Aplikasi web untuk rekapitulasi data kepegawaian (dashboard, statistik, import data pegawai, dan pengelolaan akun).
Dibangun dengan Laravel (backend) dan React (tampilan).

Panduan ini ditulis untuk admin. Cukup ikuti langkahnya satu per satu.

---

## 1. Menjalankan aplikasi sehari-hari

Sebelum mulai, pastikan **MySQL sudah menyala** (database bernama `simasn`).

Buka **Terminal / Command Prompt di folder proyek ini**, lalu jalankan perintah berikut.
Setiap perintah dijalankan di **jendela terminal sendiri** dan **dibiarkan terbuka** selama aplikasi dipakai.

| Terminal | Perintah                 | Fungsi                                                     |
| -------- | ------------------------ | ---------------------------------------------------------- |
| 1        | `php artisan serve`      | Menyalakan server aplikasi (alamat: http://localhost:8000) |
| 2        | `npm run dev`            | Menyalakan tampilan (React)                                |
| 3        | `php artisan queue:work` | Memproses import data pegawai di latar belakang            |

Setelah ketiganya berjalan, buka **http://localhost:8000** di browser.

Penting:

- Kalau terminal 3 (`queue:work`) tidak berjalan, **import data pegawai akan berhenti di status menunggu** dan tidak pernah selesai.
- Untuk menghentikan, tekan `Ctrl + C` di masing-masing terminal.
- Kalau ada perubahan pengaturan di file `.env`, hentikan `queue:work` lalu jalankan lagi.

---

## 2. Lupa kata sandi

Aplikasi ini belum punya fitur "reset kata sandi" lewat email. Kata sandi diatur ulang lewat terminal.

### Cara A — Lewat akun admin lain (kalau ada)

Login dengan akun admin lain, buka halaman **Settings**, lalu ubah kata sandi akun yang lupa.

### Cara B — Lewat terminal (kalau hanya ada satu admin)

1. Pastikan MySQL menyala.
2. Buka terminal di folder proyek, lalu jalankan:

    ```bash
    php artisan tinker
    ```

3. Kalau lupa alamat email akunnya, lihat daftar email dulu:

    ```php
    App\Models\User::pluck('email');
    ```

4. Ganti kata sandi (ubah email dan kata sandi barunya, **tanda kutip tetap dipakai**):

    ```php
    $u = App\Models\User::where('email', 'email-admin@contoh.go.id')->first();
    $u->password = 'KataSandiBaru123';
    $u->save();
    ```

    - Kata sandi minimal **8 karakter**.
    - Ditulis apa adanya, tidak perlu di-hash. Sistem yang mengamankannya.
    - Kalau setelah baris pertama muncul `null`, artinya email salah atau tidak ditemukan. Periksa lagi ejaannya.

5. Keluar dari tinker:

    ```php
    exit
    ```

6. Login di aplikasi dengan kata sandi baru, lalu ganti sesuai keinginan lewat halaman **Settings**.

---

## 3. Masa berlaku login

Setelah login, admin otomatis keluar (logout) setelah **120 menit**. Ini diatur di file `.env`:

```
SANCTUM_EXPIRATION=120
```

Angkanya dalam **menit**. Contoh: `240` untuk 4 jam, `480` untuk 8 jam.
Setelah mengubahnya, jalankan:

```bash
php artisan config:clear
```

---

## 4. Masalah yang sering terjadi

| Masalah                                            | Penyebab dan solusi                                                  |
| -------------------------------------------------- | -------------------------------------------------------------------- |
| Halaman tidak bisa dibuka / error koneksi database | MySQL belum menyala. Nyalakan MySQL, lalu muat ulang halaman.        |
| Tampilan kosong atau tidak berubah                 | Terminal 2 (`npm run dev`) belum berjalan. Jalankan lagi.            |
| Import data pegawai menunggu terus                 | Terminal 3 (`php artisan queue:work`) belum berjalan. Jalankan lagi. |
| Tiba-tiba diminta login lagi                       | Masa berlaku login habis (lihat bagian 3). Login ulang.              |
| Perubahan di `.env` tidak berpengaruh              | Jalankan `php artisan config:clear`, lalu restart `queue:work`.      |

---

## 5. Pemasangan pertama kali (untuk developer / komputer baru)

Butuh PHP, Composer, Node.js, dan MySQL.

```bash
composer run setup        # install dependensi, buat .env, generate key, migrasi, build
php artisan db:seed       # isi data master (role, golongan, pendidikan, wilayah, dll.)
```

Lalu sesuaikan koneksi database di `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
Setelah itu jalankan aplikasi seperti pada bagian 1.
