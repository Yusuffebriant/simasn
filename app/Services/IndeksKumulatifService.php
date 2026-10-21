<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\RekapSnapshotData;
use App\Support\RekapCache;

/**
 * Indeks kumulatif: deret bulanan Januari s.d. bulan terakhir yang sudah
 * diupload dalam satu tahun, untuk lima dimensi saja: agama, pendidikan,
 * golongan ruang, JF Tertentu (JFT), dan JF Pelaksana/Umum (JFU).
 *
 * Sumber angka = snapshot rekap yang dibekukan tiap import selesai
 * (tabel rekap_snapshot_data), jadi angka bulan lama tidak berubah walau
 * tabel pegawai sudah ditimpa import bulan berikutnya. Khusus periode
 * aktif yang snapshot-nya belum ada, angka dihitung langsung dari tabel
 * pegawai (sama persis dengan yang tampil di menu Rekapitulasi).
 *
 * Service ini hanya mengembalikan JUMLAH per bulan. Selisih bulanan,
 * kumulatif, dan indeks dihitung di tampilan dari angka yang sama.
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
    ];

    /**
     * Ubah deret jumlah per bulan menjadi nilai sesuai $mode:
     *  - jumlah    : jumlah apa adanya
     *  - selisih   : jumlah bulan ini - jumlah bulan sebelumnya
     *  - kumulatif : jumlah bulan ini - jumlah bulan pertama
     *  - indeks    : berantai, indeks bulan lalu x (jumlah bulan ini / jumlah
     *                bulan lalu), bulan pertama = 100
     *
     * Bulan tanpa data (null) dilewati; pembanding "bulan lalu" adalah bulan
     * terdekat sebelumnya yang punya data. Aturannya HARUS sama dengan
     * hitungDeret() di resources/js/pages/Admin/IndeksKumulatif.jsx.
     *
     * @param  array<int, int|null>  $nilai
     * @return array<int, int|float|null>
     */
    public static function hitungDeret(array $nilai, string $mode): array
    {
        $awal = null;
        foreach ($nilai as $v) {
            if ($v !== null) {
                $awal = $v;
                break;
            }
        }

        $sebelumnya = null;
        $indeksSebelumnya = null;
        $hasil = [];

        foreach ($nilai as $v) {
            if ($v === null) {
                $hasil[] = null;
                continue;
            }

            $h = null;

            if ($mode === 'jumlah') {
                $h = $v;
            } elseif ($mode === 'selisih') {
                $h = $sebelumnya === null ? null : $v - $sebelumnya;
            } elseif ($mode === 'kumulatif') {
                $h = $v - $awal;
            } elseif ($mode === 'indeks' && $awal > 0) {
                if ($sebelumnya === null) {
                    $idx = 100.0;
                } elseif ($sebelumnya > 0 && $indeksSebelumnya !== null) {
                    $idx = $indeksSebelumnya * ($v / $sebelumnya);
                } else {
                    // Jumlah bulan lalu 0: tidak bisa dibagi, hitung langsung
                    // terhadap bulan pertama (hasilnya sama secara matematis).
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

    public function susun(string $tahun): array
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

        $namaSnapshot = array_column(self::DIMENSI, 'snapshot');
        $beku = RekapSnapshotData::whereIn('periode', $daftarPeriode)
            ->whereIn('nama', $namaSnapshot)
            ->get()
            ->keyBy(fn ($r) => $r->periode . '|' . $r->nama);

        $rekap = new RekapService();
        $bulan = [];
        $agregat = []; // [dimensi][indeks bulan] => array kategori => jumlah | null

        foreach ($daftarPeriode as $i => $periode) {
            $sumber = 'kosong';

            foreach (self::DIMENSI as $key => $def) {
                $payload = $beku->get($periode . '|' . $def['snapshot'])?->payload;
                $dariSnapshot = $payload !== null;

                if ($payload === null && $periode === $aktif) {
                    $payload = $rekap->{$def['metode']}($periode);
                }

                $agregat[$key][$i] = $payload === null ? null : $this->agregat($payload);

                if ($payload !== null) {
                    $sumber = $dariSnapshot && $sumber !== 'langsung' ? 'arsip' : 'langsung';
                }
            }

            $bulan[] = ['periode' => $periode, 'sumber' => $sumber];
        }

        $dimensi = [];
        foreach (self::DIMENSI as $key => $def) {
            $dimensi[$key] = $this->susunDimensi($def['label'], $agregat[$key]);
        }

        return [
            'tahun' => $tahun,
            'periode_aktif' => $aktif,
            'bulan' => $bulan,
            'dimensi' => $dimensi,
        ];
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
        }

        return $total;
    }

    /**
     * Gabungkan agregat tiap bulan menjadi baris-per-kategori, diurutkan
     * menurut abjad (A-Z, tidak membedakan huruf besar/kecil). Pengurutan
     * dilakukan di sini supaya tampilan di layar dan file Excel pasti sama.
     * Bulan tanpa data bernilai null; kategori yang tidak muncul di bulan
     * yang ada datanya bernilai 0.
     */
    private function susunDimensi(string $label, array $perBulan): array
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

        return ['label' => $label, 'kategori' => $baris, 'total' => $total];
    }
}