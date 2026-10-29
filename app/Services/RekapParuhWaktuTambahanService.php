<?php

namespace App\Services;

use App\Models\Pegawai;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;

/**
 * Informasi tambahan untuk rekap PPPK Paruh Waktu:
 *   - per kelompok usia
 *   - perkiraan masa pensiun (per tahun)
 *   - rincian JF (Pendidikan / Kesehatan / Teknis)
 *   - jabatan terbanyak
 *   - persentase Paruh Waktu per instansi
 *
 * Dipisah dari RekapService supaya file RekapService yang besar tidak
 * perlu diubah. Semua baris kategori memakai bentuk yang sama dengan
 * RekapService::rekapParuhWaktuJenisJabatan(), yaitu:
 *   ['kategori' => ..., 'pria' => ..., 'wanita' => ..., 'jumlah' => ...]
 * sehingga bisa dipakai ulang oleh tabel layar dan sheet Excel.
 */
class RekapParuhWaktuTambahanService
{
    /** Jumlah jabatan yang ditampilkan sebelum digabung ke "Jabatan lainnya". */
    private const JABATAN_TERATAS = 10;

    /** Dipakai endpoint JSON /rekap/paruh-waktu. */
    public function semua(?string $periode = null): array
    {
        $periode ??= now()->format('Y-m');

        return [
            'per_usia' => $this->rekapUsia($periode),
            'per_pensiun' => $this->rekapPerkiraanPensiun($periode),
            'per_jf_rumpun' => $this->rekapJfRumpun($periode),
            'per_jabatan' => $this->rekapJabatanTerbanyak($periode),
            'per_persentase_instansi' => $this->rekapPersentaseInstansi($periode),
            'per_instansi_tanpa_pw' => $this->rekapInstansiTanpaParuhWaktu($periode),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Kelompok usia
    |--------------------------------------------------------------------------
    */

    /**
     * Usia dihitung pada akhir bulan periode (bukan hari ini), supaya angka
     * periode yang sama selalu menghasilkan hasil yang sama.
     */
    public function rekapUsia(?string $periode = null): array
    {
        $acuan = $this->akhirPeriode($periode);

        $pegawai = Pegawai::paruhWaktu()
            ->where('pegawai.status_aktif', 'aktif')
            ->get(['pegawai.tanggal_lahir', 'pegawai.jenis_kelamin']);

        $hasil = $this->kerangka([
            'A' => 'Sampai 30 tahun',
            'B' => '31 - 40 tahun',
            'C' => '41 - 50 tahun',
            'D' => '51 - 60 tahun',
            'E' => 'Di atas 60 tahun',
        ]);

        foreach ($pegawai as $p) {
            $kunci = '_kosong';

            if ($p->tanggal_lahir !== null) {
                $lahir = Carbon::parse($p->tanggal_lahir);
                $usia = $acuan->year - $lahir->year;
                if (($acuan->month * 100 + $acuan->day) < ($lahir->month * 100 + $lahir->day)) {
                    $usia--;
                }

                $kunci = match (true) {
                    $usia <= 30 => 'A',
                    $usia <= 40 => 'B',
                    $usia <= 50 => 'C',
                    $usia <= 60 => 'D',
                    default => 'E',
                };
            }

            $this->tambah($hasil, $kunci, $p->jenis_kelamin);
        }

        return $this->selesaikan($hasil);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Perkiraan masa pensiun
    |--------------------------------------------------------------------------
    */

    /**
     * Berdasarkan pegawai.tanggal_pensiun; bila kosong (Paruh Waktu) memakai
     * pegawai.tanggal_selesai_kontrak. Dikelompokkan per tahun, dari tahun
     * periode sampai 5 tahun ke depan, sisanya digabung.
     */
    public function rekapPerkiraanPensiun(?string $periode = null): array
    {
        $mulai = $this->akhirPeriode($periode)->copy()->startOfMonth();
        $tahunAwal = $mulai->year;
        $tahunAkhir = $tahunAwal + 5;

        $kelompok = ['_lewat' => 'Sudah melewati batas pensiun'];
        for ($t = $tahunAwal; $t <= $tahunAkhir; $t++) {
            $kelompok[(string) $t] = 'Tahun ' . $t;
        }
        $kelompok['_lanjut'] = 'Tahun ' . ($tahunAkhir + 1) . ' dan sesudahnya';

        $hasil = $this->kerangka($kelompok);

        $pegawai = Pegawai::paruhWaktu()
            ->where('pegawai.status_aktif', 'aktif')
            ->get(['pegawai.tanggal_pensiun', 'pegawai.tanggal_selesai_kontrak', 'pegawai.jenis_kelamin']);

        foreach ($pegawai as $p) {
            // Saat import, tanggal_pensiun hanya diisi untuk PNS; tanggal Paruh Waktu
            // disimpan di tanggal_selesai_kontrak. Pakai keduanya agar tidak kosong.
            $tanggal = $p->tanggal_pensiun ?? $p->tanggal_selesai_kontrak;

            if ($tanggal === null) {
                $kunci = '_kosong';
            } else {
                $tgl = Carbon::parse($tanggal);

                if ($tgl->lt($mulai)) {
                    $kunci = '_lewat';
                } elseif ($tgl->year <= $tahunAkhir) {
                    $kunci = (string) $tgl->year;
                } else {
                    $kunci = '_lanjut';
                }
            }

            $this->tambah($hasil, $kunci, $p->jenis_kelamin);
        }

        // Baris "Sudah melewati batas" hanya tampil kalau ada isinya.
        if (($hasil['_lewat']['jumlah'] ?? 0) === 0) {
            unset($hasil['_lewat']);
        }

        return $this->selesaikan($hasil);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Rincian JF (Pendidikan / Kesehatan / Teknis)
    |--------------------------------------------------------------------------
    */

    /**
     * Hanya pegawai Paruh Waktu dengan jenis_kedudukan FUNGSIONAL. Klasifikasi
     * memakai RekapService::klasifikasiJft() (kata kunci yang sama dengan
     * kartu JFT di Ringkasan Dashboard), jadi tidak ada aturan ganda.
     */
    public function rekapJfRumpun(?string $periode = null): array
    {
        $rekap = new RekapService();
        $klasifikasi = new ReflectionMethod($rekap, 'klasifikasiJft');

        $hasil = $this->kerangka([
            'pendidikan' => 'Pendidikan (Guru)',
            'kesehatan' => 'Kesehatan',
            'teknis' => 'Teknis / lainnya',
        ]);

        $pegawai = Pegawai::paruhWaktu()
            ->where('pegawai.status_aktif', 'aktif')
            ->where('pegawai.jenis_kedudukan', 'FUNGSIONAL')
            ->get(['pegawai.jabatan', 'pegawai.jenis_kelamin']);

        foreach ($pegawai as $p) {
            $kunci = $klasifikasi->invoke($rekap, (string) $p->jabatan);
            $this->tambah($hasil, $kunci, $p->jenis_kelamin);
        }

        return $this->selesaikan($hasil);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Jabatan terbanyak
    |--------------------------------------------------------------------------
    */

    /**
     * 10 jabatan dengan pegawai Paruh Waktu terbanyak. Sisanya digabung ke
     * "Jabatan lainnya" supaya total tetap sama dengan jumlah Paruh Waktu.
     * Nama jabatan disamakan (huruf besar, spasi rapi) karena di data sering
     * beda huruf besar/kecil untuk jabatan yang sama.
     */
    public function rekapJabatanTerbanyak(?string $periode = null): array
    {
        $baris = $this->urutanJabatan();

        $teratas = array_slice($baris, 0, self::JABATAN_TERATAS);
        $sisa = array_slice($baris, self::JABATAN_TERATAS);

        if ($sisa !== []) {
            $lain = ['kategori' => 'Jabatan lainnya (' . count($sisa) . ' jenis)', 'pria' => 0, 'wanita' => 0, 'jumlah' => 0];
            foreach ($sisa as $s) {
                $lain['pria'] += $s['pria'];
                $lain['wanita'] += $s['wanita'];
                $lain['jumlah'] += $s['jumlah'];
            }
            $teratas[] = $lain;
        }

        return $teratas;
    }

    /**
     * Rincian jabatan yang digabung ke baris "Jabatan lainnya" pada
     * rekapJabatanTerbanyak(). Dihitung dari data yang sama, jadi selalu
     * ikut berubah setiap kali ada import baru. Kosong kalau semua jabatan
     * sudah muat di daftar teratas.
     *
     * @return array<int, array{kategori: string, pria: int, wanita: int, jumlah: int}>
     */
    public function rincianJabatanLainnya(?string $periode = null): array
    {
        return array_slice($this->urutanJabatan(), self::JABATAN_TERATAS);
    }

    /** Semua jabatan Paruh Waktu aktif, urut dari yang terbanyak. */
    private function urutanJabatan(): array
    {
        $pegawai = Pegawai::paruhWaktu()
            ->where('pegawai.status_aktif', 'aktif')
            ->get(['pegawai.jabatan', 'pegawai.jenis_kelamin']);

        $perJabatan = [];
        foreach ($pegawai as $p) {
            $nama = mb_strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) $p->jabatan)));
            if ($nama === '' || $nama === '-') {
                $nama = '(BELUM DIISI)';
            }

            $perJabatan[$nama] ??= ['kategori' => $nama, 'pria' => 0, 'wanita' => 0];
            $perJabatan[$nama][$p->jenis_kelamin === 'L' ? 'pria' : 'wanita']++;
        }

        $baris = array_values($perJabatan);
        foreach ($baris as &$b) {
            $b['jumlah'] = $b['pria'] + $b['wanita'];
        }
        unset($b);

        usort($baris, fn ($a, $b) => [$b['jumlah'], $a['kategori']] <=> [$a['jumlah'], $b['kategori']]);

        return $baris;
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Persentase Paruh Waktu per instansi
    |--------------------------------------------------------------------------
    */

    /**
     * Persentase = Paruh Waktu / seluruh pegawai aktif di instansi tsb
     * (semua status: PNS, PPPK, Paruh Waktu, Lainnya) x 100. Hanya instansi
     * yang punya Paruh Waktu. Urutan: jumlah Paruh Waktu terbanyak dulu,
     * sama dengan tabel per instansi.
     */
    public function rekapPersentaseInstansi(?string $periode = null): array
    {
        $rows = Pegawai::query()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->where('pegawai.status_aktif', 'aktif')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'pegawai.status_kepegawaian',
                DB::raw('COUNT(*) as jumlah')
            )
            ->groupBy('instansi.id', 'instansi.nama', 'pegawai.status_kepegawaian')
            ->get();

        $perInstansi = [];
        foreach ($rows as $row) {
            $id = $row->instansi_id;
            $perInstansi[$id] ??= ['instansi' => $row->instansi_nama, 'paruh_waktu' => 0, 'total_pegawai' => 0];

            $jumlah = (int) $row->jumlah;
            $perInstansi[$id]['total_pegawai'] += $jumlah;

            if ($row->status_kepegawaian === 'PPPK_PW') {
                $perInstansi[$id]['paruh_waktu'] += $jumlah;
            }
        }

        $hasil = [];
        foreach ($perInstansi as $data) {
            if ($data['paruh_waktu'] === 0) {
                continue;
            }

            $data['persen'] = round($data['paruh_waktu'] / $data['total_pegawai'] * 100, 1);
            $hasil[] = $data;
        }

        usort($hasil, fn ($a, $b) => [$b['paruh_waktu'], $a['instansi']] <=> [$a['paruh_waktu'], $b['instansi']]);

        return $hasil;
    }

    /**
     * Instansi yang TIDAK punya PPPK Paruh Waktu beserta jumlah pegawai
     * aktifnya. Pegawai ini tidak ikut kolom "Total Pegawai" pada tabel
     * persentase (penyebutnya hanya dari instansi yang punya Paruh Waktu),
     * jadi dipakai untuk keterangan selisih terhadap total keseluruhan.
     */
    public function rekapInstansiTanpaParuhWaktu(?string $periode = null): array
    {
        $rows = Pegawai::query()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->where('pegawai.status_aktif', 'aktif')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'pegawai.status_kepegawaian',
                DB::raw('COUNT(*) as jumlah')
            )
            ->groupBy('instansi.id', 'instansi.nama', 'pegawai.status_kepegawaian')
            ->get();

        $perInstansi = [];
        foreach ($rows as $row) {
            $id = $row->instansi_id;
            $perInstansi[$id] ??= ['instansi' => $row->instansi_nama, 'jumlah' => 0, 'ada_pw' => false, 'status' => []];
            $perInstansi[$id]['jumlah'] += (int) $row->jumlah;
            $perInstansi[$id]['status'][$row->status_kepegawaian] = ($perInstansi[$id]['status'][$row->status_kepegawaian] ?? 0) + (int) $row->jumlah;

            if ($row->status_kepegawaian === 'PPPK_PW') {
                $perInstansi[$id]['ada_pw'] = true;
            }
        }

        $hasil = [];
        foreach ($perInstansi as $data) {
            if ($data['ada_pw']) {
                continue;
            }
            $hasil[] = [
                'instansi' => $data['instansi'],
                'jumlah' => $data['jumlah'],
                'status' => $data['status'],
            ];
        }

        usort($hasil, fn ($a, $b) => [$b['jumlah'], $a['instansi']] <=> [$a['jumlah'], $b['instansi']]);

        return $hasil;
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    /** Akhir bulan periode ('Y-m'); kalau format salah, pakai bulan berjalan. */
    private function akhirPeriode(?string $periode): Carbon
    {
        try {
            return Carbon::parse(($periode ?: now()->format('Y-m')) . '-01')->endOfMonth();
        } catch (\Throwable $e) {
            return now()->endOfMonth();
        }
    }

    /** Siapkan baris kosong per kategori + baris "Belum diisi / lainnya". */
    private function kerangka(array $label): array
    {
        $hasil = [];
        foreach ($label as $kunci => $nama) {
            $hasil[$kunci] = ['kategori' => $nama, 'pria' => 0, 'wanita' => 0];
        }
        $hasil['_kosong'] = ['kategori' => 'Belum diisi / lainnya', 'pria' => 0, 'wanita' => 0];

        return $hasil;
    }

    private function tambah(array &$hasil, string $kunci, ?string $jenisKelamin): void
    {
        if (!isset($hasil[$kunci])) {
            $kunci = '_kosong';
        }

        // Sama dengan rekap lain: selain 'L' dihitung wanita.
        $hasil[$kunci][$jenisKelamin === 'L' ? 'pria' : 'wanita']++;
    }

    /** Hitung jumlah, sembunyikan "Belum diisi" kalau kosong. */
    private function selesaikan(array $hasil): array
    {
        if (($hasil['_kosong']['pria'] ?? 0) + ($hasil['_kosong']['wanita'] ?? 0) === 0) {
            unset($hasil['_kosong']);
        }

        foreach ($hasil as &$data) {
            $data['jumlah'] = $data['pria'] + $data['wanita'];
        }
        unset($data);

        return array_values($hasil);
    }
}