<?php

namespace App\Support;

/**
 * Aturan perbandingan atribut pegawai antara dua snapshot. Murni logika
 * (tanpa database) supaya bisa diuji sendiri dan dipakai bersama oleh
 * pembentuk riwayat (PegawaiSnapshotService) dan perbandingan awal-akhir
 * (PerbandinganPeriodeService).
 *
 * Prinsip:
 *  - Format dinormalkan (spasi berlebih, huruf besar/kecil) supaya
 *    "Guru  Ahli" dan "GURU AHLI" tidak dianggap berubah. Perbedaan yang
 *    bermakna tidak dihilangkan.
 *  - Nilai kosong (NULL) dibedakan dari perubahan terkonfirmasi:
 *      nilai -> nilai lain  = 'ubah'        (terkonfirmasi)
 *      kosong -> nilai      = 'terisi'      (perlu verifikasi)
 *      nilai -> kosong      = 'dikosongkan' (perlu verifikasi)
 *  - Tanggal efektif hanya diisi kalau sumber data menyediakannya
 *    (PANGKAT_TMT untuk pangkat/golongan). Tidak pernah diambil dari
 *    tanggal impor atau tanggal snapshot.
 */
class PerubahanAtribut
{
    /** kunci kolom snapshot => [label, kategori] */
    public const ATRIBUT = [
        'kategori_jabatan' => ['label' => 'Kategori jabatan (JFT/JFU/Struktural)', 'kategori' => 'jabatan'],
        'jenis_kedudukan' => ['label' => 'Jenis kedudukan', 'kategori' => 'jabatan'],
        'jabatan' => ['label' => 'Nama jabatan', 'kategori' => 'jabatan'],
        'eselon' => ['label' => 'Eselon', 'kategori' => 'jabatan'],
        'golongan' => ['label' => 'Pangkat/golongan', 'kategori' => 'pangkat_golongan'],
        'tmt_pangkat' => ['label' => 'TMT pangkat (tanggal mulai pangkat)', 'kategori' => 'pangkat_golongan'],
        'instansi' => ['label' => 'Instansi', 'kategori' => 'unit_kerja'],
        'unit' => ['label' => 'Unit kerja', 'kategori' => 'unit_kerja'],
        'sub_unit' => ['label' => 'Sub unit kerja', 'kategori' => 'unit_kerja'],
        'pendidikan' => ['label' => 'Pendidikan', 'kategori' => 'pendidikan'],
        'status_kepegawaian' => ['label' => 'Status kepegawaian', 'kategori' => 'status_kepegawaian'],
        'status_lainnya' => ['label' => 'Rincian status (NABAN/Titipan)', 'kategori' => 'status_kepegawaian'],
        'status_aktif' => ['label' => 'Kedudukan pegawai (aktif, pensiun, dll.)', 'kategori' => 'status_kepegawaian'],
        'tanggal_pensiun' => ['label' => 'Tanggal pensiun', 'kategori' => 'status_kepegawaian'],
        'tanggal_selesai_kontrak' => ['label' => 'Tanggal selesai kontrak', 'kategori' => 'status_kepegawaian'],
        'nama' => ['label' => 'Nama', 'kategori' => 'identitas'],
        'agama' => ['label' => 'Agama', 'kategori' => 'data_pribadi'],
        'jenis_kelamin' => ['label' => 'Jenis kelamin', 'kategori' => 'data_pribadi'],
        'tanggal_lahir' => ['label' => 'Tanggal lahir', 'kategori' => 'data_pribadi'],
    ];

    public const KATEGORI = [
        'jabatan' => 'Jabatan',
        'pangkat_golongan' => 'Pangkat/golongan',
        'unit_kerja' => 'Unit kerja',
        'pendidikan' => 'Pendidikan',
        'status_kepegawaian' => 'Status kepegawaian',
        'identitas' => 'Identitas',
        'data_pribadi' => 'Data pribadi',
        'keberadaan' => 'Ada atau tidaknya di data',
    ];

    public const JENIS = [
        'ubah' => 'Berubah',
        'terisi' => 'Terisi (sebelumnya kosong)',
        'dikosongkan' => 'Menjadi kosong',
        'tidak_ditemukan' => 'Tidak ada lagi di data bulan berikutnya',
        'muncul' => 'Baru muncul di data (belum ada di bulan sebelumnya)',
    ];

    /** Kode status_kepegawaian di database => nama yang tampil. */
    public const STATUS_LABEL = [
        'PNS' => 'PNS',
        'PPPK' => 'PPPK',
        'PPPK_PW' => 'PPPK Paruh Waktu',
        'LAINNYA' => 'Lainnya',
    ];

    /**
     * Tingkat status untuk menentukan "naik" atau "turun":
     * PPPK Paruh Waktu (1) -> PPPK (2) -> PNS (3). Status lain (mis. Lainnya,
     * NABAN, Titipan) tidak punya tingkat sehingga dianggap perubahan biasa.
     */
    public const STATUS_TINGKAT = ['PPPK_PW' => 1, 'PPPK' => 2, 'PNS' => 3];

    public const ARAH_STATUS_LABEL = [
        'naik' => 'Naik status',
        'turun' => 'Turun status',
        'lain' => 'Perubahan lain',
    ];

    public const ATRIBUT_KEBERADAAN = '_keberadaan';

    /** Atribut yang hanya muncul dari pencocokan khusus (bukan dari bandingkan()). */
    public const ATRIBUT_KHUSUS = [
        'nip' => 'NIP',
        '_keberadaan' => 'Ada atau tidaknya di data',
    ];

    private const TANGGAL = ['tmt_pangkat', 'tanggal_lahir', 'tanggal_pensiun', 'tanggal_selesai_kontrak'];

    /** Sama dengan RekapService::ESELON_LIST (konstanta itu private). */
    private const ESELON_STRUKTURAL = ['II A', 'II B', 'III A', 'III B', 'IV A', 'IV B'];

    /** Spasi dirapikan; string kosong menjadi NULL. */
    public static function normalisasi(mixed $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }

        $teks = trim((string) preg_replace('/\s+/u', ' ', (string) $nilai));

        return $teks === '' ? null : $teks;
    }

    /** Nilai siap bandingkan/simpan untuk satu atribut (tanggal -> Y-m-d). */
    public static function nilai(string $atribut, mixed $nilai): ?string
    {
        $teks = self::normalisasi($nilai);

        if ($teks !== null && in_array($atribut, self::TANGGAL, true)) {
            return preg_match('/^(\d{4}-\d{2}-\d{2})/', $teks, $m) ? $m[1] : $teks;
        }

        return $teks;
    }

    /** Nama status kepegawaian untuk tampilan (PPPK_PW -> PPPK Paruh Waktu). */
    public static function labelStatus(?string $kode): ?string
    {
        if ($kode === null || trim($kode) === '') {
            return null;
        }

        return self::STATUS_LABEL[strtoupper(trim($kode))] ?? $kode;
    }

    /** naik | turun | lain, menurut urutan PPPK Paruh Waktu -> PPPK -> PNS. */
    public static function arahStatus(?string $dari, ?string $ke): string
    {
        $a = self::STATUS_TINGKAT[strtoupper(trim((string) $dari))] ?? null;
        $b = self::STATUS_TINGKAT[strtoupper(trim((string) $ke))] ?? null;

        if ($a === null || $b === null || $a === $b) {
            return 'lain';
        }

        return $b > $a ? 'naik' : 'turun';
    }

    /**
     * Susun daftar perpindahan status (mis. PPPK Paruh Waktu -> PPPK).
     *
     * @param  array<int, array{sebelum:?string,sesudah:?string,jumlah:int}>  $grup
     * @return array<int, array{dari_kode:?string,ke_kode:?string,dari:?string,ke:?string,arah:string,arah_label:string,jumlah:int}>
     */
    public static function susunPerpindahanStatus(array $grup): array
    {
        $urut = ['naik' => 0, 'lain' => 1, 'turun' => 2];
        $hasil = [];

        foreach ($grup as $g) {
            $arah = self::arahStatus($g['sebelum'] ?? null, $g['sesudah'] ?? null);

            $hasil[] = [
                'dari_kode' => $g['sebelum'] ?? null,
                'ke_kode' => $g['sesudah'] ?? null,
                'dari' => self::labelStatus($g['sebelum'] ?? null),
                'ke' => self::labelStatus($g['sesudah'] ?? null),
                'arah' => $arah,
                'arah_label' => self::ARAH_STATUS_LABEL[$arah],
                'jumlah' => (int) $g['jumlah'],
            ];
        }

        usort($hasil, fn ($x, $y) => [$urut[$x['arah']], -$x['jumlah']] <=> [$urut[$y['arah']], -$y['jumlah']]);

        return $hasil;
    }

    /** Perbandingan tidak peka huruf besar/kecil; NULL hanya sama dengan NULL. */
    public static function sama(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return mb_strtolower($a) === mb_strtolower($b);
    }

    /**
     * Klasifikasi jabatan, SAMA dengan RekapService (rekapJfTertentu /
     * rekapJfPelaksana): eselon II-IV = struktural; selain itu
     * jenis_kedudukan FUNGSIONAL = JFT; sisanya = JFU.
     */
    public static function kategoriJabatan(?string $jenisKedudukan, ?string $eselon): string
    {
        $es = strtoupper((string) preg_replace('/\s+/', ' ', trim((string) $eselon)));

        if (in_array($es, self::ESELON_STRUKTURAL, true)) {
            return 'STRUKTURAL';
        }

        return strtoupper(trim((string) $jenisKedudukan)) === 'FUNGSIONAL' ? 'JFT' : 'JFU';
    }

    /**
     * Tentukan identitas pencocokan dari NIP/NIK.
     *
     * @return array{0: ?string, 1: string} [kunci, status_identitas]
     *   status: nip (18 digit) | nip_tidak_standar | nik | tanpa_identitas
     */
    public static function identitas(mixed $nip, mixed $nik): array
    {
        $nipBersih = preg_replace('/[\s\'.]/u', '', (string) self::normalisasi($nip));
        $nipBersih = $nipBersih === null ? '' : $nipBersih;

        if ($nipBersih !== '') {
            if (preg_match('/^\d{18}$/', $nipBersih)) {
                return ['NIP:' . $nipBersih, 'nip'];
            }

            // Tetap dipakai sebagai kunci (kolom NIP unik di tabel pegawai),
            // tetapi ditandai supaya perubahannya diverifikasi admin.
            return ['NIP:' . mb_substr(strtoupper($nipBersih), 0, 34), 'nip_tidak_standar'];
        }

        $nikBersih = preg_replace('/\D/', '', (string) self::normalisasi($nik));
        if ($nikBersih !== null && preg_match('/^\d{16}$/', $nikBersih)) {
            return ['NIK:' . $nikBersih, 'nik'];
        }

        return [null, 'tanpa_identitas'];
    }

    /**
     * Bandingkan dua keadaan satu pegawai (baris snapshot sebagai array).
     *
     * @param  array  $opsi  bulan_terlewati:int, identitas_lemah:bool
     * @return array<int, array{atribut:string,kategori:string,jenis:string,sebelum:?string,sesudah:?string,tanggal_efektif:?string,perlu_verifikasi:bool,alasan:?string}>
     */
    public static function bandingkan(array $lama, array $baru, array $opsi = []): array
    {
        $hasil = [];

        $alasanDasar = [];
        if (($opsi['bulan_terlewati'] ?? 0) > 0) {
            $alasanDasar[] = $opsi['bulan_terlewati'] . ' bulan di antara kedua periode tidak punya data, waktu perubahan tidak pasti';
        }
        if (! empty($opsi['identitas_lemah'])) {
            $alasanDasar[] = 'NIP tidak standar atau pegawai dicocokkan lewat NIK';
        }

        foreach (self::ATRIBUT as $atribut => $def) {
            $a = self::nilai($atribut, $lama[$atribut] ?? null);
            $b = self::nilai($atribut, $baru[$atribut] ?? null);

            if (self::sama($a, $b)) {
                continue;
            }

            $alasan = $alasanDasar;

            if ($a === null) {
                $jenis = 'terisi';
                $alasan[] = 'Nilai sebelumnya kosong (mungkin data baru dilengkapi, bukan perubahan sebenarnya)';
            } elseif ($b === null) {
                $jenis = 'dikosongkan';
                $alasan[] = 'Nilai sesudahnya kosong (mungkin data tidak terbaca di file)';
            } else {
                $jenis = 'ubah';
            }

            $efektif = null;
            if (in_array($atribut, ['golongan', 'tmt_pangkat'], true)) {
                $tmtBaru = self::nilai('tmt_pangkat', $baru['tmt_pangkat'] ?? null);
                $tmtLama = self::nilai('tmt_pangkat', $lama['tmt_pangkat'] ?? null);
                $efektif = ($tmtBaru !== null && $tmtBaru !== $tmtLama) ? $tmtBaru : null;
            }

            $hasil[] = [
                'atribut' => $atribut,
                'kategori' => $def['kategori'],
                'jenis' => $jenis,
                'sebelum' => $a,
                'sesudah' => $b,
                'tanggal_efektif' => $efektif,
                'perlu_verifikasi' => $alasan !== [],
                'alasan' => $alasan === [] ? null : mb_substr(implode('; ', $alasan), 0, 200),
            ];
        }

        return $hasil;
    }

    public static function label(string $atribut): string
    {
        return self::ATRIBUT[$atribut]['label'] ?? self::ATRIBUT_KHUSUS[$atribut] ?? $atribut;
    }

    /** Selisih bulan antara dua periode YYYY-MM (Jan -> Mar = 2). */
    public static function selisihBulan(string $dari, string $ke): int
    {
        return ((int) substr($ke, 0, 4) - (int) substr($dari, 0, 4)) * 12
            + ((int) substr($ke, 5, 2) - (int) substr($dari, 5, 2));
    }
}