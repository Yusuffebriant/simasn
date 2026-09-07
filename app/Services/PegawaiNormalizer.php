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
        $nip = trim((string) ($row['NIP'] ?? ''));
        $nama = trim((string) ($row['NAMA'] ?? ''));

        if ($nip === '' || $nama === '') {
            return [false, 'NIP atau Nama kosong'];
        }

        if (is_numeric($nama)) {
            return [false, 'Baris terdeteksi sebagai index palsu, bukan data pegawai'];
        }

        return [true, null];
    }

    public function normalisasiKodeGolongan(?string $raw): ?string
    {
        if (!$raw || trim($raw) === '-' || trim($raw) === '') {
            return null;
        }

        $parts = explode(',', $raw);
        $kode = trim(end($parts));

        return $kode === '' ? null : $kode;
    }

    public function resolveGolonganRuang(
        ?string $raw,
        string $statusKepegawaian
    ): ?int {
        $kode = $this->normalisasiKodeGolongan($raw);

        if (!$kode) {
            return null;
        }

        $kelompok = $statusKepegawaian === 'PPPK'
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

            // ==========================================
            // SLTA
            // ==========================================
            'SMA' => 'SLTA',
            'SMK' => 'SLTA',
            'SMA/SMK' => 'SLTA',
            'SLTA' => 'SLTA',
            'SLTA KEJURUAN' => 'SLTA',

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

                'SMA' => 'SLTA',
                'SMK' => 'SLTA',
                'SMASMK' => 'SLTA',
                'SLTA' => 'SLTA',
                'SLTAKEJURUAN' => 'SLTA',

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
        if (!$raw || trim($raw) === '') {
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

    private function cleanZero($val): ?string
    {
        $val = trim((string) $val);

        return ($val === '' || $val === '0' || $val === '-')
            ? null
            : $val;
    }

    /**
     * Konversi nilai tanggal mentah dari Excel menjadi string 'Y-m-d'.
     *
     * FIX (lihat riwayat bug "semua pegawai tanggal_lahir = 1970-01-01"):
     * Sebelumnya method ini hanya memanggil Carbon::parse($val) langsung.
     * Kalau kolom tanggal di Excel diformat sebagai "Number" (bukan
     * "Date"), Maatwebsite Excel mengirim nilai mentah berupa SERIAL
     * NUMBER (mis. 25567), bukan string tanggal. Carbon::parse() pada
     * angka semacam itu tidak melempar exception — dia malah
     * menginterpretasikannya sebagai timestamp/format lain yang salah
     * dan hasilnya konsisten jatuh ke Unix Epoch (1970-01-01). Karena
     * kolom tanggal_lahir nullable tanpa default DB, nilai yang salah
     * itu (bukan NULL) yang akhirnya tersimpan — makanya SEMUA baris
     * kena, bukan cuma sebagian.
     *
     * Fix ini menangani 3 bentuk input secara eksplisit:
     *  1) Object tanggal (kadang sudah dikembalikan oleh Maatwebsite)
     *  2) Serial number Excel (angka) -> dikonversi lewat
     *     PhpSpreadsheet's ExcelDate, bukan Carbon::parse()
     *  3) String tanggal biasa ("1990-05-14", "14/05/1990", dst)
     *
     * Ditambah guard: kalau hasil parse tetap jatuh ke 1970-01-01
     * padahal input mentahnya sama sekali tidak menyebut "1970",
     * dianggap gagal parse -> return null. Ini sengaja lebih
     * konservatif: data kosong (NULL) di database jauh lebih mudah
     * dideteksi & diperbaiki lewat re-import ketimbang data yang
     * terlihat "valid" tapi sebenarnya salah.
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
        // Range aman: > 0 (setelah 31 Des 1899) dan < 60000 (~tahun 2064),
        // supaya angka lain yang salah masuk kolom ini (mis. NIK) tidak
        // dipaksakan jadi tanggal.
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
        $statusKepegawaian = strtoupper(
            trim((string) ($row['STATUS_KEPEGAWAIAN'] ?? 'PNS'))
        );

        $statusKepegawaian = in_array(
            $statusKepegawaian,
            ['PNS', 'PPPK'],
            true
        )
            ? $statusKepegawaian
            : 'PNS';

        $pegawai = [
            'nip' => trim((string) ($row['NIP'] ?? '')),
            'nama' => trim((string) ($row['NAMA'] ?? '')),

            'instansi_id' => $this->resolveInstansi(
                $row['UNIT'] ?? null
            ),

            'unit' => $row['UNIT'] ?? null,
            'sub_unit' => $row['SUB_UNIT'] ?? null,

            'jenis_kelamin' => $this->resolveJenisKelamin(
                $row['JENIS_KELAMIN'] ?? null
            ),

            'status_kepegawaian' => $statusKepegawaian,

            'golongan_ruang_id' => $this->resolveGolonganRuang(
                $row['PANGKAT_GOLRU'] ?? null,
                $statusKepegawaian
            ),

            'eselon_id' => $this->resolveEselon(
                $row['ESELON'] ?? null
            ),

            'agama_id' => $this->resolveAgama(
                $row['AGAMA'] ?? null
            ),

            /*
             * Bagian penting:
             * S-3/Doktor -> S3 -> pendidikan.id = 7
             */
            'pendidikan_id' => $this->resolvePendidikan(
                $row['TINGKAT_PENDIDIKAN'] ?? null
            ),

            'jabatan' => $row['JABATAN'] ?? null,

            /*
             * Sumber kebenaran untuk Fungsional Umum vs Fungsional
             * Tertentu di rekap Jabatan — jangan lagi ditebak dari
             * teks `jabatan` pakai regex.
             */
            'jenis_kedudukan' => $this->resolveJenisKedudukan(
                $row['JENIS_KEDUDUKAN'] ?? null
            ),

            'tanggal_lahir' => $this->toDate(
                $row['TGL_LAHIR'] ?? null
            ),

            'tmt_pangkat' => $this->toDate(
                $row['PANGKAT_TMT'] ?? null
            ),

            'tanggal_pensiun' => $this->toDate(
                $row['TGL_PENSIUN'] ?? null
            ),

            'status_aktif' => $this->resolveStatusAktif(
                $row['KEDUDUKAN_KEPEGAWAIAN'] ?? null
            ),
        ];

        $alamatParts = array_filter([
            $row['ALAMAT'] ?? null,

            isset($row['RT'], $row['RW'])
                ? 'RT ' . $this->cleanZero($row['RT'])
                    . '/RW ' . $this->cleanZero($row['RW'])
                : null,

            $row['KELURAHAN'] ?? null,
            $row['KECAMATAN'] ?? null,
            $row['KABUPATEN'] ?? null,
            $row['PROPINSI'] ?? null,

            $this->cleanZero(
                $row['KODEPOS'] ?? null
            ),
        ]);

        $detail = [
            'nik' => $this->cleanZero(
                $row['NIK'] ?? null
            ),

            'alamat' => implode(', ', $alamatParts),

            'hp' => $this->cleanZero(
                $row['HP'] ?? null
            ),

            'email' => $this->cleanZero(
                $row['EMAIL'] ?? null
            ),
        ];

        return compact('pegawai', 'detail');
    }
}