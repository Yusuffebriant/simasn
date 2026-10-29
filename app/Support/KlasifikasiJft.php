<?php

namespace App\Support;

/**
 * Satu-satunya tempat aturan pengelompokan Jabatan Fungsional Tertentu (JFT)
 * ke tiga kelompok kartu Ringkasan Dashboard: pendidikan, kesehatan, teknis.
 *
 * Aturan MENGIKUTI DASHBOARD RESMI:
 *  - Pengawas Sekolah dan Pamong Belajar  -> pendidikan.
 *  - Medik Veteriner, Paramedik Veteriner, Tenaga Sanitasi Lingkungan
 *    -> teknis (di dashboard resmi kolom Rumpun JF mereka kosong, bukan
 *    "KESEHATAN").
 *  - Jabatan yang tidak dikenal -> teknis, DAN dilaporkan lewat
 *    PeringatanDataService::cekJabatanBelumTerdaftar() lengkap dengan nama
 *    pegawainya.
 *
 * Cara menambah jabatan baru (tidak perlu mengubah logika):
 *  - bidang pendidikan  : tambah ke PENDIDIKAN_AWALAN / PENDIDIKAN_MENGANDUNG
 *  - bidang kesehatan   : tambah ke KATA_KUNCI_KESEHATAN
 *  - bidang teknis      : tambah ke TEKNIS_TERDAFTAR (awalan nama jabatan)
 * Setelah ditambah, catatan "jabatan belum terdaftar" untuk jabatan itu
 * hilang sendiri.
 */
class KlasifikasiJft
{
    public const PENDIDIKAN = 'pendidikan';
    public const KESEHATAN = 'kesehatan';
    public const TEKNIS = 'teknis';

    /** Nama jabatan DIAWALI kata ini -> pendidikan. */
    public const PENDIDIKAN_AWALAN = [
        'GURU',
    ];

    /** Nama jabatan MENGANDUNG kata ini -> pendidikan. */
    public const PENDIDIKAN_MENGANDUNG = [
        'DOSEN',
        'PENGAWAS SEKOLAH',
        'PAMONG BELAJAR',
    ];

    /**
     * Nama jabatan MENGANDUNG kata ini -> kesehatan.
     * Sengaja TIDAK memuat MEDIK VETERINER, PARAMEDIK VETERINER, dan
     * SANITASI LINGKUNGAN (ikut dashboard resmi: teknis).
     */
    public const KATA_KUNCI_KESEHATAN = [
        'DOKTER',
        'PERAWAT',
        'BIDAN',
        'APOTEKER',
        'PEREKAM MEDIS',
        'PRANATA LABORATORIUM KESEHATAN',
        'NUTRISIONIS',
        'EPIDEMIOLOG',
        'ADMINISTRATOR KESEHATAN',
        'PENYULUH KESEHATAN',
        'RADIOGRAFER',
        'TEKNISI TRANSFUSI DARAH',
        'SANITARIAN',
        'TERAPIS GIGI',
        'PROMOSI KESEHATAN',
        'FISIOTERAPIS',
        'FISIKAWAN MEDIS',
        'PSIKOLOG KLINIS',
        'TEKNISI ELEKTROMEDIS',
        'TERAPIS WICARA',
        'PENATA ANESTESI',
        'OKUPASI TERAPIS',
        'PEMBIMBING KESEHATAN KERJA',
    ];

    /**
     * Jabatan teknis yang sudah dikenal (dicocokkan dari AWAL nama jabatan,
     * tanpa jenjang seperti "AHLI MUDA"/"TERAMPIL"). Jabatan di luar daftar
     * ini, di luar pendidikan, dan di luar kesehatan, dihitung teknis
     * tetapi ditandai "belum terdaftar".
     */
    public const TEKNIS_TERDAFTAR = [
        'ANALIS HUKUM',
        'ANALIS KEBENCANAAN',
        'ANALIS KEBIJAKAN',
        'ANALIS KEUANGAN PUSAT DAN DAERAH',
        'ANALIS PERDAGANGAN',
        'ANALIS SUMBER DAYA MANUSIA APARATUR',
        'ARSIPARIS',
        'ASESOR SUMBER DAYA MANUSIA APARATUR',
        'ASISTEN PERISALAH LEGISLATIF',
        'AUDITOR',
        'MEDIATOR HUBUNGAN INDUSTRIAL',
        'MEDIK VETERINER',
        'PARAMEDIK VETERINER',
        'TENAGA SANITASI LINGKUNGAN',
        'OPERATOR SISTEM INFORMASI ADMINISTRASI KEPENDUDUKAN',
        'PAMONG BUDAYA',
        'PEKERJA SOSIAL',
        'PEMADAM KEBAKARAN',
        'PEMBINA INDUSTRI',
        'PEMBINA JASA KONSTRUKSI',
        'PENATA KELOLA BANGUNAN GEDUNG DAN KAWASAN PERMUKIMAN',
        'PENATA KELOLA JALAN DAN JEMBATAN',
        'PENATA LAKSANA BARANG',
        'PENATA PERIZINAN',
        'PENATA RUANG',
        'PENELITI',
        'PENERA',
        'PENGANTAR KERJA',
        'PENGAWAS KOPERASI',
        'PENGAWAS LINGKUNGAN HIDUP',
        'PENGAWAS MUTU HASIL PERTANIAN',
        'PENGAWAS PENYELENGGARAAN URUSAN PEMERINTAHAN',
        'PENGAWAS PERDAGANGAN',
        'PENGELOLA PENGADAAN BARANG/JASA',
        'PENGELOLA SUMBER DAYA AIR',
        'PENGENDALI DAMPAK LINGKUNGAN',
        'PENGGERAK SWADAYA MASYARAKAT',
        'PENGUJI KENDARAAN BERMOTOR',
        'PENILIK',
        'PENYULUH HUKUM',
        'PENYULUH PERINDUSTRIAN DAN PERDAGANGAN',
        'PENYULUH SOSIAL',
        'PERANCANG PERATURAN PERUNDANG-UNDANGAN',
        'PERENCANA',
        'POLISI PAMONG PRAJA',
        'PRANATA HUBUNGAN MASYARAKAT',
        'PRANATA KOMPUTER',
        'PRANATA SUMBER DAYA MANUSIA APARATUR',
        'PUSTAKAWAN',
        'SANDIMAN',
        'STATISTISI',
        'TEKNIK JALAN DAN JEMBATAN',
        'TEKNIK PENGAIRAN',
    ];

    /** Huruf besar, spasi ganda dirapikan. */
    public static function normalisasi(?string $jabatan): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $jabatan)));
    }

    /** @return 'pendidikan'|'kesehatan'|'teknis' */
    public static function kelompok(?string $jabatan): string
    {
        $j = self::normalisasi($jabatan);

        if (self::pendidikan($j)) {
            return self::PENDIDIKAN;
        }

        if (self::kesehatan($j)) {
            return self::KESEHATAN;
        }

        return self::TEKNIS;
    }

    /**
     * Apakah jabatan ini dikenal sistem (masuk salah satu dari tiga daftar)?
     * Jabatan kosong dianggap belum terdaftar.
     */
    public static function terdaftar(?string $jabatan): bool
    {
        $j = self::normalisasi($jabatan);

        if ($j === '') {
            return false;
        }

        if (self::pendidikan($j) || self::kesehatan($j)) {
            return true;
        }

        foreach (self::TEKNIS_TERDAFTAR as $awalan) {
            if (str_starts_with($j, $awalan)) {
                return true;
            }
        }

        return false;
    }

    private static function pendidikan(string $j): bool
    {
        foreach (self::PENDIDIKAN_AWALAN as $awalan) {
            if (str_starts_with($j, $awalan)) {
                return true;
            }
        }

        foreach (self::PENDIDIKAN_MENGANDUNG as $kata) {
            if (str_contains($j, $kata)) {
                return true;
            }
        }

        return false;
    }

    private static function kesehatan(string $j): bool
    {
        foreach (self::KATA_KUNCI_KESEHATAN as $kata) {
            if (str_contains($j, $kata)) {
                return true;
            }
        }

        return false;
    }
}