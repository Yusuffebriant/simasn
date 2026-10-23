<?php

namespace App\Exports;

use App\Exports\Concerns\PrintsConsistently;
use App\Models\ImportBatch;
use App\Models\ImportPerubahan;
use App\Services\IndeksKumulatifService;
use App\Services\PerubahanPegawaiService;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Sheet pertama export Indeks Kumulatif: "Ringkasan".
 *
 * Isinya (semua dihitung dari angka yang SAMA dengan sheet lain):
 *  - Ringkasan singkat (2-3 baris) di paling atas.
 *  A. Kesimpulan per kelompok: kategori yang naik/turun (maks. 3 terbesar).
 *  B. Jumlah ASN per kelompok (agama, pendidikan, golongan, JFT, JFU, status kepegawaian).
 *  C. Rincian jumlah ASN per kategori (awal, akhir, selisih, persen).
 *  D. Pegawai masuk, keluar, dan berubah (dihitung per orang dari catatan
 *     import: pegawai baru, pensiun, meninggal, mutasi, naik pangkat, dst).
 *
 * Catatan: bagian B-C adalah statistik jumlah (agregat). Bagian D dihitung
 * per pegawai, jadi tidak harus sama dengan selisih B-C; penjelasannya
 * ditulis tepat di bawah judul bagian D.
 */
class IndeksKumulatifRingkasanExport implements FromArray, WithTitle, WithEvents
{
    use PrintsConsistently;

    private const BULAN = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    ];

    /** Kelompok jenis perubahan (sama dengan halaman Perubahan Pegawai). */
    private const KELOMPOK = [
        'Pegawai masuk' => ['pegawai_baru'],
        'Pegawai keluar' => ['pensiun', 'kontrak_selesai', 'meninggal', 'mutasi_keluar', 'keluar_lainnya'],
        'Data berubah' => ['naik_pangkat', 'ubah_golongan', 'pindah_unit', 'ganti_jabatan', 'ganti_status', 'ganti_pendidikan'],
    ];

    private const LEBAR_TEKS = 'I'; // teks kesimpulan digabung A:I

    private array $rows = [];
    private array $judul = [];
    private array $teks = [];
    private array $tabel = [];
    private array $tebal = [];
    private int $kotakAwal = 0;
    private int $kotakAkhir = 0;
    private int $jumlahKolom = 9;

    /**
     * @param  string[]  $periode  daftar YYYY-MM berurutan
     * @param  array<string, array>  $dimensi  hasil IndeksKumulatifService (label, kategori, total)
     * @param  int|null  $dasar  urutan periode dasar (null = bulan pertama yang punya data)
     */
    public function __construct(
        private array $periode,
        private array $dimensi,
        private ?int $dasar = null
    ) {
        $this->susun();
    }

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function array(): array
    {
        return $this->rows;
    }

    // ------------------------------------------------------------------
    // Penyusunan isi
    // ------------------------------------------------------------------

    private function susun(): void
    {
        $punya = array_filter($this->dimensi, fn (array $t) => collect($t['total'])->contains(fn ($v) => $v !== null));

        $this->tambah(['RINGKASAN DAN KESIMPULAN INDEKS KUMULATIF']);
        $this->judul[] = count($this->rows);

        $pertama = $this->periode[0] ?? null;
        $terakhir = $this->periode ? end($this->periode) : null;
        $bulanKosong = $this->bulanTanpaData();

        $this->tambah([$pertama
            ? 'Rentang: ' . $this->bulan($pertama) . ' s.d. ' . $this->bulan($terakhir)
                . ' (' . count($this->periode) . ' bulan'
                . ($bulanKosong ? ', ' . count($bulanKosong) . ' bulan belum ada data' : '') . ')'
            : 'Tidak ada periode.']);
        $this->teks[] = count($this->rows);

        if ($bulanKosong) {
            $this->tambah(['Bulan tanpa data (tidak dihitung): ' . implode(', ', array_map(fn ($p) => $this->bulan($p), $bulanKosong)) . '.']);
            $this->teks[] = count($this->rows);
        }
        $this->tambah([]);

        if (! $punya) {
            $this->tambah(['Belum ada data jumlah ASN pada rentang ini, jadi belum ada yang bisa disimpulkan.']);
            $this->teks[] = count($this->rows);

            return;
        }

        $ringkas = [];
        foreach ($this->dimensi as $key => $t) {
            $ringkas[$key] = $this->hitungDimensi($t);
        }

        if (collect($ringkas)->filter()->isEmpty()) {
            $this->tambah(['Periode dasar belum punya data, jadi perubahan belum bisa dihitung. Pilih periode awal yang sudah diupload.']);
            $this->teks[] = count($this->rows);

            return;
        }

        $idxDasar = collect($ringkas)->filter()->pluck('idxAwal')->first() ?? ($this->dasar ?? 0);
        $perubahan = $this->ambilPerubahan($idxDasar);

        // ---- RINGKASAN SINGKAT (yang paling penting, di paling atas) ----
        $this->tambah(['RINGKASAN SINGKAT']);
        $this->judul[] = count($this->rows);
        $this->kotakAwal = count($this->rows) + 1;

        $this->tambah(['- ' . $this->kalimatTotal($ringkas)]);
        $this->teks[] = count($this->rows);

        $kalimatOrang = $this->kalimatPerubahanSingkat($perubahan);
        if ($kalimatOrang !== null) {
            $this->tambah(['- ' . $kalimatOrang]);
            $this->teks[] = count($this->rows);
        }

        $this->tambah(['Rincian per kelompok dan per kategori ada di bawah. Tabel bulanan lengkap ada di sheet lain pada file ini.']);
        $this->teks[] = count($this->rows);
        $this->kotakAkhir = count($this->rows);
        $this->tambah([]);

        // ---- A. Kesimpulan per kelompok ----
        $this->tambah(['A. KESIMPULAN PER KELOMPOK']);
        $this->judul[] = count($this->rows);

        $tidakBerubah = [];
        $tanpaData = [];
        foreach ($this->dimensi as $key => $t) {
            $r = $ringkas[$key];
            if ($r === null) {
                $tanpaData[] = $t['label'];
                continue;
            }
            if (! $r['satuBulan'] && $r['selisih'] === 0
                && collect($this->kategoriBerubah($t, $r))->every(fn ($k) => $k['selisih'] === 0)) {
                $tidakBerubah[] = $t['label'];
                continue;
            }
            $this->tambah(['- ' . $this->kalimatDimensi($t, $r)]);
            $this->teks[] = count($this->rows);
        }
        if ($tidakBerubah) {
            $this->tambah(['- Tidak ada perubahan jumlah pada: ' . implode(', ', $tidakBerubah) . '.']);
            $this->teks[] = count($this->rows);
        }
        if ($tanpaData) {
            $this->tambah(['- Belum ada data pada: ' . implode(', ', $tanpaData) . '.']);
            $this->teks[] = count($this->rows);
        }
        $this->tambah([]);

        // ---- B. Jumlah ASN per kelompok ----
        $pertamaR = collect($ringkas)->filter()->first();
        $hAwal = 'Jumlah ' . $this->bulanPendek($pertamaR['periodeAwal']);
        $hAkhir = 'Jumlah ' . $this->bulanPendek($pertamaR['periodeAkhir']);

        $this->tambah(['B. JUMLAH ASN PER KELOMPOK']);
        $this->judul[] = count($this->rows);
        $this->tambah(['ASN aktif = PNS dan PPPK berstatus aktif. Kelompok Status Kepegawaian juga menghitung PPPK Paruh Waktu, jadi totalnya lebih besar. Pada kelompok lain PPPK paruh waktu dan CPNS tidak termasuk, sehingga angkanya bisa lebih kecil dari total di Dashboard Total.']);
        $this->teks[] = count($this->rows);
        $this->tambah(['Indeks: 100 berarti sama dengan bulan awal. Di atas 100 berarti naik, di bawah 100 berarti turun (97,5 = turun 2,5%). JFT dan JFU hanya mencakup ASN jabatan fungsional, jadi totalnya lebih kecil dari kelompok lain.']);
        $this->teks[] = count($this->rows);
        $this->tambah(['Kelompok', 'Bulan awal', $hAwal, 'Bulan akhir', $hAkhir, 'Selisih', 'Perubahan (%)', 'Indeks akhir (awal = 100)', 'Arah']);
        $h = count($this->rows);
        foreach ($this->dimensi as $key => $t) {
            $r = $ringkas[$key];
            $this->tambah($r === null
                ? [$t['label'], '-', '-', '-', '-', '-', '-', '-', 'Belum ada data']
                : [
                    $t['label'],
                    $this->bulanPendek($r['periodeAwal']),
                    $r['awal'],
                    $this->bulanPendek($r['periodeAkhir']),
                    $r['akhir'],
                    $r['selisih'],
                    $r['persen'] ?? '-',
                    $r['indeks'] ?? '-',
                    $r['arah'],
                ]);
        }
        $this->tabel[] = [
            'header' => $h, 'awal' => $h + 1, 'akhir' => count($this->rows), 'total' => null, 'kolom' => 9,
            'arahKol' => 'I', 'selisihKol' => ['F', 'G'],
            'format' => ['C' => '#,##0', 'E' => '#,##0', 'F' => '+#,##0;-#,##0;0', 'G' => '+0.0;-0.0;0.0', 'H' => '0.0'],
        ];
        $this->tambah([]);

        // ---- C. Rincian jumlah ASN per kategori ----
        $this->tambah(['C. RINCIAN JUMLAH ASN PER KATEGORI']);
        $this->judul[] = count($this->rows);
        $this->tambah(['Kategori diurutkan dari perubahan terbesar. Angka di sini adalah selisih jumlah, bukan jumlah orang yang pindah.']);
        $this->teks[] = count($this->rows);
        $this->tambah([]);

        foreach ($this->dimensi as $key => $t) {
            $r = $ringkas[$key];
            if ($r === null) {
                continue;
            }

            $this->tambah([$t['label'] . ': ' . $this->bulan($r['periodeAwal']) . ' → ' . $this->bulan($r['periodeAkhir'])]);
            $this->judul[] = count($this->rows);
            $this->tambah([
                $t['label'],
                'Jumlah ' . $this->bulanPendek($r['periodeAwal']),
                'Jumlah ' . $this->bulanPendek($r['periodeAkhir']),
                'Selisih',
                'Perubahan (%)',
                'Arah',
            ]);
            $h = count($this->rows);

            foreach ($this->kategoriBerubah($t, $r) as $k) {
                $this->tambah([$k['nama'], $k['awal'], $k['akhir'], $k['selisih'], $k['persen'] ?? '-', $k['arah']]);
            }
            $this->tambah(['Total', $r['awal'], $r['akhir'], $r['selisih'], $r['persen'] ?? '-', $r['arah']]);

            $this->tabel[] = [
                'header' => $h, 'awal' => $h + 1, 'akhir' => count($this->rows), 'total' => count($this->rows), 'kolom' => 6,
                'arahKol' => 'F', 'selisihKol' => ['D', 'E'],
                'format' => ['B' => '#,##0', 'C' => '#,##0', 'D' => '+#,##0;-#,##0;0', 'E' => '+0.0;-0.0;0.0'],
            ];
            $this->tambah([]);
        }

        // ---- D. Pegawai masuk, keluar, dan berubah ----
        $this->susunBagianPerubahan($perubahan);
    }

    /** Bagian D: jumlah per jenis perubahan per bulan + subtotal tiap kelompok. */
    private function susunBagianPerubahan(array $p): void
    {
        $this->tambah(['D. PEGAWAI MASUK, KELUAR, DAN BERUBAH (dihitung per orang)']);
        $this->judul[] = count($this->rows);

        if ($p['bulan'] === []) {
            $this->tambah(['Rentang hanya satu bulan, jadi tidak ada perubahan antarbulan yang bisa dijumlahkan.']);
            $this->teks[] = count($this->rows);

            return;
        }

        $this->tambah(['Bagian ini menghitung setiap orang yang tercatat masuk, keluar, atau berubah datanya saat import. Hasilnya bisa berbeda dari selisih jumlah di bagian B-C: kalau satu pegawai keluar dan satu masuk, selisih jumlah = 0 tetapi tercatat 2 perubahan.']);
        $this->teks[] = count($this->rows);
        $this->tambah(['Perubahan tiap bulan = data bulan itu dibanding import sebelumnya. Bulan awal (' . $this->bulan($this->periode[$p['idxAwal']]) . ') tidak ikut dijumlah karena menjadi dasar. Tanda "-" = bulan itu belum punya catatan perubahan (bukan 0).']);
        $this->teks[] = count($this->rows);

        $header = ['Jenis perubahan'];
        foreach ($p['bulan'] as $b) {
            $header[] = $this->bulanPendek($b);
        }
        $header[] = 'Total';
        $this->tambah($header);
        $h = count($this->rows);
        $this->jumlahKolom = max($this->jumlahKolom, count($header));

        $label = PerubahanPegawaiService::LABEL;

        $barisBulan = function (array $jenisList) use ($p): array {
            $baris = [];
            $total = 0;
            foreach ($p['bulan'] as $b) {
                if (! in_array($b, $p['tercatat'], true)) {
                    $baris[] = '-';
                    continue;
                }
                $n = 0;
                foreach ($jenisList as $jenis) {
                    $n += (int) ($p['data'][$b][$jenis] ?? 0);
                }
                $baris[] = $n;
                $total += $n;
            }
            $baris[] = $total;

            return $baris;
        };

        $semuaJenis = [];
        foreach (self::KELOMPOK as $namaKelompok => $jenisList) {
            // Baris subtotal kelompok (tebal), lalu rinciannya (menjorok).
            $this->tambah(array_merge([mb_strtoupper($namaKelompok)], $barisBulan($jenisList)));
            $this->tebal[] = count($this->rows);

            foreach ($jenisList as $jenis) {
                $this->tambah(array_merge(['    ' . ($label[$jenis] ?? $jenis)], $barisBulan([$jenis])));
                $semuaJenis[] = $jenis;
            }
        }

        $this->tambah(array_merge(['JUMLAH SEMUA PERUBAHAN'], $barisBulan($semuaJenis)));

        $this->tabel[] = [
            'header' => $h, 'awal' => $h + 1, 'akhir' => count($this->rows), 'total' => count($this->rows),
            'kolom' => count($header), 'format' => [], 'angkaSemua' => '#,##0',
        ];
    }

    // ------------------------------------------------------------------
    // Perhitungan
    // ------------------------------------------------------------------

    /** Ringkasan satu dimensi, atau null kalau belum ada data sama sekali. */
    private function hitungDimensi(array $t): ?array
    {
        $total = array_values($t['total']);
        $idxAwal = $this->dasar;
        if ($idxAwal === null) {
            foreach ($total as $i => $v) {
                if ($v !== null) {
                    $idxAwal = $i;
                    break;
                }
            }
        }

        $idxAkhir = null;
        foreach ($total as $i => $v) {
            if ($v !== null) {
                $idxAkhir = $i;
            }
        }

        if ($idxAwal === null || $idxAkhir === null || ($total[$idxAwal] ?? null) === null) {
            return null;
        }

        $awal = (int) $total[$idxAwal];
        $akhir = (int) $total[$idxAkhir];
        $selisih = $akhir - $awal;
        $indeks = IndeksKumulatifService::hitungDeret($total, 'indeks', $this->dasar)[$idxAkhir] ?? null;

        return [
            'idxAwal' => $idxAwal,
            'idxAkhir' => $idxAkhir,
            'periodeAwal' => $this->periode[$idxAwal],
            'periodeAkhir' => $this->periode[$idxAkhir],
            'awal' => $awal,
            'akhir' => $akhir,
            'selisih' => $selisih,
            'persen' => $awal > 0 ? round($selisih / $awal * 100, 1) : null,
            'indeks' => $indeks === null ? null : round($indeks, 1),
            'arah' => $this->arah($selisih),
            'satuBulan' => $idxAwal === $idxAkhir,
        ];
    }

    /** Kategori dengan nilai awal/akhir/selisih, diurutkan perubahan terbesar dulu. */
    private function kategoriBerubah(array $t, array $r): array
    {
        $hasil = [];
        foreach ($t['kategori'] as $k) {
            $awal = $k['nilai'][$r['idxAwal']] ?? null;
            $akhir = $k['nilai'][$r['idxAkhir']] ?? null;
            if ($awal === null || $akhir === null) {
                continue;
            }
            $selisih = (int) $akhir - (int) $awal;
            $hasil[] = [
                'nama' => $k['nama'],
                'awal' => (int) $awal,
                'akhir' => (int) $akhir,
                'selisih' => $selisih,
                'persen' => $awal > 0 ? round($selisih / $awal * 100, 1) : null,
                'arah' => $this->arah($selisih),
            ];
        }

        usort($hasil, fn ($a, $b) => abs($b['selisih']) <=> abs($a['selisih']) ?: strnatcasecmp($a['nama'], $b['nama']));

        return $hasil;
    }

    /** Satu kalimat total ASN (pakai kelompok yang mencakup seluruh ASN). */
    private function kalimatTotal(array $ringkas): string
    {
        $r = null;
        foreach (['agama', 'pendidikan', 'golongan'] as $key) {
            if (($ringkas[$key] ?? null) !== null) {
                $r = $ringkas[$key];
                break;
            }
        }
        $r ??= collect($ringkas)->filter()->first();

        if ($r === null) {
            return 'Belum ada data jumlah ASN.';
        }

        if ($r['satuBulan']) {
            return "Baru ada data satu bulan ({$this->bulan($r['periodeAwal'])}): {$this->angka($r['awal'])} ASN aktif, jadi belum bisa dibandingkan.";
        }

        if ($r['selisih'] === 0) {
            return "Jumlah ASN aktif tidak berubah: tetap {$this->angka($r['awal'])} orang ({$this->bulan($r['periodeAwal'])} sampai {$this->bulan($r['periodeAkhir'])}).";
        }

        $arah = $r['selisih'] > 0 ? 'naik' : 'turun';

        return "Jumlah ASN aktif {$arah} dari {$this->angka($r['awal'])} ({$this->bulan($r['periodeAwal'])}) menjadi {$this->angka($r['akhir'])} ({$this->bulan($r['periodeAkhir'])}), selisih {$this->tanda($r['selisih'])}"
            . ($r['persen'] !== null ? " ({$this->tanda($r['persen'], 1)}%)" : '') . '.';
    }

    /** Satu kalimat ringkas: pegawai masuk, keluar, data berubah. */
    private function kalimatPerubahanSingkat(array $p): ?string
    {
        if ($p['bulan'] === []) {
            return null;
        }

        $periodeTeks = $this->bulan($p['bulan'][0]) . ' sampai ' . $this->bulan(end($p['bulan']));

        if ($p['tercatat'] === []) {
            return "Perubahan per pegawai ({$periodeTeks}): belum ada catatan pada bulan-bulan tersebut.";
        }

        $jumlah = [];
        foreach ($p['tercatat'] as $b) {
            foreach (($p['data'][$b] ?? []) as $jenis => $n) {
                $jumlah[$jenis] = ($jumlah[$jenis] ?? 0) + $n;
            }
        }

        if (array_sum($jumlah) === 0) {
            return "Perubahan per pegawai ({$periodeTeks}): tidak ada yang tercatat.";
        }

        $label = PerubahanPegawaiService::LABEL;
        $bagian = [];
        foreach (self::KELOMPOK as $nama => $jenisList) {
            $sub = array_sum(array_intersect_key($jumlah, array_flip($jenisList)));
            if ($sub === 0) {
                continue;
            }
            $rincian = [];
            foreach ($jenisList as $jenis) {
                if (($jumlah[$jenis] ?? 0) > 0 && count($jenisList) > 1) {
                    $rincian[] = mb_strtolower($label[$jenis] ?? $jenis) . ' ' . $this->angka($jumlah[$jenis]);
                }
            }
            $bagian[] = mb_strtolower($nama) . ' ' . $this->angka($sub) . ($rincian ? ' (' . implode(', ', $rincian) . ')' : '');
        }

        $teks = "Perubahan per pegawai ({$periodeTeks}): " . implode('; ', $bagian) . '.';

        $tanpa = array_values(array_diff($p['bulan'], $p['tercatat']));
        if ($tanpa) {
            $teks .= ' Belum ada catatan untuk: ' . implode(', ', array_map(fn ($b) => $this->bulan($b), $tanpa)) . '.';
        }

        return $teks;
    }

    private function kalimatDimensi(array $t, array $r): string
    {
        if ($r['satuBulan']) {
            return "{$t['label']}: baru ada data satu bulan ({$this->bulan($r['periodeAwal'])}, {$this->angka($r['awal'])} ASN), jadi belum bisa dibandingkan.";
        }

        $kalimat = "{$t['label']}: total {$this->angka($r['awal'])} → {$this->angka($r['akhir'])}"
            . ($r['selisih'] !== 0 ? " ({$this->tanda($r['selisih'])})" : ' (tetap)') . '.';

        $berubah = $this->kategoriBerubah($t, $r);
        $naik = array_values(array_filter($berubah, fn ($k) => $k['selisih'] > 0));
        $turun = array_values(array_filter($berubah, fn ($k) => $k['selisih'] < 0));

        if ($naik) {
            $kalimat .= ' Naik: ' . $this->daftarKategori($naik) . '.';
        }
        if ($turun) {
            $kalimat .= ' Turun: ' . $this->daftarKategori($turun) . '.';
        }
        if ($naik && $turun && $r['selisih'] === 0) {
            $kalimat .= ' (Naik dan turun saling mengimbangi, jadi total tetap.)';
        }

        return $kalimat;
    }

    /** Maksimal 3 kategori terbesar; sisanya diringkas "dan N kategori lain". */
    private function daftarKategori(array $list): string
    {
        $teks = array_map(fn ($k) => "{$k['nama']} {$this->tanda($k['selisih'])}", array_slice($list, 0, 3));
        $sisa = count($list) - 3;

        return implode(', ', $teks) . ($sisa > 0 ? ", dan {$sisa} kategori lain" : '');
    }

    /**
     * Jumlah perubahan per jenis per bulan dari import_perubahan, untuk bulan
     * SETELAH periode awal sampai periode akhir.
     */
    private function ambilPerubahan(int $idxAwal): array
    {
        $kosong = ['bulan' => [], 'tercatat' => [], 'data' => [], 'idxAwal' => $idxAwal];

        if (count($this->periode) < 2) {
            return $kosong;
        }

        $bulan = array_values(array_filter($this->periode, fn ($p, $i) => $i > $idxAwal, ARRAY_FILTER_USE_BOTH));
        if ($bulan === []) {
            return $kosong;
        }

        $tercatat = ImportBatch::where('status', 'selesai')
            ->where('perubahan_status', 'selesai')
            ->whereIn('periode', $bulan)
            ->pluck('periode')
            ->unique()
            ->values()
            ->all();

        $data = [];
        ImportPerubahan::whereIn('periode', $bulan)
            ->select('periode', 'jenis', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('periode', 'jenis')
            ->get()
            ->each(function ($r) use (&$data) {
                $data[$r->periode][$r->jenis] = (int) $r->jumlah;
            });

        return ['bulan' => $bulan, 'tercatat' => $tercatat, 'data' => $data, 'idxAwal' => $idxAwal];
    }

    /** Bulan di rentang yang tidak punya data pada semua kelompok. */
    private function bulanTanpaData(): array
    {
        $hasil = [];
        foreach ($this->periode as $i => $p) {
            $ada = false;
            foreach ($this->dimensi as $t) {
                if (($t['total'][$i] ?? null) !== null) {
                    $ada = true;
                    break;
                }
            }
            if (! $ada) {
                $hasil[] = $p;
            }
        }

        return $hasil;
    }

    // ------------------------------------------------------------------
    // Format teks
    // ------------------------------------------------------------------

    private function arah(int|float $selisih): string
    {
        return $selisih > 0 ? '▲ Naik' : ($selisih < 0 ? '▼ Turun' : '● Tetap');
    }

    private function angka(int|float $n, int $desimal = 0): string
    {
        return number_format($n, $desimal, ',', '.');
    }

    private function tanda(int|float $n, int $desimal = 0): string
    {
        return ($n > 0 ? '+' : '') . $this->angka($n, $desimal);
    }

    private function bulan(?string $p): string
    {
        return $p ? (self::BULAN[substr($p, 5, 2)] ?? $p) . ' ' . substr($p, 0, 4) : '-';
    }

    private function bulanPendek(?string $p): string
    {
        return $p ? mb_substr(self::BULAN[substr($p, 5, 2)] ?? $p, 0, 3) . ' ' . substr($p, 0, 4) : '-';
    }

    private function tambah(array $baris): void
    {
        $this->rows[] = $baris;
    }

    // ------------------------------------------------------------------
    // Tampilan
    // ------------------------------------------------------------------

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = max(1, count($this->rows));
                $lastCol = Coordinate::stringFromColumnIndex($this->jumlahKolom);
                $teksCol = self::LEBAR_TEKS;

                $sheet->getColumnDimension('A')->setWidth(34);
                for ($c = 2; $c <= $this->jumlahKolom; $c++) {
                    $sheet->getColumnDimensionByColumn($c)->setWidth(14);
                }

                // Judul sheet & judul bagian
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                foreach ($this->judul as $r) {
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize($r === 1 ? 14 : 12);
                }

                // Teks (kesimpulan, catatan): digabung supaya terbaca penuh.
                foreach ($this->teks as $r) {
                    $nilai = (string) $sheet->getCell("A{$r}")->getValue();
                    $sheet->mergeCells("A{$r}:{$teksCol}{$r}");
                    $sheet->getStyle("A{$r}")->getAlignment()
                        ->setWrapText(true)
                        ->setVertical(Alignment::VERTICAL_TOP);
                    // Lebar A:I = 34 + 8x14 = 146 karakter; pakai ~125 supaya aman.
                    $sheet->getRowDimension($r)->setRowHeight(15 * max(1, (int) ceil(mb_strlen($nilai) / 125)));
                }

                // Judul tiap dimensi di bagian C (baris tunggal sebelum header tabel)
                // Kotak ringkasan singkat: latar kuning muda supaya langsung terlihat.
                if ($this->kotakAwal > 0) {
                    $kotak = "A{$this->kotakAwal}:{$teksCol}{$this->kotakAkhir}";
                    $sheet->getStyle($kotak)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF8DC');
                    $sheet->getStyle($kotak)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle("A{$this->kotakAwal}:A" . ($this->kotakAkhir - 1))->getFont()->setBold(true);
                }

                // Baris subtotal kelompok (bagian D)
                foreach ($this->tebal as $r) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
                }

                foreach ($this->tabel as $t) {
                    $kol = Coordinate::stringFromColumnIndex($t['kolom']);
                    $head = "A{$t['header']}:{$kol}{$t['header']}";
                    $sheet->getStyle($head)->getFont()->setBold(true);
                    $sheet->getStyle($head)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCE6F2');
                    $sheet->getStyle($head)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER)
                        ->setWrapText(true);

                    $isi = "A{$t['header']}:{$kol}{$t['akhir']}";
                    $sheet->getStyle($isi)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                    $angka = "B{$t['awal']}:{$kol}{$t['akhir']}";
                    $sheet->getStyle($angka)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    if (isset($t['angkaSemua'])) {
                        $sheet->getStyle($angka)->getNumberFormat()->setFormatCode($t['angkaSemua']);
                    }
                    foreach ($t['format'] as $col => $kode) {
                        $sheet->getStyle("{$col}{$t['awal']}:{$col}{$t['akhir']}")
                            ->getNumberFormat()->setFormatCode($kode);
                    }

                    // Warna arah: hijau naik, merah turun, abu-abu tetap.
                    if (isset($t['arahKol'])) {
                        for ($r = $t['awal']; $r <= $t['akhir']; $r++) {
                            $arah = (string) $sheet->getCell("{$t['arahKol']}{$r}")->getValue();
                            $warna = str_starts_with($arah, '▲') ? '1E7B34' : (str_starts_with($arah, '▼') ? 'C00000' : '7F7F7F');
                            $sheet->getStyle("{$t['arahKol']}{$r}")->getFont()->setBold(true)->getColor()->setRGB($warna);
                            foreach ($t['selisihKol'] as $kolSel) {
                                $sheet->getStyle("{$kolSel}{$r}")->getFont()->getColor()->setRGB($warna);
                            }
                            $sheet->getStyle("{$t['arahKol']}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        }
                    }

                    if ($t['total'] !== null) {
                        $tot = "A{$t['total']}:{$kol}{$t['total']}";
                        $sheet->getStyle($tot)->getFont()->setBold(true);
                        $sheet->getStyle($tot)->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCD5B5');
                    }
                }

                $this->applyPrintSetup($sheet, "A1:{$lastCol}{$lastRow}", 0, 'landscape');
            },
        ];
    }
}