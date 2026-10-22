<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\PegawaiPerubahanRiwayat;
use App\Models\PegawaiSnapshotPeriode;
use App\Models\RekapSnapshotData;
use App\Support\PerubahanAtribut;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Perbandingan snapshot individual pegawai antar periode (halaman Indeks
 * Kumulatif > Perubahan Individual).
 *
 * DUA sudut pandang yang sengaja dipisahkan:
 *
 *  1. KONDISI  : bandingkan keadaan pegawai di periode awal dengan periode
 *                akhir. Atribut yang berubah lalu kembali ke nilai awal TIDAK
 *                dihitung. Dibaca langsung dari dua snapshot.
 *  2. KEJADIAN : semua perubahan yang tercatat pada tiap pasangan periode
 *                berurutan di dalam rentang (dari tabel pegawai_perubahan_riwayat).
 *                Perubahan yang kembali ke nilai awal tetap tampil di sini.
 *
 * Angka di sini adalah hitungan PEGAWAI/ATRIBUT dari data individual, bukan
 * selisih jumlah agregat. Selisih total JFT dan JFU di tab rekap tidak boleh
 * dibaca sebagai jumlah perpindahan JFT <-> JFU.
 */
class PerbandinganPeriodeService
{
    public const MAKS_BULAN = 120;

    private const JENIS_PERUBAHAN = ['ubah', 'terisi', 'dikosongkan'];

    public function __construct(private PegawaiSnapshotService $snapshot) {}

    /** Daftar YYYY-MM dari $awal sampai $akhir (inklusif, boleh lintas tahun). */
    public static function daftarBulan(string $awal, string $akhir): array
    {
        $tahun = (int) substr($awal, 0, 4);
        $bulan = (int) substr($awal, 5, 2);
        $hasil = [];

        while (($p = sprintf('%04d-%02d', $tahun, $bulan)) <= $akhir && count($hasil) < self::MAKS_BULAN) {
            $hasil[] = $p;

            if (++$bulan > 12) {
                $bulan = 1;
                $tahun++;
            }
        }

        return $hasil;
    }

    // ------------------------------------------------------------------
    // Status data
    // ------------------------------------------------------------------

    /**
     * Pilihan periode untuk filter: semua periode yang dikenal sistem
     * (import selesai, rekap agregat, atau snapshot individual).
     */
    public function periodeOpsi(): array
    {
        $import = ImportBatch::where('status', 'selesai')->pluck('periode');
        $rekap = RekapSnapshotData::query()->distinct()->pluck('periode');
        $individual = PegawaiSnapshotPeriode::pluck('periode');

        $semua = $import->merge($rekap)->merge($individual)
            ->filter(fn ($p) => is_string($p) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p))
            ->unique()->sort()->values();

        $individualSet = $individual->flip();

        return $semua->map(fn ($p) => [
            'periode' => $p,
            'snapshot_individual' => $individualSet->has($p),
        ])->all();
    }

    /**
     * Status kelengkapan snapshot tiap bulan pada rentang.
     * status: tersedia | snapshot_individual_belum_ada | tidak_ada_data
     */
    public function statusPeriode(string $awal, string $akhir): array
    {
        $meta = PegawaiSnapshotPeriode::whereBetween('periode', [$awal, $akhir])->get()->keyBy('periode');
        $import = ImportBatch::where('status', 'selesai')->whereBetween('periode', [$awal, $akhir])
            ->pluck('periode')->unique()->flip();
        $rekap = RekapSnapshotData::whereBetween('periode', [$awal, $akhir])
            ->distinct()->pluck('periode')->flip();

        $hasil = [];
        foreach (self::daftarBulan($awal, $akhir) as $p) {
            $m = $meta->get($p);
            $adaAgregat = $import->has($p) || $rekap->has($p);

            $status = $m ? 'tersedia' : ($adaAgregat ? 'snapshot_individual_belum_ada' : 'tidak_ada_data');

            $hasil[] = [
                'periode' => $p,
                'status' => $status,
                'keterangan' => match ($status) {
                    'tersedia' => 'Snapshot individual tersedia',
                    'snapshot_individual_belum_ada' => 'Rekap agregat ada, snapshot individual belum pernah dibuat (data pegawai bulan itu sudah tertimpa)',
                    default => 'Tidak ada data yang diupload',
                },
                'versi' => $m?->versi,
                'jumlah_pegawai' => $m?->jumlah_pegawai,
                'jumlah_perlu_verifikasi' => $m?->jumlah_perlu_verifikasi,
                'jumlah_tanpa_identitas' => $m?->jumlah_tanpa_identitas,
                'jumlah_duplikat' => $m?->jumlah_duplikat,
                'jumlah_koreksi' => $m ? max(0, count($m->riwayat ?? []) - 1) : null,
                'dibuat_pada' => $m?->dibuat_pada?->toDateTimeString(),
                'diperbarui_pada' => $m?->diperbarui_pada?->toDateTimeString(),
            ];
        }

        return $hasil;
    }

    /** Kedua periode punya snapshot individual dan awal lebih dulu dari akhir. */
    public function dapatDibandingkan(string $awal, string $akhir): bool
    {
        return $awal < $akhir
            && PegawaiSnapshotPeriode::whereIn('periode', [$awal, $akhir])->count() === 2;
    }

    // ------------------------------------------------------------------
    // Ringkasan
    // ------------------------------------------------------------------

    public function ringkasan(string $awal, string $akhir, array $filter = []): array
    {
        $status = $this->statusPeriode($awal, $akhir);
        $adaSnapshot = collect($status)->where('status', 'tersedia')->pluck('periode')->flip();

        $dapat = $awal < $akhir && $adaSnapshot->has($awal) && $adaSnapshot->has($akhir);

        $pesan = null;
        if ($awal >= $akhir) {
            $pesan = 'Periode akhir harus setelah periode awal.';
        } elseif (! $adaSnapshot->has($awal) || ! $adaSnapshot->has($akhir)) {
            $pesan = 'Perbandingan tidak tersedia: snapshot individual periode awal dan/atau akhir belum ada. '
                . 'Perubahan masa lalu tidak dikarang dari data agregat; snapshot dibuat otomatis mulai import berikutnya.';
        }

        $bulanTanpa = collect($status)->where('status', '!=', 'tersedia')->pluck('periode')->all();

        return [
            'awal' => $awal,
            'akhir' => $akhir,
            'dapat_dibandingkan' => $dapat,
            'pesan' => $pesan,
            'bulan_tanpa_snapshot' => $bulanTanpa,
            'status_periode' => $status,
            'kondisi' => $dapat ? $this->hitungDariBaris($this->saring($this->kondisiRows($awal, $akhir), $filter)) : null,
            'kejadian' => $dapat ? $this->hitungKejadian($this->queryKejadian($awal, $akhir, $filter)) : null,
            'opsi_unit' => $dapat ? $this->opsiUnit($awal, $akhir) : [],
        ];
    }

    // ------------------------------------------------------------------
    // Daftar perubahan
    // ------------------------------------------------------------------

    /** Semua baris perubahan kondisi awal -> akhir (sudah disaring dan diurutkan). */
    public function semuaKondisi(string $awal, string $akhir, array $filter = []): array
    {
        $baris = $this->saring($this->kondisiRows($awal, $akhir), $filter);

        usort($baris, fn ($a, $b) => [mb_strtolower($a['nama']), $a['atribut']] <=> [mb_strtolower($b['nama']), $b['atribut']]);

        return $this->beriLabel($baris);
    }

    /** Semua kejadian perubahan pada pasangan periode berurutan di dalam rentang. */
    public function semuaKejadian(string $awal, string $akhir, array $filter = []): array
    {
        $baris = $this->queryKejadian($awal, $akhir, $filter)
            ->orderBy('periode_ke')->orderBy('nama')->orderBy('atribut')
            ->get()->map(fn ($r) => $r->toArray())->all();

        return $this->beriLabel($baris);
    }

    public function daftarKondisi(string $awal, string $akhir, array $filter, int $halaman, int $perHalaman): array
    {
        $semua = $this->semuaKondisi($awal, $akhir, $filter);
        $total = count($semua);

        return $this->halaman(
            $this->beriSumber(array_slice($semua, ($halaman - 1) * $perHalaman, $perHalaman)),
            $total,
            $halaman,
            $perHalaman
        );
    }

    public function daftarKejadian(string $awal, string $akhir, array $filter, int $halaman, int $perHalaman): array
    {
        $paginator = $this->queryKejadian($awal, $akhir, $filter)
            ->orderBy('periode_ke')->orderBy('nama')->orderBy('atribut')
            ->paginate($perHalaman, ['*'], 'page', $halaman);

        $items = $this->beriLabel($paginator->getCollection()->map(fn ($r) => $r->toArray())->all());

        return $this->halaman($this->beriSumber($items), $paginator->total(), $halaman, $perHalaman);
    }

    // ------------------------------------------------------------------
    // Deret: berurutan dan kumulatif dari periode awal
    // ------------------------------------------------------------------

    public function deret(string $awal, string $akhir, array $filter = []): array
    {
        $bulan = self::daftarBulan($awal, $akhir);
        $punya = PegawaiSnapshotPeriode::whereBetween('periode', [$awal, $akhir])->pluck('periode')->flip();

        $berurutan = [];
        $sebelumnya = null;

        foreach ($bulan as $p) {
            if (! $punya->has($p)) {
                $berurutan[] = ['periode' => $p, 'status' => 'tidak_tersedia'];

                continue;
            }

            if ($sebelumnya === null) {
                $berurutan[] = ['periode' => $p, 'status' => 'dasar'];
            } else {
                $q = PegawaiPerubahanRiwayat::query()
                    ->where('periode_dari', $sebelumnya)->where('periode_ke', $p);
                $this->terapkanFilterQuery($q, $filter);

                $berurutan[] = ['periode' => $p, 'status' => 'tersedia', 'dibandingkan_dengan' => $sebelumnya,
                    'bulan_terlewati' => max(0, PerubahanAtribut::selisihBulan($sebelumnya, $p) - 1)]
                    + $this->hitungKejadian($q, false);
            }

            $sebelumnya = $p;
        }

        $kumulatif = [];
        foreach ($bulan as $p) {
            if (! $punya->has($p)) {
                $kumulatif[] = ['periode' => $p, 'status' => 'tidak_tersedia'];
            } elseif ($p === $awal) {
                $kumulatif[] = ['periode' => $p, 'status' => 'dasar'];
            } elseif (! $punya->has($awal)) {
                $kumulatif[] = ['periode' => $p, 'status' => 'dasar_tidak_tersedia'];
            } else {
                $kumulatif[] = ['periode' => $p, 'status' => 'tersedia', 'dibandingkan_dengan' => $awal]
                    + $this->hitungDariBaris($this->saring($this->kondisiRows($awal, $p), $filter), false);
            }
        }

        return ['awal' => $awal, 'akhir' => $akhir, 'berurutan' => $berurutan, 'kumulatif' => $kumulatif];
    }

    // ------------------------------------------------------------------
    // Detail riwayat satu pegawai
    // ------------------------------------------------------------------

    public function riwayatPegawai(string $kunci, string $awal, string $akhir): array
    {
        // Pegawai yang dicocokkan lewat NIK punya dua kunci (lama dan baru).
        $kunciSemua = collect([$kunci])
            ->merge(PegawaiPerubahanRiwayat::where('kunci', $kunci)->whereNotNull('kunci_sebelumnya')->pluck('kunci_sebelumnya'))
            ->merge(PegawaiPerubahanRiwayat::where('kunci_sebelumnya', $kunci)->pluck('kunci'))
            ->unique()->values()->all();

        $kolom = array_merge(['periode', 'kunci', 'nip', 'nik', 'nama', 'status_identitas'], array_keys(PerubahanAtribut::ATRIBUT));

        $snapshot = DB::table('pegawai_snapshots')
            ->whereIn('kunci', $kunciSemua)
            ->whereBetween('periode', [$awal, $akhir])
            ->orderBy('periode')
            ->get($kolom)
            ->keyBy('periode');

        $metaPeriode = PegawaiSnapshotPeriode::whereBetween('periode', [$awal, $akhir])->pluck('periode')->flip();

        $garisWaktu = [];
        foreach (self::daftarBulan($awal, $akhir) as $p) {
            if ($snapshot->has($p)) {
                $garisWaktu[] = ['periode' => $p, 'status' => 'ada', 'nilai' => (array) $snapshot->get($p)];
            } elseif ($metaPeriode->has($p)) {
                $garisWaktu[] = ['periode' => $p, 'status' => 'tidak_ditemukan'];
            } else {
                $garisWaktu[] = ['periode' => $p, 'status' => 'tidak_ada_snapshot'];
            }
        }

        $kejadian = PegawaiPerubahanRiwayat::query()
            ->whereIn('kunci', $kunciSemua)
            ->where('periode_dari', '>=', $awal)->where('periode_ke', '<=', $akhir)
            ->orderBy('periode_ke')->orderBy('kategori')->orderBy('atribut')
            ->get()->map(fn ($r) => $r->toArray())->all();

        // Kondisi awal vs akhir untuk pegawai ini (snapshot ada paling awal vs paling akhir).
        $ada = $snapshot->values();
        $kondisi = [];
        if ($ada->count() >= 2) {
            $a = (array) $ada->first();
            $b = (array) $ada->last();
            $kondisi = array_map(
                fn ($p) => [
                    'kunci' => $kunci,
                    'nama' => $b['nama'],
                    'periode_dari' => $a['periode'],
                    'periode_ke' => $b['periode'],
                    'kategori' => $p['kategori'],
                    'atribut' => $p['atribut'],
                    'jenis' => $p['jenis'],
                    'nilai_sebelum' => $p['sebelum'],
                    'nilai_sesudah' => $p['sesudah'],
                    'tanggal_efektif' => $p['tanggal_efektif'],
                    'perlu_verifikasi' => $p['perlu_verifikasi'],
                    'alasan_verifikasi' => $p['alasan'],
                ],
                PerubahanAtribut::bandingkan($a, $b)
            );
        }

        $pertama = $ada->first();

        return [
            'kunci' => $kunci,
            'nip' => $ada->last()?->nip ?? $pertama?->nip,
            'nama' => $ada->last()?->nama ?? $pertama?->nama,
            'awal' => $awal,
            'akhir' => $akhir,
            'garis_waktu' => $garisWaktu,
            'kejadian' => $this->beriSumber($this->beriLabel($kejadian)),
            'kondisi' => $this->beriLabel($kondisi),
            'ditemukan' => $snapshot->isNotEmpty() || $kejadian !== [],
        ];
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    /** Baris perubahan kondisi awal -> akhir, di-cache menurut isi kedua snapshot. */
    private function kondisiRows(string $awal, string $akhir): array
    {
        $hashAwal = PegawaiSnapshotPeriode::where('periode', $awal)->value('hash_isi');
        $hashAkhir = PegawaiSnapshotPeriode::where('periode', $akhir)->value('hash_isi');

        return Cache::remember(
            "indeks.kondisi.{$awal}.{$hashAwal}.{$akhir}.{$hashAkhir}",
            now()->addMinutes(10),
            fn () => $this->snapshot->selisihDuaPeriode($awal, $akhir, 0, null)
        );
    }

    private function queryKejadian(string $awal, string $akhir, array $filter)
    {
        $q = PegawaiPerubahanRiwayat::query()
            ->where('periode_dari', '>=', $awal)
            ->where('periode_ke', '<=', $akhir);

        $this->terapkanFilterQuery($q, $filter);

        return $q;
    }

    private function terapkanFilterQuery($q, array $filter): void
    {
        if (! empty($filter['kategori'])) {
            $q->where('kategori', $filter['kategori']);
        }

        if (! empty($filter['unit'])) {
            $q->where('unit', $filter['unit']);
        }

        if (! empty($filter['verifikasi'])) {
            $q->where('perlu_verifikasi', 1);
        }

        if (($cari = trim((string) ($filter['q'] ?? ''))) !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cari) . '%';
            $q->where(fn ($w) => $w->where('nama', 'like', $like)->orWhere('nip', 'like', $like));
        }
    }

    /** Saring baris (array) dengan aturan yang sama seperti terapkanFilterQuery(). */
    private function saring(array $baris, array $filter): array
    {
        $cari = mb_strtolower(trim((string) ($filter['q'] ?? '')));

        return array_values(array_filter($baris, function ($r) use ($filter, $cari) {
            if (! empty($filter['kategori']) && $r['kategori'] !== $filter['kategori']) {
                return false;
            }
            if (! empty($filter['unit']) && ($r['unit'] ?? null) !== $filter['unit']) {
                return false;
            }
            if (! empty($filter['verifikasi']) && empty($r['perlu_verifikasi'])) {
                return false;
            }
            if ($cari !== '' && ! str_contains(mb_strtolower($r['nama']), $cari)
                && ! str_contains(mb_strtolower((string) ($r['nip'] ?? '')), $cari)) {
                return false;
            }

            return true;
        }));
    }

    /** Hitungan dari query riwayat (SQL). Bentuk keluaran sama dengan hitungDariBaris(). */
    private function hitungKejadian($q, bool $lengkap = true): array
    {
        $perubahan = (clone $q)->whereIn('jenis', self::JENIS_PERUBAHAN);
        $verif = (clone $q)->where('perlu_verifikasi', 1);

        $hasil = [
            'pegawai_unik_berubah' => (int) (clone $perubahan)->distinct()->count('kunci'),
            'kejadian_perubahan' => (int) (clone $perubahan)->count(),
            'atribut_berubah' => (int) (clone $perubahan)->distinct()->count('atribut'),
            'perlu_verifikasi' => (int) (clone $verif)->count(),
            'pegawai_perlu_verifikasi' => (int) (clone $verif)->distinct()->count('kunci'),
            'tidak_ditemukan' => (int) (clone $q)->where('jenis', 'tidak_ditemukan')->count(),
            'muncul' => (int) (clone $q)->where('jenis', 'muncul')->count(),
        ];

        if (! $lengkap) {
            return $hasil;
        }

        $hasil['per_kategori'] = (clone $q)->select('kategori', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('kategori')->pluck('jumlah', 'kategori')
            ->map(fn ($j) => (int) $j)->all();

        $hasil['perpindahan_jabatan'] = (clone $perubahan)
            ->where('atribut', 'kategori_jabatan')->where('jenis', 'ubah')
            ->select('nilai_sebelum', 'nilai_sesudah', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('nilai_sebelum', 'nilai_sesudah')
            ->get()
            ->map(fn ($r) => ['dari' => $r->nilai_sebelum, 'ke' => $r->nilai_sesudah, 'jumlah' => (int) $r->jumlah])
            ->all();

        return $hasil;
    }

    /** Hitungan dari baris array (perbandingan kondisi). Bentuk sama dengan hitungKejadian(). */
    private function hitungDariBaris(array $baris, bool $lengkap = true): array
    {
        $perubahan = array_values(array_filter($baris, fn ($r) => in_array($r['jenis'], self::JENIS_PERUBAHAN, true)));
        $verif = array_values(array_filter($baris, fn ($r) => ! empty($r['perlu_verifikasi'])));

        $hasil = [
            'pegawai_unik_berubah' => count(array_unique(array_column($perubahan, 'kunci'))),
            'kejadian_perubahan' => count($perubahan),
            'atribut_berubah' => count(array_unique(array_column($perubahan, 'atribut'))),
            'perlu_verifikasi' => count($verif),
            'pegawai_perlu_verifikasi' => count(array_unique(array_column($verif, 'kunci'))),
            'tidak_ditemukan' => count(array_filter($baris, fn ($r) => $r['jenis'] === 'tidak_ditemukan')),
            'muncul' => count(array_filter($baris, fn ($r) => $r['jenis'] === 'muncul')),
        ];

        if (! $lengkap) {
            return $hasil;
        }

        $perKategori = [];
        foreach ($baris as $r) {
            $perKategori[$r['kategori']] = ($perKategori[$r['kategori']] ?? 0) + 1;
        }
        $hasil['per_kategori'] = $perKategori;

        $pindah = [];
        foreach ($perubahan as $r) {
            if ($r['atribut'] === 'kategori_jabatan' && $r['jenis'] === 'ubah') {
                $k = $r['nilai_sebelum'] . '|' . $r['nilai_sesudah'];
                $pindah[$k] = ($pindah[$k] ?? ['dari' => $r['nilai_sebelum'], 'ke' => $r['nilai_sesudah'], 'jumlah' => 0]);
                $pindah[$k]['jumlah']++;
            }
        }
        $hasil['perpindahan_jabatan'] = array_values($pindah);

        return $hasil;
    }

    private function opsiUnit(string $awal, string $akhir): array
    {
        return PegawaiPerubahanRiwayat::query()
            ->where('periode_dari', '>=', $awal)->where('periode_ke', '<=', $akhir)
            ->whereNotNull('unit')->distinct()->orderBy('unit')->limit(300)->pluck('unit')->all();
    }

    private function beriLabel(array $baris): array
    {
        return array_map(function (array $r) {
            $r['atribut_label'] = PerubahanAtribut::label($r['atribut']);
            $r['kategori_label'] = PerubahanAtribut::KATEGORI[$r['kategori']] ?? $r['kategori'];
            $r['jenis_label'] = PerubahanAtribut::JENIS[$r['jenis']] ?? $r['jenis'];
            $r['perlu_verifikasi'] = (bool) ($r['perlu_verifikasi'] ?? false);

            return $r;
        }, $baris);
    }

    /** Sumber data = snapshot periode akhir pasangan (impor yang membentuknya). */
    private function beriSumber(array $baris): array
    {
        $periode = array_values(array_unique(array_column($baris, 'periode_ke')));
        if ($periode === []) {
            return $baris;
        }

        $meta = PegawaiSnapshotPeriode::whereIn('periode', $periode)->get()->keyBy('periode');
        $batch = ImportBatch::whereIn('id', $meta->pluck('import_batch_id')->filter()->all())
            ->get(['id', 'nama_file'])->keyBy('id');

        return array_map(function (array $r) use ($meta, $batch) {
            $m = $meta->get($r['periode_ke']);
            $b = $m?->import_batch_id ? $batch->get($m->import_batch_id) : null;

            $r['sumber'] = $m === null
                ? null
                : ($b ? "Impor #{$b->id} ({$b->nama_file})" : "Snapshot {$m->periode} ({$m->sumber})");

            return $r;
        }, $baris);
    }

    private function halaman(array $data, int $total, int $halaman, int $perHalaman): array
    {
        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perHalaman,
            'current_page' => $halaman,
            'last_page' => max(1, (int) ceil($total / max(1, $perHalaman))),
        ];
    }
}