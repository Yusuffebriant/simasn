<?php

namespace App\Support;

use App\Services\PegawaiNormalizer;
use DateTimeInterface;

/**
 * Menjelaskan KENAPA data kosong atau baris gagal saat import, dengan bahasa
 * yang bisa langsung ditindaklanjuti admin.
 *
 * Dua kegunaan:
 *  1. diagnosaKosong(): untuk pegawai yang TERSIMPAN tetapi ada isian wajib
 *     yang kosong. Tiap isian kosong diberi sebab:
 *       - kolom_tidak_ada : kolomnya tidak ada di file Excel
 *       - kosong_di_file  : kolom ada, tetapi selnya kosong / "-"
 *       - tidak_dikenali  : sel terisi, tetapi isinya tidak bisa dibaca sistem
 *  2. uraikanGagal(): untuk baris yang GAGAL diimport (import_batch_errors),
 *     diurai jadi penyebab, isian yang kurang, dan saran perbaikan.
 *
 * Aturan isian wajib mengikuti KelengkapanPegawai::ATURAN, jadi daftar di sini
 * selalu sama dengan filter "belum lengkap" di Daftar Pegawai.
 */
class AlasanImport
{
    public const SEBAB_KOLOM_TIDAK_ADA = 'kolom_tidak_ada';
    public const SEBAB_KOSONG_DI_FILE = 'kosong_di_file';
    public const SEBAB_TIDAK_DIKENALI = 'tidak_dikenali';

    /** Kolom Excel yang menjadi sumber tiap aturan di KelengkapanPegawai::ATURAN. */
    private const KOLOM_EXCEL = [
        'jenis_kelamin' => 'JENIS_KELAMIN',
        'tanggal_lahir' => 'TGL_LAHIR',
        'instansi' => 'UNIT',
        'agama' => 'AGAMA',
        'pendidikan' => 'TINGKAT_PENDIDIKAN',
        'jabatan' => 'JABATAN',
        'jenis_kedudukan' => 'JENIS_KEDUDUKAN',
        'golongan' => 'PANGKAT_GOLRU',
        'status_perkawinan' => 'STATUS_PERKAWINAN',
    ];

    /** Jenis masalah baris gagal: kolom Excel, judul, dan cara memperbaikinya. */
    public const JENIS_MASALAH = [
        'nama_kosong' => ['kolom' => 'NAMA', 'judul' => 'Nama kosong', 'saran' => 'Isi kolom NAMA pada baris tersebut.'],
        'nip_nik_kosong' => ['kolom' => 'NIP dan NIK', 'judul' => 'NIP dan NIK sama-sama kosong', 'saran' => 'Isi minimal salah satu: NIP (18 digit) atau NIK (16 digit).'],
        'nik_tanpa_angka' => ['kolom' => 'NIK', 'judul' => 'NIK terisi tetapi tidak ada angkanya', 'saran' => 'Ketik ulang NIK dengan 16 digit angka.'],
        'nik_notasi_ilmiah' => ['kolom' => 'NIK', 'judul' => 'NIK tersimpan sebagai angka di Excel (tampil seperti 3.27E+15), digit belakang terpotong', 'saran' => 'Blok kolom NIK, ubah format sel menjadi Text, lalu ketik ulang NIK-nya. Mengubah format saja tidak cukup karena digit yang sudah terpotong tidak kembali.'],
        'nik_karakter' => ['kolom' => 'NIK', 'judul' => 'NIK mengandung spasi, titik, atau tanda baca', 'saran' => 'Hapus spasi, titik, dan tanda baca sehingga tersisa 16 digit angka.'],
        'nik_kurang' => ['kolom' => 'NIK', 'judul' => 'NIK kurang dari 16 digit', 'saran' => 'Periksa NIK pada KTP/KK dan lengkapi menjadi 16 digit.'],
        'nik_lebih' => ['kolom' => 'NIK', 'judul' => 'NIK lebih dari 16 digit', 'saran' => 'Hapus digit berlebih atau pisahkan angka yang tergabung.'],
        'status_kosong' => ['kolom' => 'STATUS_KEPEGAWAIAN', 'judul' => 'STATUS_KEPEGAWAIAN kosong', 'saran' => 'Isi dengan PNS, PPPK, PPPK Paruh Waktu, NABAN, atau Pegawai Titipan.'],
        'kedudukan_tidak_dikenali' => ['kolom' => 'KEDUDUKAN_KEPEGAWAIAN', 'judul' => 'KEDUDUKAN_KEPEGAWAIAN tidak dikenali', 'saran' => 'Ganti dengan AKTIF, PENSIUN, MUTASI KELUAR, atau MENINGGAL (boleh dikosongkan jika aktif).'],
        'rumus_excel' => ['kolom' => 'Kolom berisi rumus', 'judul' => 'Sel berisi rumus Excel, bukan isi sebenarnya', 'saran' => self::SARAN_RUMUS],
        'gagal_simpan' => ['kolom' => 'Beberapa kolom', 'judul' => 'Gagal disimpan ke sistem', 'saran' => 'Periksa isian yang terlalu panjang atau bentrok dengan data lain pada baris tersebut.'],
        'lainnya' => ['kolom' => '-', 'judul' => 'Penyebab lain', 'saran' => 'Lihat penyebab pada daftar baris gagal.'],
    ];

    private const SUFIKS_DATA_LAMA = ' (data lama pegawai ini dipertahankan)';

    /** Cara mengubah rumus Excel menjadi isi aslinya (dipakai di beberapa tempat). */
    public const SARAN_RUMUS = 'Di Excel: tekan Ctrl+A (pilih semua sel), Ctrl+C (salin), klik kanan lalu pilih Paste Special > Values (Tempel Nilai), simpan file, lalu import ulang. Setelah itu sel berisi teks/angka biasa, bukan rumus.';

    /**
     * Sel pada satu baris Excel yang isinya RUMUS (diawali tanda "="), bukan
     * nilai hasilnya. Import membaca teks rumusnya apa adanya, jadi isi seperti
     * =VLOOKUP(...) atau ="197902052025211049" ikut terbaca sebagai isian.
     *
     * @param  array  $row  baris Excel (kunci huruf besar)
     * @return array<string, string>  [nama kolom => teks rumus]
     */
    public static function kolomBerumus(array $row): array
    {
        $hasil = [];

        foreach ($row as $kolom => $nilai) {
            if (! is_string($nilai)) {
                continue;
            }

            $teks = trim($nilai);

            if (strlen($teks) > 1 && $teks[0] === '=') {
                $hasil[(string) $kolom] = $teks;
            }
        }

        return $hasil;
    }

    /**
     * Jenis rumus:
     *  - teks_dibungkus : ="123..." (cara Excel menyimpan angka sebagai teks)
     *  - rumus          : rumus lain, mis. =VLOOKUP(...) ke file lain
     */
    public static function jenisRumus(string $teks): string
    {
        return preg_match('/^=\s*"[^"]*"$/u', trim($teks)) === 1 ? 'teks_dibungkus' : 'rumus';
    }

    /**
     * Isian wajib yang kosong pada satu baris Excel yang berhasil disimpan.
     *
     * @param  array  $row      baris Excel (kunci huruf besar)
     * @param  array  $pegawai  hasil normalisasi: $hasil['pegawai']
     * @param  array  $detail   hasil normalisasi: $hasil['detail']
     * @return array<int, array{kunci:string,label:string,kolom:string,sebab:string,keterangan:string,nilai:?string}>
     */
    public static function diagnosaKosong(array $row, array $pegawai, array $detail): array
    {
        $hasil = [];
        $status = $pegawai['status_kepegawaian'] ?? null;

        foreach (KelengkapanPegawai::ATURAN as $kunci => $aturan) {
            $kolom = self::KOLOM_EXCEL[$kunci] ?? null;

            // NIP/NIK kosong sudah ditolak di isRowValid(), jadi tidak ada di sini.
            if ($kolom === null || ! in_array($status, $aturan['status'], true)) {
                continue;
            }

            $nilai = $aturan['sumber'] === 'detail'
                ? ($detail[$aturan['kolom']] ?? null)
                : ($pegawai[$aturan['kolom']] ?? null);

            if ($nilai !== null && $nilai !== '') {
                continue;
            }

            [$sebab, $keterangan, $mentah] = self::sebabKosong($kunci, $kolom, $row);

            $hasil[] = [
                'kunci' => $kunci,
                'label' => $aturan['label'],
                'kolom' => $kolom,
                'sebab' => $sebab,
                'keterangan' => $keterangan,
                'nilai' => $mentah,
            ];
        }

        return $hasil;
    }

    /**
     * Uraikan satu baris gagal.
     *
     * @return array{penyebab:string,kurang:array<int,string>,saran:?string,data_lama_dipertahankan:bool}
     */
    public static function uraikanGagal(?string $pesan, ?array $mentah): array
    {
        $pesan = (string) $pesan;
        $mentah = $mentah ?? [];

        $dipertahankan = str_ends_with($pesan, self::SUFIKS_DATA_LAMA);
        if ($dipertahankan) {
            $pesan = substr($pesan, 0, -strlen(self::SUFIKS_DATA_LAMA));
        }

        $hasil = [
            'penyebab' => $pesan !== '' ? $pesan : 'Kesalahan tidak diketahui',
            'kurang' => [],
            'saran' => null,
            'data_lama_dipertahankan' => $dipertahankan,
            'masalah' => [],
            'kolom_rumus' => [],
        ];

        if (str_starts_with($pesan, 'Nama kosong, atau NIP dan NIK')) {
            return self::gagalIdentitas($hasil, $mentah);
        }

        if (str_starts_with($pesan, 'NIK tidak valid')) {
            return self::gagalNik($hasil, $mentah);
        }

        if (str_starts_with($pesan, 'STATUS_KEPEGAWAIAN kosong')) {
            $hasil['penyebab'] = 'Kolom STATUS_KEPEGAWAIAN kosong, jadi sistem tidak tahu pegawai ini PNS, PPPK, atau lainnya.';
            $hasil['kurang'] = ['STATUS_KEPEGAWAIAN'];
            $hasil['masalah'][] = 'status_kosong';
            $hasil['saran'] = 'Isi STATUS_KEPEGAWAIAN di file (PNS, PPPK, PPPK Paruh Waktu, NABAN, Pegawai Titipan) lalu import ulang.';

            return $hasil;
        }

        if (str_starts_with($pesan, 'KEDUDUKAN_KEPEGAWAIAN')) {
            $isi = preg_match('/"(.*?)"/u', $pesan, $m) === 1 ? ' "' . self::potong($m[1]) . '"' : '';
            $hasil['penyebab'] = 'Isi kolom KEDUDUKAN_KEPEGAWAIAN' . $isi . ' tidak dikenali sistem.';
            $hasil['masalah'][] = 'kedudukan_tidak_dikenali';
            $hasil['saran'] = 'Ganti dengan salah satu: AKTIF, PENSIUN, MUTASI KELUAR, MENINGGAL (atau kosongkan jika aktif), lalu import ulang.';

            return $hasil;
        }

        if (str_contains($pesan, 'SQLSTATE')) {
            $rumus = self::kolomBerumus($mentah);

            if ($rumus !== []) {
                $uraianRumus = self::gagalRumus($hasil, $pesan, $rumus);

                if ($uraianRumus !== null) {
                    return $uraianRumus;
                }
            }

            $hasil['penyebab'] = 'Gagal disimpan ke sistem. Kemungkinan ada isian yang terlalu panjang atau bentrok dengan data lain.';
            $hasil['masalah'][] = 'gagal_simpan';
            $hasil['saran'] = 'Periksa isian baris ini, lalu import ulang. Pesan teknis: ' . mb_substr($pesan, 0, 160);
        }

        return $hasil;
    }

    /**
     * Kelompokkan baris gagal menjadi saran perbaikan per jenis masalah.
     *
     * @param  iterable<object{baris_ke:int,pesan:?string,data_mentah:?array}>  $baris
     * @return array<int, array{jenis:string,kolom:string,judul:string,saran:string,jumlah:int,contoh:array}>
     */
    public static function ringkasSaran(iterable $baris, int $maksContoh = 8): array
    {
        $grup = [];

        foreach ($baris as $b) {
            try {
                $uraian = self::uraikanGagal($b->pesan, $b->data_mentah);
            } catch (\Throwable $e) {
                $uraian = ['masalah' => []];
            }

            $daftar = $uraian['masalah'] ?: ['lainnya'];
            $nama = trim((string) (is_array($b->data_mentah) ? ($b->data_mentah['NAMA'] ?? '') : ''));

            foreach (array_unique($daftar) as $jenis) {
                $grup[$jenis]['jumlah'] = ($grup[$jenis]['jumlah'] ?? 0) + 1;

                if ($jenis === 'rumus_excel') {
                    foreach ($uraian['kolom_rumus'] ?? [] as $kolomRumus) {
                        $grup[$jenis]['kolom'][$kolomRumus] = ($grup[$jenis]['kolom'][$kolomRumus] ?? 0) + 1;
                    }
                }

                if (count($grup[$jenis]['contoh'] ?? []) < $maksContoh) {
                    $grup[$jenis]['contoh'][] = ['baris' => (int) $b->baris_ke, 'nama' => $nama];
                }
            }
        }

        $hasil = [];
        foreach ($grup as $jenis => $g) {
            $info = self::JENIS_MASALAH[$jenis];

            // Untuk masalah rumus, sebut kolom yang benar-benar berisi rumus.
            if (! empty($g['kolom'])) {
                arsort($g['kolom']);
                $info['kolom'] = implode(', ', array_keys($g['kolom']));
            }

            $hasil[] = ['jenis' => $jenis] + $info + ['jumlah' => $g['jumlah'], 'contoh' => $g['contoh']];
        }

        usort($hasil, fn ($a, $b) => $b['jumlah'] <=> $a['jumlah']);

        return $hasil;
    }

    /**
     * Baris gagal disimpan dan ada sel berisi rumus Excel. Rumus dianggap
     * penyebabnya hanya kalau pesan database menyebut kolom yang berisi rumus
     * itu (mis. "Data too long for column 'status_perkawinan'"), atau kalau
     * pesannya tentang isian terlalu panjang tanpa menyebut kolom. Selain itu
     * null, supaya penyebab lain (mis. bentrok data) tidak salah dituduhkan.
     *
     * @param  array<string,string>  $rumus  [kolom => teks rumus]
     */
    private static function gagalRumus(array $hasil, string $pesan, array $rumus): ?array
    {
        if (preg_match("/column '([^']+)'/i", $pesan, $m) === 1) {
            $sebut = [];

            foreach ($rumus as $kolom => $teks) {
                if (strcasecmp((string) $kolom, $m[1]) === 0) {
                    $sebut[$kolom] = $teks;
                }
            }
        } else {
            $sebut = preg_match('/too long|truncated/i', $pesan) === 1 ? $rumus : [];
        }

        if ($sebut === []) {
            return null;
        }

        $kolom = array_keys($sebut);
        $contoh = self::potong((string) reset($sebut), 60);

        $hasil['masalah'][] = 'rumus_excel';
        $hasil['kolom_rumus'] = $kolom;
        $hasil['kurang'] = $kolom;
        $hasil['penyebab'] = 'Kolom ' . implode(', ', $kolom) . ' berisi rumus Excel (terbaca "' . $contoh . '"), bukan isi sebenarnya. '
            . 'Sistem membaca teks rumusnya, yang tidak cocok dengan kolom tujuan, sehingga baris gagal disimpan.';
        $hasil['saran'] = self::SARAN_RUMUS;

        return $hasil;
    }

    /** Nama + NIP/NIK: bagian mana yang kurang. */
    private static function gagalIdentitas(array $hasil, array $mentah): array
    {
        $normalizer = new PegawaiNormalizer();

        $nama = trim((string) ($mentah['NAMA'] ?? ''));
        $nip = trim((string) ($mentah['NIP'] ?? ''));
        $nikMentah = $mentah['NIK'] ?? null;
        $nik = $normalizer->normalisasiNik($nikMentah);

        $penyebab = [];

        if ($nama === '') {
            $hasil['kurang'][] = 'Nama';
            $hasil['masalah'][] = 'nama_kosong';
            $penyebab[] = 'kolom NAMA kosong';
        }

        if ($nip === '' && $nik === null) {
            $hasil['kurang'][] = 'NIP';
            $hasil['kurang'][] = 'NIK';

            $nikTeks = trim((string) $nikMentah);
            $hasil['masalah'][] = $nikTeks !== '' ? 'nik_tanpa_angka' : 'nip_nik_kosong';
            $penyebab[] = $nikTeks !== ''
                ? 'NIP kosong, dan NIK terisi "' . self::potong($nikTeks) . '" tetapi tidak ada angka yang bisa dibaca'
                : 'NIP dan NIK sama-sama kosong';
        }

        if ($penyebab) {
            $hasil['penyebab'] = ucfirst(implode('; ', $penyebab)) . '.';
        }

        $hasil['saran'] = 'Lengkapi di file Excel (minimal Nama dan salah satu dari NIP atau NIK), lalu import ulang.';

        return $hasil;
    }

    /** NIK ada isinya tetapi bukan 16 digit: jelaskan kemungkinan sebabnya. */
    private static function gagalNik(array $hasil, array $mentah): array
    {
        $normalizer = new PegawaiNormalizer();

        $nikMentah = $mentah['NIK'] ?? null;
        $nik = (string) $normalizer->normalisasiNik($nikMentah);
        $panjang = strlen($nik);
        $teks = trim((string) $nikMentah);

        $hasil['kurang'] = ['NIK (harus 16 digit)'];

        // Angka murni (int/float) di sel NIK = Excel menyimpannya sebagai bilangan,
        // yang hanya akurat sampai 15 digit; sisanya jadi 0 (mis. -347110421276000).
        $notasiIlmiah = is_int($nikMentah) || is_float($nikMentah)
            || (is_string($nikMentah) && preg_match('/^\d+([.,]\d+)?\s*E\s*\+?\d+$/i', $teks) === 1);

        if ($notasiIlmiah) {
            $hasil['masalah'][] = 'nik_notasi_ilmiah';
            $hasil['penyebab'] = 'NIK "' . self::potong($teks) . '" tersimpan sebagai angka di Excel, sehingga digit belakangnya berubah menjadi 0 (terbaca ' . $panjang . ' digit, seharusnya 16)'
                . (str_starts_with($teks, '-') ? ' dan ada tanda minus di depannya.' : '.');
            $hasil['saran'] = 'Di Excel, blok kolom NIK, ubah format sel menjadi Text, hapus tanda minus, lalu ketik ulang 16 digit NIK-nya (digit yang sudah menjadi 0 tidak bisa dipulihkan dengan mengubah format saja). Simpan, lalu import ulang.';

            return $hasil;
        }

        if ($teks !== '' && preg_match('/\D/', $teks) === 1) {
            $hasil['masalah'][] = 'nik_karakter';
            $hasil['penyebab'] = 'NIK "' . self::potong($teks) . '" mengandung karakter selain angka (spasi, titik, tanda baca); setelah dibersihkan terbaca ' . $panjang . ' digit, seharusnya 16.';
        } elseif ($panjang < 16) {
            $hasil['masalah'][] = 'nik_kurang';
            $hasil['penyebab'] = 'NIK terbaca ' . $panjang . ' digit, kurang ' . (16 - $panjang) . ' digit dari 16 (kemungkinan ada digit yang terpotong atau salah ketik).';
        } else {
            $hasil['masalah'][] = 'nik_lebih';
            $hasil['penyebab'] = 'NIK terbaca ' . $panjang . ' digit, kelebihan ' . ($panjang - 16) . ' digit dari 16 (kemungkinan salah ketik atau dua angka tergabung).';
        }

        $hasil['saran'] = 'Perbaiki NIK di file Excel menjadi 16 digit angka, lalu import ulang.';

        return $hasil;
    }

    /**
     * Sebab satu isian kosong.
     *
     * @return array{0:string,1:string,2:?string} [sebab, keterangan, nilai mentah]
     */
    private static function sebabKosong(string $kunci, string $kolom, array $row): array
    {
        if (! array_key_exists($kolom, $row)) {
            return [
                self::SEBAB_KOLOM_TIDAK_ADA,
                "Kolom {$kolom} tidak ada di file",
                null,
            ];
        }

        $mentah = self::teks($row[$kolom]);

        if ($mentah === '' || $mentah === '-') {
            return [self::SEBAB_KOSONG_DI_FILE, 'Kosong di Excel', null];
        }

        $tampil = self::potong($mentah);

        $keterangan = match ($kunci) {
            'jenis_kelamin' => "Isi \"{$tampil}\" tidak dikenali (yang dikenali: L, P, Laki-laki, Perempuan)",
            'tanggal_lahir' => "Isi \"{$tampil}\" tidak bisa dibaca sebagai tanggal",
            'pendidikan' => "Isi \"{$tampil}\" tidak cocok dengan jenjang pendidikan yang dikenal",
            'jenis_kedudukan' => "Isi \"{$tampil}\" tidak dikenali (yang dikenali: Jabatan Fungsional, Jabatan Pelaksana, Struktural)",
            'golongan' => "Isi \"{$tampil}\" tidak bisa dibaca sebagai pangkat/golongan",
            default => "Isi \"{$tampil}\" tidak bisa dibaca",
        };

        return [self::SEBAB_TIDAK_DIKENALI, $keterangan, $tampil];
    }

    private static function teks(mixed $nilai): string
    {
        if ($nilai instanceof DateTimeInterface) {
            return $nilai->format('Y-m-d');
        }

        if (is_array($nilai)) {
            return '';
        }

        return trim((string) $nilai);
    }

    private static function potong(string $teks, int $maks = 40): string
    {
        return mb_strlen($teks) > $maks ? mb_substr($teks, 0, $maks) . '…' : $teks;
    }
}