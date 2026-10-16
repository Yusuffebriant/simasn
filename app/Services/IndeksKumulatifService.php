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
     * Gabungkan agregat tiap bulan menjadi baris-per-kategori. Urutan
     * kategori mengikuti urutan kemunculan di payload (= urutan laporan).
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

        $baris = [];
        foreach (array_keys($kategori) as $nama) {
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