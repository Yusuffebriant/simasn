<?php

namespace App\Services;

use App\Models\PegawaiPerubahanRiwayat;
use App\Models\PegawaiSnapshotPeriode;
use App\Support\PerubahanAtribut;
use Illuminate\Support\Facades\DB;

/**
 * Jumlah ASN aktif (PNS + PPPK) per instansi tiap bulan, lengkap dengan
 * PENJELASAN kenapa jumlahnya berubah dari bulan sebelumnya:
 *
 *   jumlah bulan ini = jumlah bulan lalu
 *                      + pegawai pindah MASUK dari instansi lain
 *                      - pegawai pindah KELUAR ke instansi lain
 *                      + pegawai baru (belum ada / belum aktif bulan lalu)
 *                      - pegawai berhenti (pensiun, mutasi keluar, meninggal,
 *                        status berubah, atau tidak ada lagi di data)
 *                      + selisih yang tidak bisa dilacak per orang
 *
 * Contoh: Januari Dinas A = 30. Februari 3 pegawai pindah ke Dinas B ->
 * Dinas A = 27 (keluar 3), Dinas B bertambah 3 (masuk 3 dari Dinas A),
 * dan TOTAL seluruh instansi tidak berubah.
 *
 * Sumber: snapshot INDIVIDUAL pegawai (tabel pegawai_snapshots), karena
 * perpindahan hanya bisa diketahui dengan membandingkan orang yang sama
 * antar bulan. Bulan yang snapshot individualnya belum ada ditampilkan
 * kosong (bukan 0) dan tidak dikarang dari data agregat. Pembanding "bulan
 * lalu" adalah bulan terdekat sebelumnya yang punya snapshot.
 *
 * Definisi ASN aktif sama dengan rekap lain: status_aktif = 'aktif' dan
 * status_kepegawaian PNS atau PPPK (PPPK Paruh Waktu dan Lainnya tidak
 * ikut).
 */
class MutasiInstansiService
{
    public const STATUS_ASN = ['PNS', 'PPPK'];

    /** Batas jumlah nama pegawai yang dikirim per bulan (agar respons tidak terlalu besar). */
    private const MAKS_RINCIAN = 3000;

    private const TANPA_INSTANSI = '(Instansi belum diisi)';

    private const ALASAN_KEDUDUKAN = [
        'pensiun' => 'Pensiun',
        'mutasi_keluar' => 'Mutasi keluar',
        'meninggal' => 'Meninggal dunia',
    ];

    public function susun(string $awal, string $akhir): array
    {
        $daftar = PerbandinganPeriodeService::daftarBulan($awal, $akhir);
        $punya = PegawaiSnapshotPeriode::whereBetween('periode', [$awal, $akhir])
            ->pluck('periode')->flip();

        /** @var array<string, string> $label kunci instansi (huruf kecil) => nama tampilan */
        $label = [];

        $bulan = [];
        $perBulan = []; // indeks bulan => [kunci instansi => jumlah] | null
        foreach ($daftar as $i => $p) {
            $ada = $punya->has($p);
            $bulan[] = ['periode' => $p, 'tersedia' => $ada];
            $perBulan[$i] = $ada ? $this->hitungPerInstansi($p, $label) : null;
        }

        // Selisih tiap bulan terhadap bulan terdekat sebelumnya yang punya snapshot.
        $mutasi = [];   // indeks bulan => ['lalu' => indeks bulan lalu, 'inst' => [...]]
        $rincian = [];  // periode => daftar kejadian per pegawai
        $terpotong = [];
        $lalu = null;

        foreach ($daftar as $i => $p) {
            if (! $punya->has($p)) {
                continue;
            }

            if ($lalu !== null) {
                [$inst, $kejadian] = $this->selisih($daftar[$lalu], $p, $label);
                $mutasi[$i] = ['lalu' => $lalu, 'inst' => $inst];

                $terpotong[$p] = count($kejadian) > self::MAKS_RINCIAN;
                $rincian[$p] = array_slice($kejadian, 0, self::MAKS_RINCIAN);
            }

            $lalu = $i;
        }

        // Semua instansi yang pernah muncul, urut abjad.
        $semuaKunci = [];
        foreach ($perBulan as $per) {
            foreach (array_keys($per ?? []) as $k) {
                $semuaKunci[(string) $k] = true;
            }
        }
        foreach ($mutasi as $m) {
            foreach (array_keys($m['inst']) as $k) {
                $semuaKunci[(string) $k] = true;
            }
        }
        $kunciUrut = array_keys($semuaKunci);
        usort($kunciUrut, fn ($a, $b) => strnatcasecmp($label[$a] ?? $a, $label[$b] ?? $b));

        $instansi = [];
        foreach ($kunciUrut as $k) {
            $jumlah = [];
            $perMutasi = [];

            foreach ($daftar as $i => $p) {
                $jumlah[] = $perBulan[$i] === null ? null : (int) ($perBulan[$i][$k] ?? 0);

                if (! isset($mutasi[$i])) {
                    $perMutasi[] = null;

                    continue;
                }

                $perMutasi[] = $this->susunMutasi(
                    $mutasi[$i]['inst'][$k] ?? $this->kosong(),
                    (int) ($perBulan[$mutasi[$i]['lalu']][$k] ?? 0),
                    (int) ($perBulan[$i][$k] ?? 0),
                    $daftar[$mutasi[$i]['lalu']],
                    $daftar[$i],
                    $label
                );
            }

            $instansi[] = ['nama' => $label[$k] ?? (string) $k, 'jumlah' => $jumlah, 'mutasi' => $perMutasi];
        }

        return [
            'awal' => $awal,
            'akhir' => $akhir,
            'bulan' => $bulan,
            'ada_data' => $punya->isNotEmpty(),
            'instansi' => $instansi,
            'total' => $this->susunTotal($instansi, $bulan),
            'rincian' => $rincian,
            'rincian_terpotong' => $terpotong,
        ];
    }

    // ------------------------------------------------------------------
    // Jumlah per instansi
    // ------------------------------------------------------------------

    /** @return array<string, int> kunci instansi => jumlah ASN aktif */
    private function hitungPerInstansi(string $periode, array &$label): array
    {
        $baris = DB::table('pegawai_snapshots')
            ->where('periode', $periode)
            ->where('status_aktif', 'aktif')
            ->whereIn('status_kepegawaian', self::STATUS_ASN)
            ->selectRaw('instansi, COUNT(*) as jumlah')
            ->groupBy('instansi')
            ->get();

        $hasil = [];
        foreach ($baris as $r) {
            $k = $this->kunciInstansi($r->instansi, $label);
            $hasil[$k] = ($hasil[$k] ?? 0) + (int) $r->jumlah;
        }

        return $hasil;
    }

    // ------------------------------------------------------------------
    // Selisih dua periode (siapa pindah, baru, berhenti)
    // ------------------------------------------------------------------

    /**
     * @return array{0: array<string, array>, 1: array<int, array>} [per instansi, kejadian per pegawai]
     */
    private function selisih(string $dari, string $ke, array &$label): array
    {
        $kolom = ['kunci', 'nama', 'nip', 'instansi', 'status_aktif', 'status_kepegawaian'];

        $lama = DB::table('pegawai_snapshots')->where('periode', $dari)->get($kolom);
        $baru = DB::table('pegawai_snapshots')->where('periode', $ke)->get($kolom);

        // Pegawai yang dicocokkan lewat NIK punya kunci berbeda antar bulan.
        $peta = PegawaiPerubahanRiwayat::query()
            ->where('periode_dari', $dari)->where('periode_ke', $ke)
            ->whereNotNull('kunci_sebelumnya')
            ->pluck('kunci', 'kunci_sebelumnya')
            ->all();

        $barisBaru = [];
        foreach ($baru as $r) {
            if ($r->kunci !== null) {
                $barisBaru[$r->kunci] = $r;
            }
        }

        $inst = [];
        $kejadian = [];
        $terpakai = [];

        foreach ($lama as $r) {
            if ($r->kunci === null) {
                continue; // tanpa identitas / ganda: tidak bisa dilacak per orang
            }

            $kunciBaru = $peta[$r->kunci] ?? $r->kunci;
            $terpakai[$kunciBaru] = true;

            $cocok = $barisBaru[$kunciBaru] ?? null;
            $lamaAktif = $this->asnAktif($r);
            $cocokAktif = $cocok !== null && $this->asnAktif($cocok);

            if ($lamaAktif) {
                $asal = $this->kunciInstansi($r->instansi, $label);
                $inst[$asal] ??= $this->kosong();

                if ($cocokAktif) {
                    $tujuan = $this->kunciInstansi($cocok->instansi, $label);

                    if ($tujuan !== $asal) {
                        $inst[$tujuan] ??= $this->kosong();
                        $inst[$asal]['keluar'][$tujuan] = ($inst[$asal]['keluar'][$tujuan] ?? 0) + 1;
                        $inst[$tujuan]['masuk'][$asal] = ($inst[$tujuan]['masuk'][$asal] ?? 0) + 1;

                        $kejadian[] = [
                            'jenis' => 'pindah',
                            'nama' => $cocok->nama,
                            'nip' => $cocok->nip,
                            'dari' => $label[$asal],
                            'ke' => $label[$tujuan],
                            'alasan' => null,
                        ];
                    }
                } else {
                    $alasan = $cocok === null
                        ? 'Tidak ada lagi di data bulan ini'
                        : $this->alasanTidakAktif($cocok);

                    $inst[$asal]['berhenti'][$alasan] = ($inst[$asal]['berhenti'][$alasan] ?? 0) + 1;

                    $kejadian[] = [
                        'jenis' => 'berhenti',
                        'nama' => $r->nama,
                        'nip' => $r->nip,
                        'dari' => $label[$asal],
                        'ke' => null,
                        'alasan' => $alasan,
                    ];
                }
            } elseif ($cocokAktif) {
                // Bulan lalu ada di data tetapi bukan ASN aktif, bulan ini ASN aktif.
                $tujuan = $this->kunciInstansi($cocok->instansi, $label);
                $inst[$tujuan] ??= $this->kosong();
                $inst[$tujuan]['baru']++;

                $kejadian[] = [
                    'jenis' => 'baru',
                    'nama' => $cocok->nama,
                    'nip' => $cocok->nip,
                    'dari' => null,
                    'ke' => $label[$tujuan],
                    'alasan' => 'Bulan lalu belum berstatus ASN aktif',
                ];
            }
        }

        foreach ($barisBaru as $kunci => $r) {
            if (isset($terpakai[$kunci]) || ! $this->asnAktif($r)) {
                continue;
            }

            $tujuan = $this->kunciInstansi($r->instansi, $label);
            $inst[$tujuan] ??= $this->kosong();
            $inst[$tujuan]['baru']++;

            $kejadian[] = [
                'jenis' => 'baru',
                'nama' => $r->nama,
                'nip' => $r->nip,
                'dari' => null,
                'ke' => $label[$tujuan],
                'alasan' => 'Baru muncul di data bulan ini',
            ];
        }

        usort($kejadian, fn ($a, $b) => [$a['jenis'], mb_strtolower((string) $a['nama'])] <=> [$b['jenis'], mb_strtolower((string) $b['nama'])]);

        return [$inst, $kejadian];
    }

    private function kosong(): array
    {
        return ['masuk' => [], 'keluar' => [], 'baru' => 0, 'berhenti' => []];
    }

    /**
     * Rakit penjelasan satu instansi pada satu bulan. Selisih yang tidak
     * habis dijelaskan oleh pegawai yang bisa dilacak (mis. pegawai tanpa
     * NIP/NIK atau NIP ganda) dicatat sebagai 'tak_terlacak', supaya
     * rumus di atas SELALU menghasilkan jumlah akhir.
     */
    private function susunMutasi(array $node, int $awal, int $akhir, string $periodeLalu, string $periode, array $label): array
    {
        $masuk = $this->daftarAsal($node['masuk'], $label, 'dari');
        $keluar = $this->daftarAsal($node['keluar'], $label, 'ke');

        $berhenti = [];
        foreach ($node['berhenti'] as $alasan => $jumlah) {
            $berhenti[] = ['alasan' => (string) $alasan, 'jumlah' => $jumlah];
        }
        usort($berhenti, fn ($a, $b) => $b['jumlah'] <=> $a['jumlah'] ?: strcmp($a['alasan'], $b['alasan']));

        $sumMasuk = array_sum(array_column($masuk, 'jumlah'));
        $sumKeluar = array_sum(array_column($keluar, 'jumlah'));
        $sumBerhenti = array_sum(array_column($berhenti, 'jumlah'));

        $tak = $akhir - ($awal + $sumMasuk - $sumKeluar + $node['baru'] - $sumBerhenti);

        return [
            'sejak' => $periodeLalu,
            'bulan_terlewati' => max(0, PerubahanAtribut::selisihBulan($periodeLalu, $periode) - 1),
            'awal' => $awal,
            'masuk' => $masuk,
            'keluar' => $keluar,
            'baru' => $node['baru'],
            'berhenti' => $berhenti,
            'tak_terlacak' => $tak,
            'akhir' => $akhir,
        ];
    }

    /** @return array<int, array{nama: string, jumlah: int}> */
    private function daftarAsal(array $peta, array $label, string $kunciNama): array
    {
        $hasil = [];
        foreach ($peta as $k => $jumlah) {
            $hasil[] = [$kunciNama => $label[(string) $k] ?? (string) $k, 'jumlah' => $jumlah];
        }

        usort($hasil, fn ($a, $b) => $b['jumlah'] <=> $a['jumlah'] ?: strnatcasecmp($a[$kunciNama], $b[$kunciNama]));

        return $hasil;
    }

    /** Total seluruh instansi: pindah antar instansi tidak mengubah total. */
    private function susunTotal(array $instansi, array $bulan): array
    {
        $jumlah = [];
        $mutasi = [];

        foreach ($bulan as $i => $b) {
            if (! $b['tersedia']) {
                $jumlah[] = null;
                $mutasi[] = null;

                continue;
            }

            $jumlah[] = array_sum(array_map(fn ($x) => $x['jumlah'][$i] ?? 0, $instansi));

            $adaMutasi = array_filter(array_map(fn ($x) => $x['mutasi'][$i] ?? null, $instansi));
            if ($adaMutasi === []) {
                $mutasi[] = null;

                continue;
            }

            $berhenti = [];
            foreach ($adaMutasi as $m) {
                foreach ($m['berhenti'] as $x) {
                    $berhenti[$x['alasan']] = ($berhenti[$x['alasan']] ?? 0) + $x['jumlah'];
                }
            }
            arsort($berhenti);

            $contoh = reset($adaMutasi);

            $mutasi[] = [
                'sejak' => $contoh['sejak'],
                'bulan_terlewati' => $contoh['bulan_terlewati'],
                'awal' => array_sum(array_column($adaMutasi, 'awal')),
                'pindah' => array_sum(array_map(fn ($m) => array_sum(array_column($m['masuk'], 'jumlah')), $adaMutasi)),
                'baru' => array_sum(array_column($adaMutasi, 'baru')),
                'berhenti' => array_map(
                    fn ($alasan, $n) => ['alasan' => (string) $alasan, 'jumlah' => $n],
                    array_keys($berhenti),
                    array_values($berhenti)
                ),
                'tak_terlacak' => array_sum(array_column($adaMutasi, 'tak_terlacak')),
                'akhir' => array_sum(array_column($adaMutasi, 'akhir')),
            ];
        }

        return ['jumlah' => $jumlah, 'mutasi' => $mutasi];
    }

    // ------------------------------------------------------------------
    // Pembantu
    // ------------------------------------------------------------------

    private function asnAktif(object $r): bool
    {
        return mb_strtolower((string) $r->status_aktif) === 'aktif'
            && in_array(strtoupper((string) $r->status_kepegawaian), self::STATUS_ASN, true);
    }

    private function alasanTidakAktif(object $r): string
    {
        $aktif = mb_strtolower((string) PerubahanAtribut::normalisasi($r->status_aktif));

        if ($aktif !== '' && $aktif !== 'aktif') {
            return self::ALASAN_KEDUDUKAN[$aktif] ?? ('Kedudukan menjadi ' . str_replace('_', ' ', $aktif));
        }

        $status = str_replace('_', ' ', (string) ($r->status_kepegawaian ?: 'tidak diketahui'));

        return 'Status kepegawaian menjadi ' . $status . ' (bukan PNS/PPPK)';
    }

    /** Kunci instansi tidak peka huruf besar/kecil; nama tampilan = penulisan pertama yang ditemui. */
    private function kunciInstansi(?string $nama, array &$label): string
    {
        $bersih = PerubahanAtribut::normalisasi($nama) ?? self::TANPA_INSTANSI;
        $kunci = mb_strtolower($bersih);
        $label[$kunci] ??= $bersih;

        return $kunci;
    }
}