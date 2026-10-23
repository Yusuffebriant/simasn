<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\PegawaiSnapshotPeriode;
use App\Support\PerubahanAtribut;
use Illuminate\Support\Facades\DB;

/**
 * Snapshot INDIVIDUAL pegawai per periode, dan riwayat perubahan antar
 * periode yang berurutan.
 *
 * Dipanggil setelah import selesai (ProcessPegawaiImport) atau lewat
 * `php artisan pegawai:snapshot`. Snapshot menyalin nilai atribut pegawai
 * SAAT ITU, jadi tidak ikut berubah ketika tabel pegawai ditimpa import
 * bulan berikutnya.
 *
 * Aman diulang (idempoten):
 *  - isi sama (hash sama)     -> tidak ada yang ditulis, versi tetap.
 *  - isi berbeda (koreksi)    -> snapshot periode itu diganti, versi naik,
 *                                koreksi dicatat di kolom riwayat, dan
 *                                riwayat perubahan pasangan periode yang
 *                                terkait dihitung ulang (bukan ditambah).
 *
 * Periode yang belum pernah di-snapshot TIDAK dikarang: perbandingan yang
 * melibatkannya berstatus "tidak tersedia".
 */
class PegawaiSnapshotService
{
    private const POTONG_INSERT = 200;

    /**
     * @return array{periode:string,status:string,versi:int,jumlah_pegawai:int,jumlah_perubahan:int}
     *   status: dibuat | diperbarui | tidak_berubah
     */
    public function bangun(string $periode, ?ImportBatch $batch = null, string $sumber = 'import'): array
    {
        $this->pastikanPeriode($periode);

        $baris = $this->bacaPegawaiSaatIni();
        $ringkas = $this->tetapkanIdentitas($baris);
        $hash = $this->hitungHash($baris);
        $sekarang = now();

        return DB::transaction(function () use ($periode, $batch, $sumber, $baris, $ringkas, $hash, $sekarang) {
            $meta = PegawaiSnapshotPeriode::where('periode', $periode)->lockForUpdate()->first();

            if ($meta && $meta->hash_isi === $hash) {
                return [
                    'periode' => $periode,
                    'status' => 'tidak_berubah',
                    'versi' => (int) $meta->versi,
                    'jumlah_pegawai' => (int) $meta->jumlah_pegawai,
                    'jumlah_perubahan' => 0,
                ];
            }

            $versi = $meta ? (int) $meta->versi + 1 : 1;

            $riwayat = $meta?->riwayat ?? [];
            $riwayat[] = [
                'versi' => $versi,
                'waktu' => $sekarang->toDateTimeString(),
                'sumber' => $sumber,
                'import_batch_id' => $batch?->id,
                'jumlah_sebelum' => $meta ? (int) $meta->jumlah_pegawai : null,
                'jumlah_sesudah' => $ringkas['jumlah_pegawai'],
            ];

            DB::table('pegawai_snapshots')->where('periode', $periode)->delete();

            foreach (array_chunk($baris, self::POTONG_INSERT) as $potongan) {
                DB::table('pegawai_snapshots')->insert(array_map(function (array $b) use ($periode, $batch, $sekarang) {
                    return $b + [
                        'periode' => $periode,
                        'import_batch_id' => $batch?->id,
                        'dibuat_pada' => $sekarang->toDateTimeString(),
                    ];
                }, $potongan));
            }

            PegawaiSnapshotPeriode::updateOrCreate(
                ['periode' => $periode],
                $ringkas + [
                    'import_batch_id' => $batch?->id,
                    'sumber' => $sumber,
                    'hash_isi' => $hash,
                    'versi' => $versi,
                    'riwayat' => $riwayat,
                    'dibuat_pada' => $meta?->dibuat_pada ?? $sekarang,
                    'diperbarui_pada' => $sekarang,
                ]
            );

            $jumlahPerubahan = $this->susunUlangRantai($periode);

            return [
                'periode' => $periode,
                'status' => $meta ? 'diperbarui' : 'dibuat',
                'versi' => $versi,
                'jumlah_pegawai' => $ringkas['jumlah_pegawai'],
                'jumlah_perubahan' => $jumlahPerubahan,
            ];
        });
    }

    /**
     * Buang snapshot satu periode (dipakai saat import periode itu dihapus
     * total) dan hubungkan kembali periode sebelum & sesudahnya.
     */
    public function hapusPeriode(string $periode): void
    {
        DB::transaction(function () use ($periode) {
            $daftar = PegawaiSnapshotPeriode::orderBy('periode')->pluck('periode')->all();
            $idx = array_search($periode, $daftar, true);

            $sebelum = ($idx !== false && $idx > 0) ? $daftar[$idx - 1] : null;
            $sesudah = ($idx !== false && isset($daftar[$idx + 1])) ? $daftar[$idx + 1] : null;

            DB::table('pegawai_snapshots')->where('periode', $periode)->delete();
            DB::table('pegawai_perubahan_riwayat')
                ->where(fn ($q) => $q->where('periode_dari', $periode)->orWhere('periode_ke', $periode))
                ->delete();
            PegawaiSnapshotPeriode::where('periode', $periode)->delete();

            if ($sebelum !== null && $sesudah !== null) {
                $this->hitungPasangan($sebelum, $sesudah);
            }
        });
    }

    /** Hitung ulang SEMUA pasangan periode berurutan (perintah artisan). */
    public function hitungUlangSemua(): int
    {
        $daftar = PegawaiSnapshotPeriode::orderBy('periode')->pluck('periode')->all();
        $total = 0;

        for ($i = 1; $i < count($daftar); $i++) {
            $total += $this->hitungPasangan($daftar[$i - 1], $daftar[$i]);
        }

        $this->buangPasanganTidakBerurutan($daftar);

        return $total;
    }

    // ------------------------------------------------------------------
    // Pembentukan snapshot
    // ------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function bacaPegawaiSaatIni(): array
    {
        $baris = [];

        DB::table('pegawai')
            ->leftJoin('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->leftJoin('golongan_ruang', 'golongan_ruang.id', '=', 'pegawai.golongan_ruang_id')
            ->leftJoin('eselon', 'eselon.id', '=', 'pegawai.eselon_id')
            ->leftJoin('pendidikan', 'pendidikan.id', '=', 'pegawai.pendidikan_id')
            ->leftJoin('agama', 'agama.id', '=', 'pegawai.agama_id')
            ->whereNull('pegawai.deleted_at')
            ->select([
                'pegawai.id as id',
                'pegawai.nip', 'pegawai.nik', 'pegawai.nama',
                'instansi.nama as instansi', 'pegawai.unit', 'pegawai.sub_unit',
                'pegawai.jenis_kedudukan', 'pegawai.jabatan', 'eselon.kode as eselon',
                'golongan_ruang.kode as golongan', 'pegawai.tmt_pangkat',
                'pendidikan.jenjang as pendidikan', 'agama.nama as agama',
                'pegawai.jenis_kelamin', 'pegawai.tanggal_lahir',
                'pegawai.status_kepegawaian', 'pegawai.status_lainnya', 'pegawai.status_aktif',
                'pegawai.tanggal_pensiun', 'pegawai.tanggal_selesai_kontrak',
            ])
            ->chunkById(2000, function ($rows) use (&$baris) {
                foreach ($rows as $r) {
                    $baris[] = $this->bentukBaris($r);
                }
            }, 'pegawai.id', 'id');

        return $baris;
    }

    private function bentukBaris(object $r): array
    {
        [$kunci, $status] = PerubahanAtribut::identitas($r->nip, $r->nik);

        $baris = [
            'pegawai_id' => $r->id,
            'kunci' => $kunci,
            'status_identitas' => $status,
            'kunci_asal' => null,
            'nip' => PerubahanAtribut::normalisasi($r->nip),
            'nik' => PerubahanAtribut::normalisasi($r->nik),
        ];

        foreach (array_keys(PerubahanAtribut::ATRIBUT) as $atribut) {
            $baris[$atribut] = PerubahanAtribut::nilai($atribut, $r->{$atribut} ?? null);
        }

        $baris['kategori_jabatan'] = PerubahanAtribut::kategoriJabatan($r->jenis_kedudukan, $r->eselon);
        $baris['nama'] = $baris['nama'] ?? '-';

        return $baris;
    }

    /**
     * Kunci yang muncul lebih dari sekali dalam satu periode tidak boleh
     * dipakai mencocokkan (bisa salah orang): kuncinya dikosongkan dan baris
     * ditandai 'duplikat'. Mengembalikan ringkasan untuk metadata periode.
     */
    private function tetapkanIdentitas(array &$baris): array
    {
        $hitung = [];
        foreach ($baris as $b) {
            if ($b['kunci'] !== null) {
                $hitung[$b['kunci']] = ($hitung[$b['kunci']] ?? 0) + 1;
            }
        }

        $ringkas = [
            'jumlah_pegawai' => count($baris),
            'jumlah_identitas_valid' => 0,
            'jumlah_tanpa_identitas' => 0,
            'jumlah_duplikat' => 0,
        ];

        foreach ($baris as &$b) {
            if ($b['kunci'] !== null && $hitung[$b['kunci']] > 1) {
                $b['kunci_asal'] = $b['kunci'];
                $b['kunci'] = null;
                $b['status_identitas'] = 'duplikat';
            }

            match ($b['status_identitas']) {
                'nip' => $ringkas['jumlah_identitas_valid']++,
                'duplikat' => $ringkas['jumlah_duplikat']++,
                'tanpa_identitas' => $ringkas['jumlah_tanpa_identitas']++,
                default => null,
            };
        }
        unset($b);

        $ringkas['jumlah_perlu_verifikasi'] = $ringkas['jumlah_pegawai'] - $ringkas['jumlah_identitas_valid'];

        return $ringkas;
    }

    /** Sidik isi: urutan baris di database tidak boleh memengaruhi hasil. */
    private function hitungHash(array $baris): string
    {
        $potongan = array_map(function (array $b) {
            unset($b['pegawai_id']);

            return json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $baris);

        sort($potongan, SORT_STRING);

        return sha1(implode("\n", $potongan));
    }

    // ------------------------------------------------------------------
    // Riwayat perubahan antar periode berurutan
    // ------------------------------------------------------------------

    /**
     * Setelah snapshot $periode berubah: buang riwayat pasangan yang tidak
     * lagi berurutan (mis. Jan->Mar setelah Feb ditambahkan), lalu hitung
     * ulang pasangan (sebelumnya, $periode) dan ($periode, sesudahnya).
     */
    private function susunUlangRantai(string $periode): int
    {
        $daftar = PegawaiSnapshotPeriode::orderBy('periode')->pluck('periode')->all();

        $this->buangPasanganTidakBerurutan($daftar);

        $total = 0;
        for ($i = 1; $i < count($daftar); $i++) {
            if ($daftar[$i - 1] === $periode || $daftar[$i] === $periode) {
                $total += $this->hitungPasangan($daftar[$i - 1], $daftar[$i]);
            }
        }

        return $total;
    }

    private function buangPasanganTidakBerurutan(array $daftar): void
    {
        $sah = [];
        for ($i = 1; $i < count($daftar); $i++) {
            $sah[$daftar[$i - 1] . '|' . $daftar[$i]] = true;
        }

        $ada = DB::table('pegawai_perubahan_riwayat')
            ->select('periode_dari', 'periode_ke')
            ->distinct()
            ->get();

        foreach ($ada as $p) {
            if (! isset($sah[$p->periode_dari . '|' . $p->periode_ke])) {
                DB::table('pegawai_perubahan_riwayat')
                    ->where('periode_dari', $p->periode_dari)
                    ->where('periode_ke', $p->periode_ke)
                    ->delete();
            }
        }
    }

    /**
     * Bandingkan snapshot $dari dengan $ke dan simpan perubahannya.
     * Riwayat pasangan ini dihapus dulu lalu ditulis ulang, jadi menghitung
     * ulang tidak pernah menggandakan catatan.
     */
    public function hitungPasangan(string $dari, string $ke): int
    {
        $gap = max(0, PerubahanAtribut::selisihBulan($dari, $ke) - 1);
        $batchKe = PegawaiSnapshotPeriode::where('periode', $ke)->value('import_batch_id');

        $riwayat = $this->selisihDuaPeriode($dari, $ke, $gap, $batchKe);

        DB::transaction(function () use ($dari, $ke, $riwayat) {
            DB::table('pegawai_perubahan_riwayat')
                ->where('periode_dari', $dari)
                ->where('periode_ke', $ke)
                ->delete();

            foreach (array_chunk($riwayat, self::POTONG_INSERT) as $potongan) {
                DB::table('pegawai_perubahan_riwayat')->insert($potongan);
            }
        });

        return count($riwayat);
    }

    /**
     * Selisih atribut antara snapshot $dari dan $ke, dalam bentuk baris
     * riwayat (belum disimpan). Dipakai juga untuk perbandingan awal-akhir
     * yang bukan pasangan berurutan ($gap = 0: tidak ada catatan bulan
     * terlewati karena yang dibandingkan hanya kondisi awal dan akhir).
     *
     * @return array<int, array<string, mixed>>
     */
    public function selisihDuaPeriode(string $dari, string $ke, int $gap = 0, ?int $batchKe = null): array
    {
        $kolom = array_merge(
            ['id', 'kunci', 'status_identitas', 'nip', 'nik', 'nama'],
            array_keys(PerubahanAtribut::ATRIBUT)
        );

        $sekarang = now()->toDateTimeString();

        $lama = [];
        DB::table('pegawai_snapshots')
            ->where('periode', $dari)->whereNotNull('kunci')
            ->select($kolom)
            ->chunkById(2000, function ($rows) use (&$lama) {
                foreach ($rows as $r) {
                    $lama[$r->kunci] = (array) $r;
                }
            }, 'id');

        $riwayat = [];
        $ditemui = [];
        $baruTanpaPasangan = [];

        $tambah = function (array $b, ?array $lamaRow, ?string $kunciSebelumnya, array $p) use (&$riwayat, $dari, $ke, $gap, $batchKe, $sekarang) {
            $riwayat[] = [
                'periode_dari' => $dari,
                'periode_ke' => $ke,
                'bulan_terlewati' => $gap,
                'kunci' => $b['kunci'],
                'kunci_sebelumnya' => $kunciSebelumnya,
                'nip' => $b['nip'] ?? null,
                'nama' => $b['nama'],
                'instansi' => $b['instansi'] ?? null,
                'unit' => $b['unit'] ?? null,
                'kategori' => $p['kategori'],
                'atribut' => $p['atribut'],
                'jenis' => $p['jenis'],
                'nilai_sebelum' => $p['sebelum'] === null ? null : mb_substr($p['sebelum'], 0, 500),
                'nilai_sesudah' => $p['sesudah'] === null ? null : mb_substr($p['sesudah'], 0, 500),
                'tanggal_efektif' => $p['tanggal_efektif'],
                'perlu_verifikasi' => $p['perlu_verifikasi'] ? 1 : 0,
                'alasan_verifikasi' => $p['alasan'],
                'import_batch_id' => $batchKe,
                'dihitung_pada' => $sekarang,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ];
        };

        DB::table('pegawai_snapshots')
            ->where('periode', $ke)->whereNotNull('kunci')
            ->select($kolom)
            ->chunkById(2000, function ($rows) use (&$lama, &$ditemui, &$baruTanpaPasangan, $gap, $tambah) {
                foreach ($rows as $r) {
                    $b = (array) $r;

                    if (! isset($lama[$b['kunci']])) {
                        $baruTanpaPasangan[$b['kunci']] = $b;

                        continue;
                    }

                    $a = $lama[$b['kunci']];
                    $ditemui[$b['kunci']] = true;
                    $lemah = $a['status_identitas'] !== 'nip' || $b['status_identitas'] !== 'nip';

                    foreach (PerubahanAtribut::bandingkan($a, $b, ['bulan_terlewati' => $gap, 'identitas_lemah' => $lemah]) as $p) {
                        $tambah($b, $a, null, $p);
                    }
                }
            }, 'id');

        $hilang = array_diff_key($lama, $ditemui);

        // Pencocokan cadangan lewat NIK: NIP kosong/berganti tapi NIK sama dan
        // tidak ambigu di kedua sisi. Selalu ditandai perlu verifikasi.
        foreach ($this->cocokkanLewatNik($hilang, $baruTanpaPasangan) as [$kunciLama, $kunciBaru]) {
            $a = $hilang[$kunciLama];
            $b = $baruTanpaPasangan[$kunciBaru];
            unset($hilang[$kunciLama], $baruTanpaPasangan[$kunciBaru]);

            $nipLama = PerubahanAtribut::normalisasi($a['nip']);
            $nipBaru = PerubahanAtribut::normalisasi($b['nip']);
            $tambah($b, $a, $kunciLama, [
                'atribut' => 'nip',
                'kategori' => 'identitas',
                'jenis' => $nipLama === null ? 'terisi' : ($nipBaru === null ? 'dikosongkan' : 'ubah'),
                'sebelum' => $nipLama,
                'sesudah' => $nipBaru,
                'tanggal_efektif' => null,
                'perlu_verifikasi' => true,
                'alasan' => 'Dicocokkan lewat NIK karena NIP berbeda atau kosong',
            ]);

            foreach (PerubahanAtribut::bandingkan($a, $b, ['bulan_terlewati' => $gap, 'identitas_lemah' => true]) as $p) {
                $tambah($b, $a, $kunciLama, $p);
            }
        }

        foreach ($hilang as $a) {
            $tambah($a, $a, null, [
                'atribut' => PerubahanAtribut::ATRIBUT_KEBERADAAN,
                'kategori' => 'keberadaan',
                'jenis' => 'tidak_ditemukan',
                'sebelum' => "Ada di data {$dari}",
                'sesudah' => "Tidak ada di data {$ke}",
                'tanggal_efektif' => null,
                'perlu_verifikasi' => true,
                'alasan' => 'Tidak ada lagi di data bulan berikutnya; penyebabnya (pensiun, pindah, data terlewat, atau NIP berubah) belum bisa dipastikan',
            ]);
        }

        foreach ($baruTanpaPasangan as $b) {
            $lemah = $b['status_identitas'] !== 'nip' || $gap > 0;
            $tambah($b, null, null, [
                'atribut' => PerubahanAtribut::ATRIBUT_KEBERADAAN,
                'kategori' => 'keberadaan',
                'jenis' => 'muncul',
                'sebelum' => "Tidak ada di data {$dari}",
                'sesudah' => "Ada di data {$ke}",
                'tanggal_efektif' => null,
                'perlu_verifikasi' => $lemah,
                'alasan' => $lemah ? 'Identitas tidak standar atau ada bulan tanpa data di antaranya' : null,
            ]);
        }

        return $riwayat;
    }

    /**
     * @param  array<string, array>  $hilang
     * @param  array<string, array>  $baru
     * @return array<int, array{0:string,1:string}> pasangan [kunci lama, kunci baru]
     */
    private function cocokkanLewatNik(array $hilang, array $baru): array
    {
        $perNik = function (array $baris): array {
            $hasil = [];
            foreach ($baris as $kunci => $b) {
                $nik = $b['nik'] ?? null;
                if ($nik !== null && $nik !== '') {
                    $hasil[$nik][] = $kunci;
                }
            }

            return $hasil;
        };

        $nikHilang = $perNik($hilang);
        $nikBaru = $perNik($baru);
        $pasangan = [];

        foreach ($nikHilang as $nik => $kunciLama) {
            if (count($kunciLama) === 1 && isset($nikBaru[$nik]) && count($nikBaru[$nik]) === 1) {
                $pasangan[] = [$kunciLama[0], $nikBaru[$nik][0]];
            }
        }

        return $pasangan;
    }

    private function pastikanPeriode(string $periode): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode)) {
            throw new \InvalidArgumentException("Periode tidak valid: {$periode} (format YYYY-MM).");
        }
    }
}