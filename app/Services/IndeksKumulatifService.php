<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Pegawai;
use App\Models\PegawaiSnapshotPeriode;
use App\Models\RekapSnapshotData;
use App\Support\RekapCache;
use Illuminate\Support\Facades\DB;

/**
 * Indeks kumulatif: deret bulanan Januari s.d. bulan terakhir yang sudah
 * diupload dalam satu tahun, untuk enam dimensi: agama, pendidikan,
 * golongan ruang, JF Tertentu (JFT), JF Pelaksana/Umum (JFU), dan status
 * kepegawaian (PNS, PPPK, PPPK Paruh Waktu).
 *
 * Lima dimensi pertama hanya menghitung ASN aktif (PNS + PPPK), sama dengan
 * menu Rekapitulasi. Dimensi status kepegawaian satu-satunya yang ikut
 * menghitung PPPK Paruh Waktu; angkanya diambil dari ringkasan status di
 * snapshot 'paruh-waktu' (tidak butuh snapshot baru).
 *
 * Sumber angka = snapshot rekap yang dibekukan tiap import selesai
 * (tabel rekap_snapshot_data), jadi angka bulan lama tidak berubah walau
 * tabel pegawai sudah ditimpa import bulan berikutnya. Khusus periode
 * aktif yang snapshot-nya belum ada, angka dihitung langsung dari tabel
 * pegawai (sama persis dengan yang tampil di menu Rekapitulasi).
 *
 * Service ini hanya mengembalikan JUMLAH per bulan. Selisih bulanan,
 * kumulatif, dan indeks dihitung di tampilan dari angka yang sama.
 *
 * PENTING: semua angka di sini adalah AGREGAT (statistik jumlah per kategori).
 * Selisih agregat BUKAN jumlah perpindahan individual pegawai. Perpindahan
 * JFT <-> JFU dan perubahan atribut individual dihitung dari snapshot
 * individual (lihat PerbandinganPeriodeService), bukan dari selisih total
 * JFT dan JFU di sini.
 */
class IndeksKumulatifService
{
    /** key dimensi => [label, nama snapshot, method di RekapService] */
    public const DIMENSI = [
        'agama' => ['label' => 'Agama', 'snapshot' => 'agama', 'metode' => 'rekapAgama'],
        'pendidikan' => ['label' => 'Pendidikan', 'snapshot' => 'pendidikan', 'metode' => 'rekapPendidikan'],
        'golongan' => ['label' => 'Golongan Ruang', 'snapshot' => 'golongan', 'metode' => 'rekapGolongan'],
        'jft' => ['label' => 'JF Tertentu (JFT)', 'snapshot' => 'jf-tertentu', 'metode' => 'rekapJfTertentu'],
        'jfu' => ['label' => 'JF Pelaksana (JFU)', 'snapshot' => 'jf-pelaksana', 'metode' => 'rekapJfPelaksana'],
        'status' => [
            'label' => 'Status Kepegawaian',
            'snapshot' => 'paruh-waktu',
            'metode' => 'ringkasanStatusKepegawaian',
            'cakupan' => ['sebutan' => 'pegawai aktif', 'rincian' => 'PNS + PPPK + PPPK Paruh Waktu'],
        ],
    ];

    /** Cakupan bawaan dimensi: hanya ASN aktif (PNS + PPPK). */
    public const CAKUPAN_ASN = ['sebutan' => 'ASN aktif', 'rincian' => 'PNS + PPPK'];

    /** Dimensi yang bisa ikut menghitung PPPK Paruh Waktu (pilihan pengguna). */
    public const DIMENSI_PARUH_WAKTU = ['agama', 'pendidikan'];

    public const CAKUPAN_ASN_PW = ['sebutan' => 'pegawai aktif', 'rincian' => 'PNS + PPPK + PPPK Paruh Waktu'];

    /** Kode status di tabel pegawai => nama kategori yang tampil di dimensi 'status'. */
    public const STATUS_LABEL = [
        'PNS' => 'PNS',
        'PPPK' => 'PPPK',
        'PPPK_PW' => 'PPPK Paruh Waktu',
    ];

    /**
     * Ubah deret jumlah per bulan menjadi nilai sesuai $mode:
     *  - jumlah    : jumlah apa adanya
     *  - selisih   : jumlah bulan ini - jumlah bulan sebelumnya
     *  - kumulatif : jumlah bulan ini - jumlah periode dasar
     *  - indeks    : berantai, indeks bulan lalu x (jumlah bulan ini / jumlah
     *                bulan lalu), periode dasar = 100
     *
     * $dasar = urutan (mulai 0) periode dasar di deret. NULL = bulan pertama
     * yang punya data (perilaku lama). Bulan sebelum periode dasar tidak
     * punya nilai kumulatif/indeks. Kalau periode dasar sendiri kosong (null)
     * atau berjumlah 0, kumulatif/indeks tidak dihitung (tidak ada pembagian
     * dengan nol; hasilnya null, bukan 0).
     *
     * Bulan tanpa data (null) dilewati; pembanding "bulan lalu" adalah bulan
     * terdekat sebelumnya yang punya data. Data kosong TIDAK dianggap 0.
     * Aturannya HARUS sama dengan hitungDeret() di resources/js/lib/indeks.js.
     *
     * @param  array<int, int|null>  $nilai
     * @return array<int, int|float|null>
     */
    public static function hitungDeret(array $nilai, string $mode, ?int $dasar = null): array
    {
        $nilai = array_values($nilai);

        if ($dasar === null) {
            foreach ($nilai as $i => $v) {
                if ($v !== null) {
                    $dasar = $i;
                    break;
                }
            }
        }

        $awal = $dasar === null ? null : ($nilai[$dasar] ?? null);

        $sebelumnya = null;
        $indeksSebelumnya = null;
        $hasil = [];

        foreach ($nilai as $i => $v) {
            if ($v === null) {
                $hasil[] = null;
                continue;
            }

            $h = null;
            $mulaiDariDasar = $dasar !== null && $i >= $dasar;

            if ($mode === 'jumlah') {
                $h = $v;
            } elseif ($mode === 'selisih') {
                $h = $sebelumnya === null ? null : $v - $sebelumnya;
            } elseif ($mode === 'kumulatif') {
                $h = ($awal !== null && $mulaiDariDasar) ? $v - $awal : null;
            } elseif ($mode === 'indeks' && $awal !== null && $awal > 0 && $mulaiDariDasar) {
                if ($i === $dasar) {
                    $idx = 100.0;
                } elseif ($sebelumnya > 0 && $indeksSebelumnya !== null) {
                    $idx = $indeksSebelumnya * ($v / $sebelumnya);
                } else {
                    // Jumlah bulan lalu 0: tidak bisa dibagi, hitung langsung
                    // terhadap periode dasar (hasilnya sama secara matematis).
                    $idx = ($v / $awal) * 100;
                }

                $indeksSebelumnya = $idx;
                $h = $idx;
            }

            $sebelumnya = $v;
            $hasil[] = $h;
        }

        return $hasil;
    }

    /** Tahun-tahun yang punya data (import selesai atau snapshot), terbaru dulu. */
    public function tahunTersedia(): array
    {
        $periode = ImportBatch::where('status', 'selesai')->pluck('periode')
            ->merge(RekapSnapshotData::query()->distinct()->pluck('periode'));

        return $periode
            ->filter(fn ($p) => is_string($p) && preg_match('/^\d{4}-\d{2}$/', $p))
            ->map(fn ($p) => substr($p, 0, 4))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /** Januari s.d. bulan terakhir yang diupload pada $tahun (perilaku lama). */
    public function susun(string $tahun, bool $sertakanParuhWaktu = false): array
    {
        $aktif = RekapCache::periodeAktif();

        $periodeAda = ImportBatch::where('status', 'selesai')
            ->where('periode', 'like', "{$tahun}-%")
            ->pluck('periode')
            ->merge(
                RekapSnapshotData::where('periode', 'like', "{$tahun}-%")
                    ->distinct()
                    ->pluck('periode')
            );

        if ($aktif !== null && str_starts_with($aktif, "{$tahun}-")) {
            $periodeAda->push($aktif);
        }

        $periodeAda = $periodeAda->unique()->values();

        if ($periodeAda->isEmpty()) {
            return [
                'tahun' => $tahun,
                'periode_aktif' => $aktif,
                'awal' => null,
                'akhir' => null,
                'bulan' => [],
                'dimensi' => [],
            ];
        }

        // Januari s.d. bulan terakhir yang diupload. Bulan di antaranya yang
        // belum pernah diupload tetap tampil (kosong) supaya urutan tidak loncat.
        $akhir = (int) substr($periodeAda->max(), 5, 2);
        $daftarPeriode = [];
        for ($m = 1; $m <= $akhir; $m++) {
            $daftarPeriode[] = sprintf('%s-%02d', $tahun, $m);
        }

        return ['tahun' => $tahun] + $this->susunDaftar($daftarPeriode, $aktif, $sertakanParuhWaktu);
    }

    /**
     * Rentang periode pilihan pengguna ($awal s.d. $akhir, boleh lintas
     * tahun). Periode awal = periode dasar untuk mode Kumulatif dan Indeks.
     */
    public function susunRentang(string $awal, string $akhir, bool $sertakanParuhWaktu = false): array
    {
        $daftarPeriode = PerbandinganPeriodeService::daftarBulan($awal, $akhir);

        return ['tahun' => substr($awal, 0, 4)] + $this->susunDaftar($daftarPeriode, RekapCache::periodeAktif(), $sertakanParuhWaktu);
    }

    /**
     * @param  string[]  $daftarPeriode  YYYY-MM berurutan
     */
    private function susunDaftar(array $daftarPeriode, ?string $aktif, bool $sertakanParuhWaktu = false): array
    {
        ['agregat' => $agregat, 'asal' => $asal, 'tanpa_pw' => $tanpaPw] = $this->bangunAgregat($daftarPeriode, $aktif, $sertakanParuhWaktu);

        // Periode yang pernah punya import selesai: kalau arsip rekapnya tidak
        // ada, itu "tanpa arsip" (bukan "tidak ada data").
        $adaImport = ImportBatch::where('status', 'selesai')
            ->whereIn('periode', $daftarPeriode)
            ->pluck('periode')
            ->flip();

        $bulan = [];
        foreach ($daftarPeriode as $i => $periode) {
            $bulan[] = [
                'periode' => $periode,
                'sumber' => $this->tentukanSumber($asal[$i], $adaImport->has($periode)),
            ];
        }

        $dimensi = [];
        foreach (self::DIMENSI as $key => $def) {
            $cakupan = $def['cakupan']
                ?? (($sertakanParuhWaktu && in_array($key, self::DIMENSI_PARUH_WAKTU, true)) ? self::CAKUPAN_ASN_PW : self::CAKUPAN_ASN);

            $dimensi[$key] = $this->susunDimensi($def['label'], $agregat[$key], $cakupan);
        }

        return [
            'periode_aktif' => $aktif,
            'awal' => $daftarPeriode[0] ?? null,
            'akhir' => $daftarPeriode[count($daftarPeriode) - 1] ?? null,
            'bulan' => $bulan,
            'dimensi' => $dimensi,
            'sertakan_paruh_waktu' => $sertakanParuhWaktu,
            'bulan_tanpa_paruh_waktu' => array_keys($tanpaPw),
        ];
    }

    /**
     * Kumpulkan agregat tiap dimensi untuk setiap periode, sekaligus mencatat
     * dari mana angkanya berasal.
     *
     * Prioritas sumber per dimensi dan periode:
     *  1) snapshot beku (rekap periode lama);
     *  2) kalau tidak ada snapshot DAN periode itu periode aktif: hitung
     *     langsung dari data pegawai;
     *  3) selain itu null (data tidak ada, bukan 0).
     *
     * @param  string[]  $daftarPeriode  YYYY-MM berurutan
     * @return array{
     *     agregat: array<string, array<int, array<string, int>|null>>,
     *     asal: array<int, array<string, 'snapshot'|'langsung'|null>>
     * }  agregat[dimensi][indeks bulan] = kategori => jumlah | null;
     *    asal[indeks bulan][dimensi]    = asal angka (null = tidak ada data).
     */
    private function bangunAgregat(array $daftarPeriode, ?string $aktif, bool $sertakanParuhWaktu = false): array
    {
        $namaSnapshot = array_column(self::DIMENSI, 'snapshot');
        $beku = RekapSnapshotData::whereIn('periode', $daftarPeriode)
            ->whereIn('nama', $namaSnapshot)
            ->get()
            ->keyBy(fn ($r) => $r->periode . '|' . $r->nama);

        $rekap = new RekapService();
        $agregat = [];
        $asal = [];
        $tanpaPw = [];

        foreach ($daftarPeriode as $i => $periode) {
            foreach (self::DIMENSI as $key => $def) {
                $payload = $beku->get($periode . '|' . $def['snapshot'])?->payload;
                $asalAngka = $payload !== null ? 'snapshot' : null;
                $angka = $payload === null ? null : $this->agregatDimensi($key, $payload);

                // Tidak ada snapshot, atau snapshot lama yang tidak memuat bagian
                // ini: khusus periode aktif, hitung langsung dari data pegawai.
                if ($angka === null && $periode === $aktif) {
                    $angka = $this->agregatDimensi($key, $rekap->{$def['metode']}($periode));
                    $asalAngka = $angka === null ? null : 'langsung';
                }

                $adaAsn = $angka !== null;

                // Pilihan "Sertakan PPPK Paruh Waktu" (hanya agama dan pendidikan).
                // Kalau data Paruh Waktu bulan itu tidak ada, bulan dikosongkan
                // (bukan dihitung tanpa Paruh Waktu) supaya angka tidak tercampur.
                if ($sertakanParuhWaktu && $adaAsn && in_array($key, self::DIMENSI_PARUH_WAKTU, true)) {
                    $tambahan = $this->agregatParuhWaktu($key, $periode, $beku, $periode === $aktif);

                    if ($tambahan === null) {
                        $angka = null;
                        $tanpaPw[$periode] = true;
                    } else {
                        foreach ($tambahan as $kategori => $jumlah) {
                            $angka[$kategori] = ($angka[$kategori] ?? 0) + $jumlah;
                        }
                    }
                }

                $agregat[$key][$i] = $angka;
                $asal[$i][$key] = $adaAsn ? $asalAngka : null;
            }
        }

        return ['agregat' => $agregat, 'asal' => $asal, 'tanpa_pw' => $tanpaPw];
    }

    /**
     * Jumlah PPPK Paruh Waktu aktif per kategori untuk satu periode, dengan
     * nama kategori yang sama seperti rekap ASN supaya bisa dijumlahkan.
     *
     *  - pendidikan: dari arsip rekap 'paruh-waktu' (per_pendidikan); khusus
     *    periode aktif tanpa arsip, dihitung langsung dari data pegawai.
     *  - agama: arsip rekap tidak memuat agama Paruh Waktu, jadi dibaca dari
     *    data pegawai per orang (pegawai_snapshots); khusus periode aktif
     *    tanpa arsip, dihitung langsung.
     *
     * Null = datanya tidak ada untuk periode itu (bukan 0).
     *
     * @return array<string, int>|null
     */
    private function agregatParuhWaktu(string $key, string $periode, $beku, bool $periodeAktif): ?array
    {
        $hasil = [];

        if ($key === 'pendidikan') {
            $payload = $beku->get($periode . '|paruh-waktu')?->payload;
            $baris = is_array($payload) ? ($payload['per_pendidikan'] ?? null) : null;

            if ($baris === null && $periodeAktif) {
                $baris = (new RekapService())->rekapParuhWaktuPendidikan($periode);
            }

            if (! is_array($baris)) {
                return null;
            }

            foreach ($baris as $b) {
                $nama = (string) ($b['kategori'] ?? '');
                if ($nama === '') {
                    continue;
                }
                if ($nama === 'Belum diisi / lainnya') {
                    $nama = 'Tidak dikenali'; // nama yang sama dengan rekap pendidikan ASN
                }
                $hasil[$nama] = ($hasil[$nama] ?? 0) + (int) ($b['jumlah'] ?? 0);
            }

            return $hasil;
        }

        // Agama
        if (PegawaiSnapshotPeriode::where('periode', $periode)->exists()) {
            $rows = DB::table('pegawai_snapshots')
                ->where('periode', $periode)
                ->where('status_kepegawaian', 'PPPK_PW')
                ->where('status_aktif', 'aktif')
                ->selectRaw('agama, COUNT(*) as jumlah')
                ->groupBy('agama')
                ->get();
        } elseif ($periodeAktif) {
            $rows = Pegawai::paruhWaktu()
                ->leftJoin('agama', 'agama.id', '=', 'pegawai.agama_id')
                ->where('pegawai.status_aktif', 'aktif')
                ->selectRaw('agama.nama as agama, COUNT(*) as jumlah')
                ->groupBy('agama.nama')
                ->get();
        } else {
            return null;
        }

        foreach ($rows as $r) {
            $nama = trim((string) $r->agama) === '' ? 'Tidak diketahui' : trim((string) $r->agama);
            $hasil[$nama] = ($hasil[$nama] ?? 0) + (int) $r->jumlah;
        }

        return $hasil;
    }

    /**
     * Status sumber data satu periode, dipakai tampilan untuk menandai bulan.
     *
     *  - 'langsung'       : minimal satu dimensi dihitung langsung dari data
     *                       pegawai (hanya terjadi di periode aktif). Paling
     *                       dominan: tidak turun ke 'arsip' walau dimensi lain
     *                       berasal dari snapshot.
     *  - 'arsip'          : tidak ada yang langsung, minimal satu dimensi dari
     *                       snapshot beku.
     *  - 'tanpa_snapshot' : semua dimensi kosong, tetapi periode ini pernah
     *                       di-import. Arsip rekap gagal dibuat, jadi data
     *                       tidak tersedia (bukan berarti jumlahnya 0).
     *  - 'kosong'         : semua dimensi kosong dan tidak ada import.
     *
     * @param  array<string, 'snapshot'|'langsung'|null>  $asalDimensi  asal angka per dimensi
     */
    private function tentukanSumber(array $asalDimensi, bool $adaImport): string
    {
        if (in_array('langsung', $asalDimensi, true)) {
            return 'langsung';
        }

        if (in_array('snapshot', $asalDimensi, true)) {
            return 'arsip';
        }

        return $adaImport ? 'tanpa_snapshot' : 'kosong';
    }

    /** Pilih cara membaca payload sesuai dimensinya. */
    private function agregatDimensi(string $key, array $payload): ?array
    {
        return $key === 'status' ? $this->agregatStatus($payload) : $this->agregat($payload);
    }

    /**
     * Jumlah pegawai aktif per status kepegawaian. Payload snapshot
     * 'paruh-waktu' memuat 'ringkasan_status'; hitung langsung
     * (RekapService::ringkasanStatusKepegawaian) mengembalikan ringkasan itu
     * sendiri. CPNS/Lainnya (NABAN, Pegawai Titipan) sengaja tidak ikut.
     * Null kalau payload tidak memuat ringkasan status (arsip lama).
     *
     * @return array<string, int>|null  nama status => jumlah
     */
    private function agregatStatus(array $payload): ?array
    {
        $ringkasan = $payload['ringkasan_status'] ?? $payload;

        if (! is_array($ringkasan) || ! isset($ringkasan['PNS'], $ringkasan['PPPK'])) {
            return null;
        }

        $hasil = [];
        foreach (self::STATUS_LABEL as $kode => $nama) {
            $hasil[$nama] = (int) ($ringkasan[$kode] ?? 0);
        }

        return $hasil;
    }

    /**
     * Jumlahkan seluruh instansi dan kedua jenis kelamin menjadi
     * kategori => jumlah. Hanya membaca 'pria' & 'wanita' (kunci 'pppk'
     * dan agregat lain di payload sudah termasuk di dalamnya, jadi tidak
     * boleh ikut dijumlah supaya tidak dobel).
     */
    private function agregat(array $baris): array
    {
        $total = [];

        foreach ($baris as $row) {
            foreach (['pria', 'wanita'] as $kelompok) {
                foreach (($row[$kelompok] ?? []) as $kategori => $jumlah) {
                    $total[$kategori] = ($total[$kategori] ?? 0) + (int) $jumlah;
                }
            }

            // Hanya ada di rekap pendidikan: jenjang yang tidak dikenali.
            if (! empty($row['tidak_dikenali'])) {
                $total['Tidak dikenali'] = ($total['Tidak dikenali'] ?? 0) + (int) $row['tidak_dikenali'];
            }

            // Hanya ada di rekap agama: Konghucu dan agama belum diisi, yang
            // tidak punya kolom sendiri di laporan tetapi tetap harus dihitung
            // supaya total sama dengan jumlah ASN aktif. Arsip rekap lama
            // (dibuat sebelum bagian ini ada) tidak punya kunci 'lainnya'.
            foreach (($row['lainnya'] ?? []) as $kategori => $jumlah) {
                $total[$kategori] = ($total[$kategori] ?? 0) + (int) $jumlah;
            }
        }

        return $total;
    }

    /**
     * Gabungkan agregat tiap bulan menjadi baris-per-kategori, diurutkan
     * menurut abjad (A-Z, tidak membedakan huruf besar/kecil). Pengurutan
     * dilakukan di sini supaya tampilan di layar dan file Excel pasti sama.
     * Bulan tanpa data bernilai null; kategori yang tidak muncul di bulan
     * yang ada datanya bernilai 0.
     *
     * Contoh: dua bulan, bulan kedua belum ada datanya.
     *   $perBulan = [['Islam' => 10, 'Kristen' => 2], null]
     *   hasil     = ['label' => 'Agama',
     *                'kategori' => [
     *                    ['nama' => 'Islam',   'nilai' => [10, null]],
     *                    ['nama' => 'Kristen', 'nilai' => [2, null]],
     *                ],
     *                'total' => [12, null]]
     *
     * @param  string  $label  Judul dimensi untuk tampilan dan sheet Excel (mis. "Agama").
     * @param  array{sebutan: string, rincian: string}  $cakupan  Siapa yang dihitung, untuk teks
     *         penjelasan di tampilan dan Excel (mis. ASN aktif / PNS + PPPK).
     * @param  array<int, array<string, int>|null>  $perBulan  Satu elemen per bulan, urut sesuai
     *         daftar periode: kategori => jumlah (hasil agregat()), atau null kalau bulan itu
     *         tidak ada datanya.
     * @return array{
     *     label: string,
     *     cakupan: array{sebutan: string, rincian: string},
     *     kategori: list<array{nama: string, nilai: list<int|null>}>,
     *     total: list<int|null>
     * }  'kategori' = satu baris per kategori (urut abjad); 'nilai' sejajar dengan $perBulan
     *    (null = bulan tanpa data, 0 = bulan ada data tetapi kategori tidak muncul).
     *    'total' = jumlah semua kategori per bulan, null untuk bulan tanpa data.
     */
    private function susunDimensi(string $label, array $perBulan, array $cakupan = self::CAKUPAN_ASN): array
    {
        $kategori = [];
        foreach ($perBulan as $agregat) {
            foreach (array_keys($agregat ?? []) as $nama) {
                $kategori[$nama] = true;
            }
        }

        // Kunci array angka (mis. "1") otomatis menjadi int di PHP, jadi
        // dipaksa string dulu sebelum diurutkan.
        $urut = array_map('strval', array_keys($kategori));
        usort($urut, 'strnatcasecmp');

        $baris = [];
        foreach ($urut as $nama) {
            $baris[] = [
                'nama' => (string) $nama,
                'nilai' => array_map(
                    fn ($agregat) => $agregat === null ? null : (int) ($agregat[$nama] ?? 0),
                    $perBulan
                ),
            ];
        }

        $total = array_map(
            fn ($agregat) => $agregat === null ? null : (int) array_sum($agregat),
            $perBulan
        );

        return ['label' => $label, 'cakupan' => $cakupan, 'kategori' => $baris, 'total' => $total];
    }
}