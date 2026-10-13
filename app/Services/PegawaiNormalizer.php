<?php

namespace App\Services;

use App\Models\GolonganRuang;
use App\Models\Eselon;
use App\Models\Pendidikan;
use App\Models\Agama;
use App\Models\Instansi;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class PegawaiNormalizer
{
    public function isRowValid(array $row): array
    {
        $nip = $this->bersihkanRumus($row['NIP'] ?? null);
        $nik = $this->normalisasiNik($row['NIK'] ?? null);
        $nama = trim((string) ($row['NAMA'] ?? ''));

        if ($nama === '' || ($nip === '' && $nik === null)) {
            return [false, 'Nama kosong, atau NIP dan NIK sama-sama kosong'];
        }

        // Teks ini dibandingkan persis oleh PegawaiImport, jangan diubah.
        if (is_numeric($nama)) {
            return [false, 'Baris terdeteksi sebagai index palsu, bukan data pegawai'];
        }

        return [true, null];
    }

    public function normalisasiNik($val): ?string
    {
        if ($val === null) {
            return null;
        }

        // Kolom diformat Number di Excel -> float notasi ilmiah.
        if (is_int($val) || is_float($val)) {
            $val = number_format((float) $val, 0, '', '');
        }

        $nik = preg_replace('/\D/', '', (string) $val);

        return $nik === '' ? null : $nik;
    }

    /**
     * Nilai tak dikenal -> null, dan baris ditolak lewat import_batch_errors
     * (TIDAK lagi dipaksa jadi 'PNS').
     */
    public function resolveStatusKepegawaian(?string $raw): ?string
    {
        $key = preg_replace('/\s+/', ' ', strtoupper(trim((string) $raw)));

        return match ($key) {
            'PNS' => 'PNS',
            'PPPK' => 'PPPK',
            'PPPK PARUH WAKTU' => 'PPPK_PW',
            'NABAN', 'PEGAWAI TITIPAN' => 'LAINNYA',
            default => null,
        };
    }

    /**
     * '-' dan kosong -> null. Varian yang belum dikenal tetap disimpan
     * (huruf besar, spasi jadi underscore) supaya tidak ada data hilang
     * dan varian baru kelihatan saat dicek.
     */
    public function resolveStatusPerkawinan(?string $raw): ?string
    {
        $key = preg_replace('/\s+/', ' ', strtoupper(trim((string) $raw)));

        if ($key === '' || $key === '-') {
            return null;
        }

        return match ($key) {
            'NIKAH', 'KAWIN' => 'KAWIN',
            'BELUM NIKAH', 'BELUM KAWIN' => 'BELUM_KAWIN',
            'CERAI HIDUP' => 'CERAI_HIDUP',
            'CERAI MATI' => 'CERAI_MATI',
            'JANDA' => 'JANDA',
            'DUDA' => 'DUDA',
            default => str_replace(' ', '_', $key),
        };
    }

    public function normalisasiKodeGolongan(?string $raw): ?string
    {
        if (!$raw || trim($raw) === '-' || trim($raw) === '') {
            return null;
        }

        $parts = explode(',', $raw);
        $kode = trim(end($parts));

        return ($kode === '' || $kode === '-') ? null : $kode;
    }

    public function resolveGolonganRuang(
        ?string $raw,
        string $statusKepegawaian
    ): ?int {
        $kode = $this->normalisasiKodeGolongan($raw);

        if (!$kode) {
            return null;
        }

        $kelompok = in_array($statusKepegawaian, ['PPPK', 'PPPK_PW'], true)
            ? 'PPPK'
            : 'PNS';

        $golongan = GolonganRuang::firstOrCreate(
            [
                'kode' => $kode,
                'kelompok' => $kelompok,
            ],
            [
                'urutan' => 999,
            ]
        );

        return $golongan->id;
    }

    public function resolveEselon(?string $raw): int
    {
        $kode = trim((string) $raw);

        if ($kode === '' || $kode === '-') {
            $kode = 'Non Eselon';
        }

        return Eselon::firstOrCreate(
            ['kode' => $kode],
            ['urutan' => 999]
        )->id;
    }

    /**
     * Normalisasi kolom JENIS_KEDUDUKAN dari Excel mentah
     * ('JABATAN FUNGSIONAL' / 'JABATAN PELAKSANA' / 'STRUKTURAL')
     * menjadi nilai singkat yang disimpan di pegawai.jenis_kedudukan.
     *
     * Ini sumber kebenaran untuk klasifikasi Fungsional Umum vs
     * Fungsional Tertentu di RekapService::rekapJabatan() — BUKAN
     * ditebak dari teks kolom `jabatan`.
     */
    public function resolveJenisKedudukan(?string $raw): ?string
    {
        $key = strtoupper(trim((string) $raw));
        $key = preg_replace('/\s+/', ' ', $key);

        return match ($key) {
            'JABATAN FUNGSIONAL', 'FUNGSIONAL' => 'FUNGSIONAL',
            'JABATAN PELAKSANA', 'PELAKSANA' => 'PELAKSANA',
            'STRUKTURAL', 'JABATAN STRUKTURAL' => 'STRUKTURAL',
            default => null,
        };
    }

    /**
     * Normalisasi tingkat pendidikan dari data Excel
     * menjadi ID tabel pendidikan.
     *
     * Contoh:
     * S-3/Doktor -> S3 -> pendidikan_id = 7
     */
    public function resolvePendidikan(?string $raw): ?int
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        /*
         * Normalisasi awal:
         * - Ubah menjadi huruf besar
         * - Hilangkan spasi berlebih
         */
        $key = strtoupper(trim($raw));
        $key = preg_replace('/\s+/', ' ', $key);

        /*
         * Mapping berbagai variasi dari Excel
         * ke jenjang standar di database.
         */
        $map = [

            // ==========================================
            // SD
            // ==========================================
            'SD' => 'SD',
            'SEKOLAH DASAR' => 'SD',

            // ==========================================
            // SLTP
            // ==========================================
            'SMP' => 'SLTP',
            'SLTP' => 'SLTP',
            'SLTP KEJURUAN' => 'SLTP',

            // ==========================================
            // SLTA
            // ==========================================
            'SMA' => 'SLTA',
            'SMK' => 'SLTA',
            'SMA/SMK' => 'SLTA',
            'SLTA' => 'SLTA',
            'SLTA KEJURUAN' => 'SLTA',
            'SLTA KEGURUAN' => 'SLTA',

            // ==========================================
            // D I
            // ==========================================
            'D1' => 'D I',
            'D-1' => 'D I',
            'D I' => 'D I',
            'DIPLOMA I' => 'D I',

            // ==========================================
            // D II
            // ==========================================
            'D2' => 'D II',
            'D-2' => 'D II',
            'D II' => 'D II',
            'DIPLOMA II' => 'D II',

            // ==========================================
            // D III
            // ==========================================
            'D3' => 'D III',
            'D-3' => 'D III',
            'D III' => 'D III',
            'DIPLOMA III' => 'D III',
            'DIPLOMA III/SARJANA' => 'D III',

            // ==========================================
            // D IV
            // ==========================================
            'D4' => 'D IV',
            'D-4' => 'D IV',
            'D IV' => 'D IV',
            'D4/S1' => 'D IV',
            'DIPLOMA IV' => 'D IV',

            // ==========================================
            // S1
            // ==========================================
            'S1' => 'S1',
            'S-1' => 'S1',
            'S-1/SARJANA' => 'S1',
            'SARJANA' => 'S1',

            // ==========================================
            // S2
            // ==========================================
            'S2' => 'S2',
            'S-2' => 'S2',
            'S-2/MAGISTER' => 'S2',
            'MAGISTER' => 'S2',

            // ==========================================
            // S3
            // ==========================================
            'S3' => 'S3',
            'S-3' => 'S3',
            'S-3/DOKTOR' => 'S3',
            'DOKTOR' => 'S3',
        ];

        /*
         * Cari hasil mapping.
         */
        $jenjang = $map[$key] ?? null;

        /*
         * Kalau tidak dikenali, coba normalisasi
         * dengan menghapus karakter selain huruf/angka.
         *
         * Contoh:
         * S-3/Doktor
         * menjadi S3DOKTOR
         */
        if ($jenjang === null) {
            $compact = preg_replace('/[^A-Z0-9]/', '', $key);

            $compactMap = [
                'SD' => 'SD',
                'SEKOLAHDASAR' => 'SD',

                'SMP' => 'SLTP',
                'SLTP' => 'SLTP',
                'SLTPKEJURUAN' => 'SLTP',

                'SMA' => 'SLTA',
                'SMK' => 'SLTA',
                'SMASMK' => 'SLTA',
                'SLTA' => 'SLTA',
                'SLTAKEJURUAN' => 'SLTA',
                'SLTAKEGURUAN' => 'SLTA',

                'D1' => 'D I',
                'DIPLOMAI' => 'D I',

                'D2' => 'D II',
                'DIPLOMAII' => 'D II',

                'D3' => 'D III',
                'DIPLOMAIII' => 'D III',
                'DIPLOMAIIISARJANA' => 'D III',

                'D4' => 'D IV',
                'D4S1' => 'D IV',
                'DIPLOMAIV' => 'D IV',

                'S1' => 'S1',
                'S1SARJANA' => 'S1',
                'SARJANA' => 'S1',

                'S2' => 'S2',
                'S2MAGISTER' => 'S2',
                'MAGISTER' => 'S2',

                'S3' => 'S3',
                'S3DOKTOR' => 'S3',
                'DOKTOR' => 'S3',
            ];

            $jenjang = $compactMap[$compact] ?? null;
        }

        /*
         * Kalau tetap tidak dikenali,
         * jangan membuat data pendidikan baru.
         */
        if ($jenjang === null) {
            return null;
        }

        /*
         * Ambil ID dari tabel master pendidikan.
         *
         * Contoh:
         * S3 -> id 7
         */
        return Pendidikan::where('jenjang', $jenjang)->value('id');
    }

    public function resolveJenisKelamin(?string $raw): ?string
    {
        $val = strtoupper(trim((string) $raw));

        return match (true) {
            in_array($val, ['L', 'LAKI-LAKI', 'PRIA'], true) => 'L',
            in_array($val, ['P', 'PEREMPUAN', 'WANITA'], true) => 'P',
            default => null,
        };
    }

    public function resolveAgama(?string $raw): ?int
    {
        if (!$raw || trim($raw) === '' || trim($raw) === '-') {
            return null;
        }

        return Agama::firstOrCreate(
            ['nama' => trim($raw)],
            ['urutan' => 999]
        )->id;
    }

    public function resolveInstansi(?string $unit): ?int
    {
        if (!$unit || trim($unit) === '') {
            return null;
        }

        return Instansi::firstOrCreate(
            ['nama' => trim($unit)]
        )->id;
    }

    public function resolveStatusAktif(?string $raw): string
    {
        $val = strtoupper(trim((string) $raw));

        return match ($val) {
            'AKTIF' => 'aktif',
            'PENSIUN' => 'pensiun',
            'MUTASI KELUAR', 'MUTASI' => 'mutasi_keluar',
            'MENINGGAL' => 'meninggal',
            default => 'aktif',
        };
    }

    /**
     * Kolom Excel yang diformat sebagai teks sering berisi rumus
     * seperti ="08122711195". Maatwebsite membacanya apa adanya
     * (tanpa WithCalculatedFormulas), jadi bungkus =" " dibuang di sini.
     */
    private function bersihkanRumus($val): string
    {
        $val = trim((string) $val);

        if (str_starts_with($val, '=')) {
            $val = trim($val, '=" ');
        }

        return $val;
    }

    private function cleanZero($val): ?string
    {
        $val = $this->bersihkanRumus($val);

        return ($val === '' || $val === '0' || $val === '-')
            ? null
            : $val;
    }

    /**
     * Konversi nilai tanggal mentah dari Excel menjadi string 'Y-m-d'.
     *
     * FIX (bug "semua pegawai tanggal_lahir = 1970-01-01"):
     * Kalau kolom tanggal di Excel diformat sebagai "Number", Maatwebsite
     * Excel mengirim SERIAL NUMBER (mis. 25567), dan Carbon::parse() pada
     * angka itu jatuh ke Unix Epoch (1970-01-01) tanpa exception.
     *
     * Method ini menangani 3 bentuk input secara eksplisit:
     *  1) Object tanggal (kadang sudah dikembalikan oleh Maatwebsite)
     *  2) Serial number Excel (angka) -> lewat PhpSpreadsheet ExcelDate
     *  3) String tanggal biasa ("1990-05-14", "14/05/1990", dst)
     *
     * Guard: hasil 1970-01-01 padahal input tidak menyebut "1970"
     * dianggap gagal parse -> null (lebih mudah dideteksi daripada
     * data yang tampak valid tapi salah).
     */
    private function toDate($val): ?string
    {
        if ($val === null || $val === '' || $val === '-') {
            return null;
        }

        // Kasus 1: sudah berupa object tanggal.
        if ($val instanceof \DateTimeInterface) {
            return Carbon::instance($val)->format('Y-m-d');
        }

        // Kasus 2: serial number Excel (kolom diformat sebagai Number).
        // Range aman: > 0 dan < 60000 (~tahun 2064), supaya angka lain
        // yang salah masuk kolom ini (mis. NIK) tidak dipaksakan jadi tanggal.
        if (is_numeric($val)) {
            $num = (float) $val;

            if ($num > 0 && $num < 60000) {
                try {
                    return ExcelDate::excelToDateTimeObject($num)
                        ->format('Y-m-d');
                } catch (\Throwable $e) {
                    return null;
                }
            }

            return null;
        }

        // Kasus 3: string tanggal biasa.
        try {
            $tanggal = Carbon::parse($val);

            // Guard anti-epoch-diam-diam.
            if ($tanggal->format('Y-m-d') === '1970-01-01'
                && !str_contains((string) $val, '1970')) {
                return null;
            }

            return $tanggal->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function normalisasiBaris(array $row): array
    {
        $statusKepegawaian = $this->resolveStatusKepegawaian(
            $row['STATUS_KEPEGAWAIAN'] ?? null
        );

        if ($statusKepegawaian === null) {
            throw new \InvalidArgumentException(
                'STATUS_KEPEGAWAIAN tidak dikenali: '
                . ($row['STATUS_KEPEGAWAIAN'] ?? '(kosong)')
            );
        }

        $nip = $this->bersihkanRumus($row['NIP'] ?? null);
        $nik = $this->normalisasiNik($row['NIK'] ?? null);

        // Isi kolom sumber ini BEDA per status (terbukti dari selisihnya dengan
        // tanggal lahir di data):
        // - PNS dan PPPK_PW  : batas usia pensiun (lahir + 58/60 tahun)
        //                      -> tanggal_pensiun
        // - PPPK dan LAINNYA : tanggal kontrak -> tanggal_selesai_kontrak
        // tanggal_selesai_kontrak untuk PPPK_PW sengaja dikosongkan sampai ada
        // data kontrak asli (tanggal mulai/selesai dan nomor SK).
        $tanggalAkhir = $this->toDate(
            $row['TGL_PENSIUN_ATAU_TGL_SELESAI_KONTRAK'] ?? $row['TGL_PENSIUN'] ?? null
        );
        $isPensiun = in_array($statusKepegawaian, ['PNS', 'PPPK_PW'], true);

        $pegawai = [
            'nip' => $nip === '' ? null : $nip,   // WAJIB null, bukan '' (kolom unique)
            'nik' => $nik,
            'nama' => trim((string) ($row['NAMA'] ?? '')),

            'instansi_id' => $this->resolveInstansi($row['UNIT'] ?? null),

            'unit' => $row['UNIT'] ?? null,
            'sub_unit' => $row['SUB_UNIT'] ?? null,

            'jenis_kelamin' => $this->resolveJenisKelamin($row['JENIS_KELAMIN'] ?? null),

            'status_kepegawaian' => $statusKepegawaian,

            'golongan_ruang_id' => $this->resolveGolonganRuang(
                $row['PANGKAT_GOLRU'] ?? null,
                $statusKepegawaian
            ),

            'eselon_id' => $this->resolveEselon($row['ESELON'] ?? null),

            'agama_id' => $this->resolveAgama($row['AGAMA'] ?? null),

            // S-3/Doktor -> S3 -> pendidikan.id = 7
            'pendidikan_id' => $this->resolvePendidikan($row['TINGKAT_PENDIDIKAN'] ?? null),

            'jabatan' => $row['JABATAN'] ?? null,

            // Sumber kebenaran untuk Fungsional Umum vs Fungsional Tertentu
            // di rekap Jabatan — jangan ditebak dari teks `jabatan`.
            'jenis_kedudukan' => $this->resolveJenisKedudukan($row['JENIS_KEDUDUKAN'] ?? null),

            'tanggal_lahir' => $this->toDate($row['TGL_LAHIR'] ?? null),

            'tmt_pangkat' => $this->toDate($row['PANGKAT_TMT'] ?? null),

            'tanggal_pensiun' => $isPensiun ? $tanggalAkhir : null,
            'tanggal_selesai_kontrak' => $isPensiun ? null : $tanggalAkhir,

            'status_aktif' => $this->resolveStatusAktif($row['KEDUDUKAN_KEPEGAWAIAN'] ?? null),
        ];

        $rt = $this->cleanZero($row['RT'] ?? null);
        $rw = $this->cleanZero($row['RW'] ?? null);
        $rtRw = ($rt || $rw)
            ? 'RT ' . ($rt ?? '-') . '/RW ' . ($rw ?? '-')
            : null;

        $alamatParts = array_filter([
            $row['ALAMAT'] ?? null,

            $rtRw,

            $row['KELURAHAN'] ?? null,
            $row['KECAMATAN'] ?? null,
            $row['KABUPATEN'] ?? null,
            $row['PROPINSI'] ?? null,

            $this->cleanZero($row['KODEPOS'] ?? null),
        ]);

        $detail = [
            'nik' => $nik,
            'alamat' => implode(', ', $alamatParts),
            'hp' => $this->cleanZero($row['HP'] ?? null),
            'email' => $this->cleanZero($row['EMAIL'] ?? null),
            'status_perkawinan' => $this->resolveStatusPerkawinan(
                $row['STATUS_PERKAWINAN'] ?? null
            ),
        ];

        return compact('pegawai', 'detail');
    }
}