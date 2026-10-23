# SIMASN — Rekapitulasi Data Kepegawaian

Aplikasi web untuk rekapitulasi data kepegawaian: dashboard, statistik, import data pegawai, perubahan data per bulan, indeks kumulatif, dan pengelolaan akun.

- **Backend:** Laravel (API, Sanctum, antrean database)
- **Tampilan:** React (Vite + Tailwind)
- **Database:** MySQL, nama database `simasn`

Panduan ini ditulis untuk admin. Semua perintah dijalankan di terminal yang sudah berada di folder proyek (misalnya `E:\Laragon\www\simasn`).

## Daftar isi

1. [Persiapan sebelum mulai](#1-persiapan-sebelum-mulai)
2. [Menjalankan aplikasi sehari-hari](#2-menjalankan-aplikasi-sehari-hari)
3. [Alur import data bulanan](#3-alur-import-data-bulanan)
4. [Penjelasan perintah](#4-penjelasan-perintah)
5. [Memahami menu Indeks Kumulatif](#5-memahami-menu-indeks-kumulatif)
6. [Lupa kata sandi](#6-lupa-kata-sandi)
7. [Peran (role) pengguna](#7-peran-role-pengguna)
8. [Pengaturan `.env` yang penting](#8-pengaturan-env-yang-penting)
9. [Masalah yang sering terjadi](#9-masalah-yang-sering-terjadi)
10. [Hal yang masih perlu dicek](#10-hal-yang-masih-perlu-dicek)

---

## 1. Persiapan sebelum mulai

1. Nyalakan **MySQL** dari Laragon (klik *Start All*). Database yang dipakai: `simasn`.
2. Pastikan file `.env` berisi koneksi database yang benar:
   `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=simasn`, `DB_USERNAME`, `DB_PASSWORD`.
3. Buka terminal (Cmder) di folder proyek.

---

## 2. Menjalankan aplikasi sehari-hari

Butuh **3 terminal terpisah**. Ketiganya dibiarkan terbuka selama aplikasi dipakai.

| Terminal | Perintah                 | Fungsi                                                                        |
| -------- | ------------------------ | ----------------------------------------------------------------------------- |
| 1        | `php artisan serve`      | Menyalakan server aplikasi di http://localhost:8000                           |
| 2        | `npm run dev`            | Menyalakan tampilan React (Vite), memuat ulang otomatis saat kode berubah     |
| 3        | `php artisan queue:work` | Memproses antrean latar belakang, terutama **import data pegawai**            |

Setelah ketiganya berjalan, buka **http://localhost:8000** di browser. Untuk berhenti, tekan `Ctrl + C` di tiap terminal.

**Penting:**

- Kalau terminal 3 (`queue:work`) mati, import pegawai akan berhenti di status *menunggu* dan tidak pernah selesai (antrean memakai `QUEUE_CONNECTION=database`).
- Setiap kali `.env` diubah, hentikan `queue:work` lalu jalankan lagi, karena worker menyimpan konfigurasi lama di memori.

---

## 3. Alur import data bulanan

1. Login sebagai admin, buka halaman import, lalu unggah file Excel/CSV data pegawai untuk periode (bulan) tersebut.
2. Pastikan `queue:work` sedang berjalan. Import diproses di latar belakang.
3. Aturan pengaman di sistem:
   - Import bersifat **ganti total**: pegawai lama yang tidak ada di file baru dihapus dari data aktif.
   - Kalau jumlah baris valid turun lebih dari **20%** dibanding data sekarang, upload ditahan dan admin harus mengonfirmasi (biasanya tandanya file salah atau terpotong). Ambang ada di `config/import.php` dan bisa diubah lewat `IMPORT_AMBANG_PENURUNAN_PERSEN` di `.env`.
   - Periode yang lebih lama dari periode aktif ditolak.
   - Status kepegawaian yang tidak terdeteksi masuk ke kelompok **Lainnya**.
4. Setelah import selesai, sistem otomatis membuat snapshot rekap dan snapshot individual pegawai, lalu mencatat perubahan data dibanding bulan sebelumnya (dilihat di halaman perubahan pegawai per bulan).
5. Menghapus riwayat import menghapus data terkait import itu. File cadangan CSV di folder `backups/` **tidak** ikut terhapus. Batch yang sedang aktif tidak boleh dihapus.

---

## 4. Penjelasan perintah

### 4.1 Perintah harian

| Perintah                 | Fungsi                                                                                                   |
| ------------------------ | -------------------------------------------------------------------------------------------------------- |
| `php artisan serve`      | Menjalankan server Laravel lokal di port 8000                                                            |
| `npm run dev`            | Menjalankan Vite (mode pengembangan) untuk tampilan React                                                |
| `npm run build`          | Membangun tampilan untuk produksi (hasil di `public/build`). Dipakai bila tidak menjalankan `npm run dev` |
| `php artisan queue:work` | Worker antrean: mengerjakan job seperti `ProcessPegawaiImport`                                           |
| `php artisan config:clear` | Menghapus cache konfigurasi supaya perubahan `.env` terbaca                                            |
| `php artisan cache:clear`  | Mengosongkan cache aplikasi (berguna kalau angka rekap terasa tidak ikut berubah)                      |

### 4.2 Pemasangan pertama dan setelah `git pull`

| Perintah                   | Fungsi                                                                                                                              |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| `composer run setup`       | Paket sekali jalan: `composer install`, salin `.env.example` ke `.env` bila belum ada, `key:generate`, `migrate --force`, `npm install`, `npm run build` |
| `composer install`         | Memasang paket PHP sesuai `composer.lock`                                                                                           |
| `npm install`              | Memasang paket JavaScript sesuai `package.json`                                                                                     |
| `php artisan key:generate` | Membuat `APP_KEY` di `.env` (wajib untuk instalasi baru)                                                                            |
| `php artisan migrate`      | Membuat/menambah tabel sesuai file migrasi. Aman dijalankan berulang, hanya migrasi baru yang diproses                              |
| `php artisan db:seed`      | Mengisi data master: role, golongan, eselon, agama, pendidikan, instansi, alamat fasilitas, dan wilayah                             |

Urutan yang biasa dipakai setelah `git pull`:

```bash
composer install
php artisan migrate
npm install
php artisan config:clear
npm run build   # atau cukup jalankan npm run dev
```

Kalau database masih kosong, jalankan `php artisan migrate` lalu `php artisan db:seed`.

Di dalam proyek juga ada berkas `simasn.sql` (dump MySQL tanggal 9 Oktober) yang bisa diimpor lewat HeidiSQL/phpMyAdmin bila ingin memulai dari data lama. Dump ini bukan data terbaru, jadi tetap jalankan `php artisan migrate` sesudahnya.

### 4.3 Perintah buatan khusus SIMASN

| Perintah                                         | Fungsi                                                                                                          | Kapan dipakai                                                                                  |
| ------------------------------------------------ | --------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `php artisan pegawai:snapshot`                   | Membuat snapshot individual pegawai dari data saat ini (periode aktif) dan menghitung riwayat perubahannya      | Normalnya otomatis tiap import selesai. Manual hanya untuk periode yang diimport sebelum fitur snapshot ada |
| `php artisan pegawai:snapshot 2026-10`           | Sama, untuk periode tertentu (format `YYYY-MM`). Harus sama dengan periode aktif                                | Bila ingin menentukan periode secara eksplisit                                                 |
| `php artisan pegawai:snapshot 2026-10 --paksa`   | Mengizinkan periode yang bukan periode aktif                                                                    | **Hati-hati.** Tabel pegawai hanya berisi data periode aktif, jadi memberi label periode lain sama dengan mengarang riwayat |
| `php artisan pegawai:snapshot --hitung-ulang`    | Menghitung ulang riwayat perubahan semua periode berurutan, tanpa membuat snapshot baru                         | Kalau riwayat perubahan terlihat tidak sinkron                                                 |
| `php artisan import:bersihkan-sisa`              | Menghapus **permanen** data pegawai dan file upload sisa yang tidak terhubung ke riwayat import mana pun (meminta konfirmasi) | Membersihkan data yatim setelah riwayat import dihapus                       |
| `php artisan import:bersihkan-sisa --force`      | Sama, tanpa pertanyaan konfirmasi                                                                               | Hanya jika yakin. Tidak bisa dibatalkan                                                        |
| `php artisan rekap:bekukan`                      | Membekukan rekap kondisi data saat ini sebagai snapshot satu periode (default periode aktif; bisa `rekap:bekukan 2026-10`) | Tidak wajib. Perubahan data pegawai sudah tercatat otomatis tiap import              |

### 4.4 Perawatan dan pengembangan

| Perintah                          | Fungsi                                                     |
| --------------------------------- | ---------------------------------------------------------- |
| `php artisan tinker`              | Konsol interaktif Laravel, dipakai misalnya untuk reset kata sandi |
| `php artisan migrate:status`      | Melihat migrasi mana yang sudah/belum dijalankan           |
| `php artisan route:list`          | Menampilkan semua rute (endpoint API)                      |
| `php artisan test` / `composer test` | Menjalankan pengujian otomatis (PHPUnit)                |
| `php artisan pail`                | Menampilkan log aplikasi secara langsung di terminal       |
| `vendor/bin/pint`                 | Merapikan gaya penulisan kode PHP                          |

---

## 5. Memahami menu Indeks Kumulatif

Bagian ini menjelaskan arti angka dan istilah di menu Indeks Kumulatif dengan bahasa sehari-hari.

### 5.1 Gambaran besar: tiga tab, tiga pertanyaan

| Tab                      | Pertanyaan yang dijawab                                                                   | Yang dihitung                                  |
| ------------------------ | ----------------------------------------------------------------------------------------- | ---------------------------------------------- |
| Jumlah per Kategori      | Berapa jumlah pegawai di tiap kelompok (agama, pendidikan, golongan, JFT, JFU, status) tiap bulan? | Total per bulan                        |
| Perpindahan per Instansi | Berapa jumlah pegawai tiap instansi, dan kenapa jumlahnya berubah?                        | Per instansi, lengkap dengan pindah, baru, berhenti |
| Perubahan Individual     | Siapa saja yang datanya berubah antara dua bulan, dan apa yang berubah?                   | Orang per orang                                |

> **Peringatan penting.** Tab *Jumlah per Kategori* hanya membandingkan total. Kalau JFT turun 3 dan JFU naik 3, belum tentu ada 3 orang yang pindah jabatan. Bisa juga ada pegawai baru atau yang pensiun. Untuk melihat siapa yang berpindah, buka tab *Perubahan Individual*.

### 5.2 Istilah yang sering membingungkan

| Istilah                                      | Artinya                                                                                                                         |
| -------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| Data bulanan (di kode disebut *snapshot*)    | Salinan data semua pegawai pada satu bulan. Dibuat otomatis setiap kali Excel bulanan diupload.                                  |
| Indeks                                       | Perbandingan dengan bulan awal. Bulan awal dianggap 100. Angka 110 berarti naik 10%, angka 90 berarti turun 10%.                 |
| Naik/Turun per Bulan                         | Jumlah bulan ini dikurangi jumlah bulan lalu.                                                                                    |
| Naik/Turun sejak Awal                        | Jumlah bulan ini dikurangi jumlah bulan awal. Angkanya menumpuk.                                                                 |
| Hasil akhir saja (kondisi)                   | Membandingkan keadaan pegawai di bulan awal dengan bulan akhir. Kalau berubah lalu kembali seperti semula, tidak dihitung.       |
| Semua perubahan tiap bulan (kejadian)        | Mencatat setiap perubahan dari bulan ke bulan, termasuk yang kemudian kembali seperti semula.                                    |
| Perlu dicek                                  | Sistem ragu. Misalnya data sebelumnya kosong lalu terisi, ada bulan tanpa data di antaranya, atau pegawai dicocokkan lewat NIK.  |
| Tidak ada lagi di data                       | Pegawai ada di bulan awal tapi tidak ada di bulan akhir. Belum tentu keluar: bisa pensiun, pindah, data terlewat, atau NIP berubah. |
| Baru muncul di data                          | Pegawai tidak ada di bulan awal tapi ada di bulan akhir.                                                                         |
| TMT pangkat                                  | Tanggal mulai berlakunya pangkat. Karena itu satu kenaikan pangkat menghasilkan dua baris: pangkat dan TMT-nya.                  |
| Berlaku sejak                                | Tanggal efektif perubahan. Hanya diisi kalau Excel menyediakannya (pangkat dan TMT). Tidak diambil dari tanggal upload.          |

### 5.3 Cara menghitung angka di tab Jumlah per Kategori

Contoh angka (bukan data asli):

| Bulan           | Jumlah | Naik/turun per bulan | Naik/turun sejak awal | Indeks |
| --------------- | ------ | -------------------- | --------------------- | ------ |
| Bulan 1 (awal)  | 50     | –                    | 0                     | 100,0  |
| Bulan 2         | 55     | +5                   | +5                    | 110,0  |
| Bulan 3         | 45     | −10                  | −5                    | 90,0   |
| Bulan 4         | 60     | +15                  | +10                   | 120,0  |

Rumus untuk Bulan 3: `45 − 55 = −10` (per bulan), `45 − 50 = −5` (sejak awal), `45 ÷ 50 × 100 = 90,0` (indeks).

Tanda "–" berarti data bulan itu belum ada. Itu **bukan nol**, dan bulannya dilewati dalam hitungan.

### 5.4 Naik dan turun status

Urutan tingkat yang dipakai sistem: **PPPK Paruh Waktu → PPPK → PNS**.

- Pindah ke tingkat lebih tinggi disebut **Naik status** (misalnya PPPK Paruh Waktu → PPPK, PPPK → PNS, PPPK Paruh Waktu → PNS).
- Pindah ke tingkat lebih rendah disebut **Turun status**.
- Status **Lainnya** (NABAN, Pegawai Titipan) tidak punya tingkat, jadi perubahannya dicatat sebagai **Perubahan lain**.

Urutan ini adalah asumsi saat pembuatan fitur. Kalau urutan di instansi berbeda, ubah konstanta `STATUS_TINGKAT` di `app/Support/PerubahanAtribut.php`.

Contoh: pegawai A berstatus PPPK Paruh Waktu di Januari dan PPPK di Maret. Pada perbandingan Januari ke Maret, A dihitung satu kali sebagai naik status. Tombol **Lihat nama** di blok *Perubahan status kepegawaian* menampilkan nama-namanya.

### 5.5 Bagaimana sistem mengenali orang yang sama antar bulan

1. Pegawai dicocokkan lewat **NIP**, bukan nama.
2. Kalau NIP tidak ada di bulan berikutnya, sistem mencari pegawai yang "hilang" dan yang "baru" dengan **NIK** yang sama. Kalau cocok dan tidak ambigu, keduanya dianggap satu orang. Perubahan NIP dicatat, dan semua barisnya ditandai *perlu dicek*.
3. Kasus yang umum: PPPK yang diangkat menjadi PNS mendapat NIP baru. Dengan pencocokan NIK, ia tetap terbaca sebagai naik status.

Pencocokan gagal kalau NIK kosong di salah satu bulan, atau NIK yang sama muncul di lebih dari satu baris. Sistem sengaja tidak menebak: pegawai itu muncul sebagai satu orang hilang dan satu orang baru, keduanya perlu dicek.

Di cadangan data terbesar yang diunggah (6.367 pegawai), NIK terisi untuk semua PNS, PPPK, dan PPPK Paruh Waktu.

### 5.6 Pilihan "Sertakan PPPK Paruh Waktu"

Bawaannya **tidak dicentang**, jadi angka lama tetap PNS + PPPK dan sama dengan menu Rekapitulasi.

- **Perpindahan per Instansi.** Kalau dicentang, Paruh Waktu ikut dihitung. Pegawai yang naik dari Paruh Waktu ke PPPK atau PNS tetap dihitung di instansinya. Tanpa centang, perpindahan status seperti itu terbaca sebagai "berhenti" atau "baru".
- **Jumlah per Kategori.** Centang hanya berlaku untuk **Agama** dan **Pendidikan**. Golongan, JFT, dan JFU tetap PNS + PPPK (dugaan: Paruh Waktu biasanya tidak punya golongan atau jabatan fungsional, belum dicek ke data).
- **Sumber data berbeda.** Pendidikan Paruh Waktu diambil dari arsip rekap Paruh Waktu. Agama Paruh Waktu dibaca dari data pegawai per orang. Bulan yang datanya tidak ada ditulis "–" di Agama dan Pendidikan, bukan angka tanpa Paruh Waktu, supaya dua jenis angka tidak tercampur. Bulan yang masih aktif dihitung langsung dari data pegawai.

### 5.7 Latihan membaca baris di tabel Perubahan Individual

| Baris                                                               | Artinya                                                                                                              |
| ------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| Tidak ada lagi di data bulan akhir (perlu dicek)                    | Pegawai ada di Januari tapi tidak ada di Februari. Penyebabnya belum bisa dipastikan. Kalau NIP kosong, makin sulit dicocokkan. |
| Baru muncul di data (terdeteksi)                                    | Pegawai tidak ada di Januari tapi ada di Februari.                                                                   |
| Pangkat/golongan IV/a menjadi IV/b, berlaku sejak 2027-02-01        | Kenaikan pangkat biasa. Baris *TMT pangkat* di bawahnya adalah tanggal mulai berlakunya.                             |
| Kedudukan pegawai: aktif menjadi pensiun                            | Pegawai berstatus pensiun di bulan akhir.                                                                            |
| Instansi atau unit kerja berubah                                    | Pegawai pindah tempat kerja. Di tab *Perpindahan per Instansi* tercatat sebagai pindah keluar dan pindah masuk.      |

### 5.8 File yang terkait fitur ini

- **Tampilan:** `PerubahanIndividual.jsx`, `IndeksKumulatif.jsx`, `MutasiInstansi.jsx`
- **Logika:** `PerubahanAtribut.php`, `PerbandinganPeriodeService.php`, `IndeksKumulatifService.php`, `MutasiInstansiService.php`, `PegawaiSnapshotService.php`
- **Pendukung:** `IndeksKumulatifController.php`, `IndeksKumulatifExport.php`, `PerbandinganPeriodeExport.php`, tes `PerubahanStatusTest.php`

---

## 6. Lupa kata sandi

Belum ada fitur reset lewat email. Ada dua cara.

**Cara A — lewat akun admin lain.** Login dengan akun admin lain, buka halaman **Settings**, lalu ubah kata sandi akun yang lupa.

**Cara B — lewat terminal** (kalau hanya ada satu admin):

```bash
php artisan tinker
```

```php
App\Models\User::pluck('email');   // lihat daftar email
$u = App\Models\User::where('email', 'email-admin@contoh.go.id')->first();
$u->password = 'KataSandiBaru123';  // minimal 8 karakter, tulis apa adanya
$u->save();
exit
```

- Kata sandi ditulis apa adanya, tidak perlu di-hash. Sistem yang mengamankannya.
- Kalau setelah baris `$u = ...` muncul `null`, berarti email tidak ditemukan. Cek lagi ejaannya.
- Setelah login dengan kata sandi baru, ganti sesuai keinginan lewat halaman **Settings**.

---

## 7. Peran (role) pengguna

- `super-admin` dan `admin-instansi`: dua peran dengan level yang sama, memegang kendali penuh atas import, rekap, perubahan pegawai, indeks kumulatif, data referensi, dan manajemen akun.
- Role lama `admin` sudah tidak dipakai. Akun yang masih memakainya otomatis dipindah ke kedua peran di atas saat `RoleSeeder` dijalankan.

---

## 8. Pengaturan `.env` yang penting

| Pengaturan                                  | Fungsi                                                                          |
| ------------------------------------------- | ------------------------------------------------------------------------------- |
| `DB_*`                                      | Koneksi MySQL (database `simasn`)                                               |
| `QUEUE_CONNECTION=database`                 | Antrean disimpan di database, sehingga `queue:work` wajib berjalan              |
| `SANCTUM_EXPIRATION=120`                    | Masa login dalam **menit** (contoh: `240` = 4 jam, `480` = 8 jam). Setelah diubah, jalankan `php artisan config:clear` |
| `IMPORT_AMBANG_PENURUNAN_PERSEN=20`         | Ambang penurunan jumlah pegawai yang menahan import                             |
| `VITE_API_BASE_URL=http://localhost:8000/api` | Alamat API yang dipakai tampilan React                                        |

Jangan membagikan file `.env` ke orang lain atau mengunggahnya ke repository, karena berisi kunci aplikasi dan kredensial database.

---

## 9. Masalah yang sering terjadi

| Gejala                                                | Penyebab dan solusi                                                         |
| ----------------------------------------------------- | --------------------------------------------------------------------------- |
| Halaman error koneksi database                        | MySQL belum menyala. Nyalakan di Laragon lalu muat ulang                    |
| Tampilan kosong atau tidak berubah                    | `npm run dev` belum berjalan, atau jalankan `npm run build`                 |
| Import menunggu terus                                 | `php artisan queue:work` belum berjalan                                     |
| Tiba-tiba diminta login lagi                          | Masa login habis (default 120 menit). Login ulang                           |
| Perubahan `.env` tidak berpengaruh                    | `php artisan config:clear`, lalu restart `queue:work`                       |
| Error tabel/kolom tidak ditemukan setelah `git pull`  | Ada migrasi baru. Jalankan `php artisan migrate`                            |
| Error paket/class tidak ditemukan setelah `git pull`  | Jalankan `composer install` dan `npm install`                               |
| Import ditahan dengan peringatan penurunan jumlah     | Periksa apakah file benar dan lengkap, lalu konfirmasi bila memang sesuai   |

---

## 10. Hal yang masih perlu dicek

- Kode PHP untuk fitur Indeks Kumulatif belum dijalankan saat pembuatan (PHP tidak tersedia di lingkungan penyusun). Sintaks JSX sudah dicek. Jalankan `php artisan test` dan coba semua tab di browser.
- Uji satu pegawai yang benar-benar diangkat dari PPPK menjadi PNS untuk memastikan pencocokan NIK berfungsi.
- Periksa ejaan nama agama dan jenjang pendidikan antara data Paruh Waktu dan ASN. Ejaan yang berbeda membuat barisnya muncul terpisah.
- Teks lama di baris riwayat yang sudah tersimpan baru berubah kalau riwayat dihitung ulang (`php artisan pegawai:snapshot --hitung-ulang`).
