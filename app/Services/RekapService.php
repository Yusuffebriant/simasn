<?php

namespace App\Services;

use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\KlasifikasiJft;
use App\Support\UrutanInstansi;

class RekapService
{
    /**
     * Batas bawah usia wajar untuk pegawai aktif (tahun). Dipakai sebagai
     * pengaman data anomali di rekapDashboard() — lihat catatan di sana.
     */
    private const USIA_MINIMAL_WAJAR = 17;

    public function rekapAgama(?string $periode = null): array
    {
        $agamaList = ['Islam', 'Kristen', 'Katholik', 'Hindu', 'Budha'];

        // Agama di luar lima kolom laporan (Konghucu) dan yang belum diisi
        // tidak boleh hilang dari hitungan. Kolom laporan tetap lima (sesuai
        // format laporan), jadi keduanya dicatat terpisah di 'lainnya'.
        $lainnyaList = ['Konghucu', 'Tidak diketahui'];

        $rows = Pegawai::asnPenuh()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->leftJoin('agama', 'agama.id', '=', 'pegawai.agama_id')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'agama.nama as agama_nama',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->groupBy(
                'instansi.id',
                'instansi.nama',
                'agama.nama',
                'pegawai.jenis_kelamin'
            )
            ->get();

        $perInstansi = [];

        foreach ($rows as $row) {
            $id = $row->instansi_id;

            if (!isset($perInstansi[$id])) {
                $perInstansi[$id] = [
                    'instansi' => $row->instansi_nama,
                    'pria' => array_fill_keys($agamaList, 0),
                    'wanita' => array_fill_keys($agamaList, 0),
                    'lainnya' => array_fill_keys($lainnyaList, 0),
                ];
            }

            $kelompok = $row->jenis_kelamin === 'L'
                ? 'pria'
                : 'wanita';

            // Dijumlahkan (+=), bukan ditimpa: nilai jenis_kelamin selain
            // 'L' semuanya masuk 'wanita' dan bisa lebih dari satu baris.
            if (in_array($row->agama_nama, $agamaList, true)) {
                $perInstansi[$id][$kelompok][$row->agama_nama] += (int) $row->jumlah;
            } elseif ($row->agama_nama === 'Konghucu') {
                $perInstansi[$id]['lainnya']['Konghucu'] += (int) $row->jumlah;
            } else {
                // Kosong, "-", atau nama agama yang tidak dikenali.
                $perInstansi[$id]['lainnya']['Tidak diketahui'] += (int) $row->jumlah;
            }
        }

        foreach ($perInstansi as &$data) {
            $data['jml_pria'] = array_sum($data['pria']);
            $data['jml_wanita'] = array_sum($data['wanita']);
            // Total laporan = hanya lima kolom agama (format laporan tetap).
            $data['jml_total'] =
                $data['jml_pria'] + $data['jml_wanita'];
            // ASN aktif yang tidak masuk lima kolom di atas.
            $data['jml_lainnya'] = array_sum($data['lainnya']);
        }
        unset($data);

        return UrutanInstansi::abjad(array_values($perInstansi));
    }

    public function rekapPendidikan(?string $periode = null): array
    {
        // Sesuai laporan final: 10 kategori, tanpa kolom "BELUM DIISI".
        $pendidikanList = [
            'SD',
            'SLTP',
            'SLTA',
            'D I',
            'D II',
            'D III',
            'D IV',
            'S1',
            'S2',
            'S3',
        ];

        $rows = Pegawai::asnPenuh()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->leftJoin('pendidikan', 'pendidikan.id', '=', 'pegawai.pendidikan_id')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'pendidikan.jenjang as pendidikan_nama',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->groupBy(
                'instansi.id',
                'instansi.nama',
                'pendidikan.jenjang',
                'pegawai.jenis_kelamin'
            )
            ->get();

        $perInstansi = [];

        foreach ($rows as $row) {
            $id = $row->instansi_id;

            if (!isset($perInstansi[$id])) {
                $perInstansi[$id] = [
                    'instansi' => $row->instansi_nama,
                    'pria' => array_fill_keys($pendidikanList, 0),
                    'wanita' => array_fill_keys($pendidikanList, 0),
                    'tidak_dikenali' => 0,
                ];
            }

            $pendidikanRaw = $row->pendidikan_nama
                ? strtoupper(trim($row->pendidikan_nama))
                : null;

            $pendidikan = $pendidikanRaw ? match ($pendidikanRaw) {
                'SD', 'SEKOLAH DASAR'                              => 'SD',
                'SMP', 'SLTP'                                      => 'SLTP',
                'SMA', 'SMK', 'SMA/SMK', 'SLTA', 'SLTA KEJURUAN'   => 'SLTA',
                'D1', 'D-1', 'D I', 'DIPLOMA I'                     => 'D I',
                'D2', 'D-2', 'D II', 'DIPLOMA II'                   => 'D II',
                'D3', 'D-3', 'D III', 'DIPLOMA III/SARJANA'         => 'D III',
                'D4', 'D-4', 'D IV', 'D4/S1', 'DIPLOMA IV'          => 'D IV',
                'S1', 'S-1', 'S-1/SARJANA', 'SARJANA'               => 'S1',
                'S2', 'S-2', 'S-2/MAGISTER'                         => 'S2',
                'S3', 'S-3', 'S-3/DOKTOR'                           => 'S3',
                default => null,
            } : null;

            $kelompok = $row->jenis_kelamin === 'L' ? 'pria' : 'wanita';
            $jumlah = (int) $row->jumlah;

            if ($pendidikan === null) {
                $perInstansi[$id]['tidak_dikenali'] += $jumlah;

                if ($pendidikanRaw !== null) {
                    Log::warning('RekapPendidikan: jenjang pendidikan tidak dikenali', [
                        'instansi_id' => $id,
                        'pendidikan_raw' => $row->pendidikan_nama,
                        'jumlah' => $jumlah,
                    ]);
                }

                continue;
            }

            $perInstansi[$id][$kelompok][$pendidikan] =
                ($perInstansi[$id][$kelompok][$pendidikan] ?? 0) + $jumlah;
        }

        foreach ($perInstansi as &$data) {
            $data['jml_pria'] = array_sum($data['pria']);
            $data['jml_wanita'] = array_sum($data['wanita']);
            $data['jml_total'] =
                $data['jml_pria'] + $data['jml_wanita'] + $data['tidak_dikenali'];
        }

        return UrutanInstansi::abjad(array_values($perInstansi));
    }

    public function rekapGolongan(?string $periode = null): array
    {
        $golonganList = [
            'I/a',
            'I/b',
            'I/c',
            'I/d',
            'II/a',
            'II/b',
            'II/c',
            'II/d',
            'III/a',
            'III/b',
            'III/c',
            'III/d',
            'IV/a',
            'IV/b',
            'IV/c',
            'IV/d',
            'IV/e',
            'BELUM DIISI',
        ];
        $pppkList = ['I', 'III', 'V', 'VII', 'IX', 'X', 'XI'];

        $kolomList = array_merge($golonganList, $pppkList);

        $rows = Pegawai::asnPenuh()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->leftJoin('golongan_ruang', 'golongan_ruang.id', '=', 'pegawai.golongan_ruang_id')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'golongan_ruang.kode as golongan_kode',
                'golongan_ruang.kelompok as golongan_kelompok',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->groupBy(
                'instansi.id',
                'instansi.nama',
                'golongan_ruang.kode',
                'golongan_ruang.kelompok',
                'pegawai.jenis_kelamin'
            )
            ->get();

        $perInstansi = [];

        foreach ($rows as $row) {
            $id = $row->instansi_id;

            if (!isset($perInstansi[$id])) {
                $perInstansi[$id] = [
                    'instansi' => $row->instansi_nama,
                    'pria' => array_fill_keys($kolomList, 0),
                    'wanita' => array_fill_keys($kolomList, 0),
                    'pppk' => array_fill_keys($pppkList, 0),
                ];
            }

            $jumlah = (int) $row->jumlah;
            $kelompok = $row->jenis_kelamin === 'L' ? 'pria' : 'wanita';

            if ($row->golongan_kode === null) {
                $perInstansi[$id][$kelompok]['BELUM DIISI'] =
                    ($perInstansi[$id][$kelompok]['BELUM DIISI'] ?? 0) + $jumlah;
                continue;
            }

            $kode = trim($row->golongan_kode);

            if ($row->golongan_kelompok === 'PPPK') {
                if (!in_array($kode, $pppkList, true)) {
                    continue;
                }

                $perInstansi[$id][$kelompok][$kode] =
                    ($perInstansi[$id][$kelompok][$kode] ?? 0) + $jumlah;

                $perInstansi[$id]['pppk'][$kode] =
                    ($perInstansi[$id]['pppk'][$kode] ?? 0) + $jumlah;

                continue;
            }

            if (!in_array($kode, $golonganList, true)) {
                continue;
            }

            $perInstansi[$id][$kelompok][$kode] =
                ($perInstansi[$id][$kelompok][$kode] ?? 0) + $jumlah;
        }

        foreach ($perInstansi as &$data) {
            $data['jml_pria'] = array_sum($data['pria']);
            $data['jml_wanita'] = array_sum($data['wanita']);
            $data['jml_total'] = $data['jml_pria'] + $data['jml_wanita'];

            $gabungan = [];
            foreach ($data['pria'] as $kode => $jumlah) {
                $gabungan[$kode] = $jumlah + ($data['wanita'][$kode] ?? 0);
            }

            $pnsAgg = ['I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0];
            foreach ($gabungan as $kode => $jumlah) {
                if (!str_contains($kode, '/')) {
                    continue;
                }
                $romawi = explode('/', $kode)[0];
                if (isset($pnsAgg[$romawi])) {
                    $pnsAgg[$romawi] += $jumlah;
                }
            }
            $data['pns_agg'] = $pnsAgg;
            $data['pns_total'] = array_sum($pnsAgg);

            $data['pppk_total'] = array_sum($data['pppk']);
        }

        return UrutanInstansi::abjad(array_values($perInstansi));
    }

    public function rekapJabatan(?string $periode = null): array
    {
        // Klasifikasi Fungsional Umum vs Fungsional Tertentu memakai
        // pegawai.jenis_kedudukan langsung (nilai: 'FUNGSIONAL',
        // 'PELAKSANA', 'STRUKTURAL', dst) — BUKAN menebak dari teks
        // bebas pegawai.jabatan lewat regex. Baris Eselon tetap ditentukan
        // dari eselon.kode seperti sebelumnya (sudah akurat).
        $rows = Pegawai::asnPenuh()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->leftJoin('eselon', 'eselon.id', '=', 'pegawai.eselon_id')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'pegawai.jenis_kedudukan',
                'eselon.kode as eselon_kode',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->groupBy(
                'instansi.id',
                'instansi.nama',
                'pegawai.jenis_kedudukan',
                'eselon.kode'
            )
            ->get();

        $eselonList = ['II A', 'II B', 'III A', 'III B', 'IV A', 'IV B'];
        $perInstansi = [];

        foreach ($rows as $row) {
            $id = $row->instansi_id;

            if (!isset($perInstansi[$id])) {
                $perInstansi[$id] = [
                    'instansi' => $row->instansi_nama,
                    'eselon' => array_fill_keys($eselonList, 0),
                    'fungsional_umum' => 0,
                    'fungsional_tertentu' => 0,
                ];
            }

            $jumlah = (int) $row->jumlah;
            $kodeEselon = strtoupper(trim((string) $row->eselon_kode));
            $kodeEselon = preg_replace('/\s+/', ' ', $kodeEselon);
            $jenisKedudukan = strtoupper(trim((string) $row->jenis_kedudukan));

            if (in_array($kodeEselon, $eselonList, true)) {
                $perInstansi[$id]['eselon'][$kodeEselon] += $jumlah;
            } elseif ($jenisKedudukan === 'FUNGSIONAL') {
                $perInstansi[$id]['fungsional_tertentu'] += $jumlah;
            } else {
                // 'PELAKSANA', 'STRUKTURAL' (yang lolos cek eselon di atas
                // karena data eselon-nya kosong/tidak baku), NULL, atau
                // varian lain masuk default ke Fungsional Umum supaya
                // JML TOTAL tetap konsisten dengan total pegawai aktif
                // (sesuai laporan resmi).
                $perInstansi[$id]['fungsional_umum'] += $jumlah;
            }
        }

        foreach ($perInstansi as &$data) {
            $data['jml_eselon'] = array_sum($data['eselon']);
            $data['jml_total'] = $data['jml_eselon']
                + $data['fungsional_umum']
                + $data['fungsional_tertentu'];
        }

        return UrutanInstansi::abjad(array_values($perInstansi));
    }

    /**
     * Basis query Dashboard. Default PNS + PPPK (asnPenuh). Untuk Dashboard
     * Total ($termasukParuhWaktu = true) ditambah PPPK Paruh Waktu dan
     * Lainnya, sehingga seluruh kartu dan grafik menghitung semua status.
     */
    private function basisDashboard(bool $termasukParuhWaktu)
    {
        return $termasukParuhWaktu ? Pegawai::totalPegawai() : Pegawai::asnPenuh();
    }

    /**
     * Ringkasan dashboard: total pegawai, jabatan (struktural/JFU/JFT),
     * distribusi generasi, golongan, dan pendidikan — semuanya dari satu
     * query (tidak N+1).
     *
     * Klasifikasi JFU/JFT memakai pegawai.jenis_kedudukan, sama seperti
     * rekapJabatan() (bukan regex isJabatanFungsional() versi lama —
     * method itu sudah dihapus karena sudah digantikan pendekatan ini).
     *
     * Mapping pendidikan di bawah disamakan persis dengan rekapPendidikan()
     * (termasuk varian SEKOLAH DASAR, SLTA KEJURUAN, DIPLOMA I-IV,
     * S-3/DOKTOR) supaya tidak mengulang bug lama di mana varian teks tak
     * dikenal membuat pegawai "hilang" dari kategori pendidikannya.
     */
    public function rekapDashboard(?string $periode = null, bool $termasukParuhWaktu = false): array
    {
        $eselonList = ['II A', 'II B', 'III A', 'III B', 'IV A', 'IV B'];

        $rows = $this->basisDashboard($termasukParuhWaktu)
            ->leftJoin('golongan_ruang', 'golongan_ruang.id', '=', 'pegawai.golongan_ruang_id')
            ->leftJoin('eselon', 'eselon.id', '=', 'pegawai.eselon_id')
            ->leftJoin('pendidikan', 'pendidikan.id', '=', 'pegawai.pendidikan_id')
            ->where('pegawai.status_aktif', 'aktif')
            ->select(
                'pegawai.nip',
                'pegawai.nama',
                'pegawai.jenis_kelamin',
                'pegawai.jenis_kedudukan',
                'pegawai.status_kepegawaian',
                'pegawai.jabatan',
                'pegawai.tanggal_lahir',
                'pegawai.tmt_pangkat',
                'golongan_ruang.kode as golongan_kode',
                'golongan_ruang.kelompok as golongan_kelompok',
                'eselon.kode as eselon_kode',
                'pendidikan.jenjang as pendidikan_jenjang'
            )
            ->get();

        $totalPria = 0;
        $totalWanita = 0;

        $strukturalPria = 0;
        $strukturalWanita = 0;
        $jfuPria = 0;
        $jfuWanita = 0;
        $jftPria = 0;
        $jftWanita = 0;

        // Rincian JFT jadi 3 rumpun sederhana untuk kartu Ringkasan
        // Dashboard (versi ringkas dari rumpunJabatanFungsional() di
        // StatistikService yang punya 6 rumpun detail untuk halaman
        // Statistik — sengaja dibedakan supaya kartu Ringkasan tetap
        // sederhana: Pendidikan, Kesehatan, Teknis).
        $jftRincianKeys = ['pendidikan', 'kesehatan', 'teknis'];
        $jftRincian = [];
        foreach ($jftRincianKeys as $jk) {
            $jftRincian[$jk] = ['pria' => 0, 'wanita' => 0];
        }

        $generasiKeys = ['Baby Boomer', 'Generasi X', 'Generasi Y', 'Generasi Z'];
        $generasi = [];
        foreach ($generasiKeys as $g) {
            $generasi[$g] = ['pria' => 0, 'wanita' => 0];
        }

        $mkPangkatKeys = ['s.d. 10 Tahun', '11 - 20 Tahun', '21 - 30 Tahun', '30 Tahun Keatas'];
        $mkPangkat = [];
        foreach ($mkPangkatKeys as $mk) {
            $mkPangkat[$mk] = ['pria' => 0, 'wanita' => 0];
        }

        $usiaKeys = ['s.d. 25 Tahun', '26 - 35 Tahun', '36 - 45 Tahun', '46 - 55 Tahun', '56 Tahun atau Lebih'];
        $usia = [];
        foreach ($usiaKeys as $u) {
            $usia[$u] = ['pria' => 0, 'wanita' => 0];
        }

        $golonganGroup = [
            'I' => 0,
            'II' => 0,
            'III' => 0,
            'IV' => 0,
            'PPPK' => 0,
            'BELUM DIISI' => 0,
        ];

        // Versi lengkap khusus Dashboard Total: pegawai tanpa golongan
        // (PPPK Paruh Waktu & Lainnya) dihitung sebagai batang sendiri,
        // supaya jumlah grafik Golongan = Total Pegawai. Kunci
        // 'golongan' (dipakai export Excel) sengaja TIDAK diubah.
        $golonganLengkap = [
            'I' => 0,
            'II' => 0,
            'III' => 0,
            'IV' => 0,
            'PPPK' => 0,
            'PPPK PW' => 0,
            'Lainnya' => 0,
            'BELUM DIISI' => 0,
        ];

        // Tanpa 'BELUM DIISI' — sama seperti rekapPendidikan(), pegawai
        // dengan jenjang pendidikan kosong/tak dikenal dilewatkan dari
        // rekap ini (lihat penanganannya di bawah), bukan ditumpuk jadi
        // satu kategori "BELUM DIISI" di tabel & grafik Pendidikan.
        $pendidikanList = [
            'SD',
            'SLTP',
            'SLTA',
            'D I',
            'D II',
            'D III',
            'D IV',
            'S1',
            'S2',
            'S3',
        ];
        $pendidikanGroup = [];
        foreach ($pendidikanList as $pl) {
            $pendidikanGroup[$pl] = ['pria' => 0, 'wanita' => 0];
        }

        foreach ($rows as $row) {
            $isPria = $row->jenis_kelamin === 'L';
            $isPria ? $totalPria++ : $totalWanita++;

            $kodeEselon = preg_replace('/\s+/', ' ', strtoupper(trim((string) $row->eselon_kode)));
            $jenisKedudukan = strtoupper(trim((string) $row->jenis_kedudukan));

            if (in_array($kodeEselon, $eselonList, true)) {
                $isPria ? $strukturalPria++ : $strukturalWanita++;
            } elseif ($jenisKedudukan === 'FUNGSIONAL') {
                $isPria ? $jftPria++ : $jftWanita++;

                $kategoriJft = $this->klasifikasiJft((string) $row->jabatan);
                $jftRincian[$kategoriJft][$isPria ? 'pria' : 'wanita']++;
            } else {
                $isPria ? $jfuPria++ : $jfuWanita++;
            }

            if ($row->tanggal_lahir) {
                // FIX: di Carbon 3 (Laravel 13), diffInYears() sekarang
                // mengembalikan float presisi tinggi (mis. 25.69), bukan
                // integer umur genap seperti Carbon 2 (25). Kalau dipakai
                // langsung di sini, siapa pun yang usianya PERSIS di batas
                // bucket (25/35/45/55 tahun) akan salah nyasar ke bucket
                // di atasnya sepanjang tahun setelah ulang tahunnya. Pakai
                // properti ->age, yang selalu mengembalikan umur genap
                // (integer) berapa pun versi Carbon-nya.
                $usiaTahun = $row->tanggal_lahir->age;

                // Sebagian kecil data tanggal_lahir hasil import ternyata
                // salah entry sehingga usia terhitungnya tidak wajar untuk
                // pegawai aktif (mis. tanggal_lahir 2023 -> usia hitungan
                // cuma beberapa tahun). Baris seperti ini SENGAJA tetap
                // dihitung apa adanya (masuk ke bucket usia sesuai angka
                // mentahnya) alih-alih dibuang atau dipisah ke bucket
                // "tidak valid" — supaya total tabel Usia & kartu Generasi
                // selalu rekonsil satu sama lain dan dengan Total Pegawai.
                // Konsekuensinya: bucket usia termuda bisa sedikit
                // terdistorsi kalau ada data tanggal_lahir yang salah
                // entry seperti ini. Warning tetap dicatat supaya admin
                // bisa menelusuri & membenahi datanya di sumber import.
                if ($usiaTahun < self::USIA_MINIMAL_WAJAR) {
                    Log::warning('RekapDashboard: usia pegawai tidak wajar (kemungkinan tanggal_lahir salah entry), tetap dihitung apa adanya', [
                        'nip' => $row->nip ?? null,
                        'nama' => $row->nama ?? null,
                        'tanggal_lahir' => (string) $row->tanggal_lahir,
                        'usia_tahun' => $usiaTahun,
                    ]);
                }

                $kategoriUsia = match (true) {
                    $usiaTahun <= 25 => 's.d. 25 Tahun',
                    $usiaTahun <= 35 => '26 - 35 Tahun',
                    $usiaTahun <= 45 => '36 - 45 Tahun',
                    $usiaTahun <= 55 => '46 - 55 Tahun',
                    default => '56 Tahun atau Lebih',
                };

                $usia[$kategoriUsia][$isPria ? 'pria' : 'wanita']++;

                $tgl = trim((string) $row->tanggal_lahir);
                $tahun = null;

                if (preg_match('/^(\d{4})[-\/]/', $tgl, $matches)) {
                    $tahun = (int) $matches[1];
                } elseif (preg_match('/^\d{1,2}[-\/]\d{1,2}[-\/](\d{4})/', $tgl, $matches)) {
                    $tahun = (int) $matches[1];
                }

                if ($tahun !== null) {
                    $g = match (true) {
                        $tahun >= 1946 && $tahun <= 1964 => 'Baby Boomer',
                        $tahun >= 1965 && $tahun <= 1980 => 'Generasi X',
                        $tahun >= 1981 && $tahun <= 1996 => 'Generasi Y',
                        $tahun >= 1997 && $tahun <= 2012 => 'Generasi Z',
                        default => null,
                    };

                    if ($g) {
                        $generasi[$g][$isPria ? 'pria' : 'wanita']++;
                    }
                }
            }

            if ($row->tmt_pangkat) {
                // Sama seperti fix usia di atas: pakai ->age (integer),
                // bukan diffInYears(now()) (float presisi tinggi di
                // Carbon 3) supaya masa kerja persis di batas bucket
                // (10/20/30 tahun) tidak salah nyasar ke bucket atasnya.
                $masaKerja = $row->tmt_pangkat->age;

                $kategoriMk = match (true) {
                    $masaKerja <= 10 => 's.d. 10 Tahun',
                    $masaKerja <= 20 => '11 - 20 Tahun',
                    $masaKerja <= 30 => '21 - 30 Tahun',
                    default => '30 Tahun Keatas',
                };

                $mkPangkat[$kategoriMk][$isPria ? 'pria' : 'wanita']++;
            }

            if (!$row->golongan_kode) {
                $golonganGroup['BELUM DIISI']++;

                $kunciLengkap = match ($row->status_kepegawaian) {
                    'PPPK_PW' => 'PPPK PW',
                    'LAINNYA' => 'Lainnya',
                    default => 'BELUM DIISI',
                };
                $golonganLengkap[$kunciLengkap]++;
            } elseif ($row->golongan_kelompok === 'PPPK') {
                $golonganGroup['PPPK']++;
                $golonganLengkap['PPPK']++;
            } else {
                $romawi = explode('/', trim($row->golongan_kode))[0] ?? null;
                if (isset($golonganGroup[$romawi])) {
                    $golonganGroup[$romawi]++;
                    $golonganLengkap[$romawi]++;
                }
            }

            $pnd = $row->pendidikan_jenjang ? strtoupper(trim($row->pendidikan_jenjang)) : null;
            $pnd = $pnd ? preg_replace('/\s+/', ' ', $pnd) : null;

            $pnd = $pnd ? match ($pnd) {
                'SD', 'SEKOLAH DASAR'                              => 'SD',
                'SMP', 'SLTP'                                      => 'SLTP',
                'SMA', 'SMK', 'SMA/SMK', 'SLTA', 'SLTA KEJURUAN'   => 'SLTA',
                'D1', 'D-1', 'D I', 'DIPLOMA I'                     => 'D I',
                'D2', 'D-2', 'D II', 'DIPLOMA II'                   => 'D II',
                'D3', 'D-3', 'D III', 'DIPLOMA III/SARJANA'         => 'D III',
                'D4', 'D-4', 'D IV', 'D4/S1', 'DIPLOMA IV'          => 'D IV',
                'S1', 'S-1', 'S-1/SARJANA', 'SARJANA'               => 'S1',
                'S2', 'S-2', 'S-2/MAGISTER'                         => 'S2',
                'S3', 'S-3', 'S-3/DOKTOR'                           => 'S3',
                default => null,
            } : null;

            // Jenjang kosong/tidak dikenal dilewatkan dari tabel & grafik
            // Pendidikan (tanpa kategori "BELUM DIISI") — lihat catatan di
            // definisi $pendidikanList di atas.
            if ($pnd !== null) {
                $pendidikanGroup[$pnd][$isPria ? 'pria' : 'wanita']++;
            }
        }

        // Rekap per Unit Kerja (instansi)
        $unitKerjaRows = $this->basisDashboard($termasukParuhWaktu)
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->where('pegawai.status_aktif', 'aktif')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->groupBy('instansi.id', 'instansi.nama', 'pegawai.jenis_kelamin')
            ->get();

        $unitKerjaMap = [];
        foreach ($unitKerjaRows as $row) {
            $id = $row->instansi_id;

            if (!isset($unitKerjaMap[$id])) {
                $unitKerjaMap[$id] = [
                    'label' => $row->instansi_nama,
                    'pria' => 0,
                    'wanita' => 0,
                ];
            }

            if ($row->jenis_kelamin === 'L') {
                $unitKerjaMap[$id]['pria'] = (int) $row->jumlah;
            } else {
                $unitKerjaMap[$id]['wanita'] = (int) $row->jumlah;
            }
        }

        $unitKerja = array_values($unitKerjaMap);

        foreach ($unitKerja as &$uk) {
            $uk['total'] = $uk['pria'] + $uk['wanita'];
        }
        unset($uk);

        usort($unitKerja, fn($a, $b) => strcmp($a['label'], $b['label']));

        // Rekap per Agama
        $agamaRows = $this->basisDashboard($termasukParuhWaktu)
            ->join('agama', 'agama.id', '=', 'pegawai.agama_id')
            ->where('pegawai.status_aktif', 'aktif')
            ->select(
                'agama.id as agama_id',
                'agama.nama as agama_nama',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->groupBy('agama.id', 'agama.nama', 'pegawai.jenis_kelamin')
            ->get();

        $agamaMap = [];
        foreach ($agamaRows as $row) {
            $id = $row->agama_id;

            if (!isset($agamaMap[$id])) {
                $agamaMap[$id] = [
                    'label' => $row->agama_nama,
                    'pria' => 0,
                    'wanita' => 0,
                ];
            }

            if ($row->jenis_kelamin === 'L') {
                $agamaMap[$id]['pria'] = (int) $row->jumlah;
            } else {
                $agamaMap[$id]['wanita'] = (int) $row->jumlah;
            }
        }

        $agama = array_values($agamaMap);

        foreach ($agama as &$ag) {
            $ag['total'] = $ag['pria'] + $ag['wanita'];
        }
        unset($ag);

        usort($agama, fn($a, $b) => strcmp($a['label'], $b['label']));

        $hasil = [
            'total' => [
                'total' => $totalPria + $totalWanita,
                'pria' => $totalPria,
                'wanita' => $totalWanita,
            ],
            'jabatan' => [
                'struktural' => [
                    'total' => $strukturalPria + $strukturalWanita,
                    'pria' => $strukturalPria,
                    'wanita' => $strukturalWanita,
                ],
                'jfu' => [
                    'total' => $jfuPria + $jfuWanita,
                    'pria' => $jfuPria,
                    'wanita' => $jfuWanita,
                ],
                'jft' => [
                    'total' => $jftPria + $jftWanita,
                    'pria' => $jftPria,
                    'wanita' => $jftWanita,
                ],
                'jft_rincian' => array_map(
                    fn($label) => [
                        'label' => $label,
                        'total' => $jftRincian[$label]['pria'] + $jftRincian[$label]['wanita'],
                        'pria' => $jftRincian[$label]['pria'],
                        'wanita' => $jftRincian[$label]['wanita'],
                    ],
                    $jftRincianKeys
                ),
            ],
            'generasi' => array_values(array_map(
                fn($label) => [
                    'label' => $label,
                    'pria' => $generasi[$label]['pria'],
                    'wanita' => $generasi[$label]['wanita'],
                    'total' => $generasi[$label]['pria'] + $generasi[$label]['wanita'],
                ],
                $generasiKeys
            )),
            'masa_kerja_pangkat' => array_values(array_map(
                fn($label) => [
                    'label' => $label,
                    'pria' => $mkPangkat[$label]['pria'],
                    'wanita' => $mkPangkat[$label]['wanita'],
                    'total' => $mkPangkat[$label]['pria'] + $mkPangkat[$label]['wanita'],
                ],
                $mkPangkatKeys
            )),
            'usia' => array_values(array_map(
                fn($label) => [
                    'label' => $label,
                    'pria' => $usia[$label]['pria'],
                    'wanita' => $usia[$label]['wanita'],
                    'total' => $usia[$label]['pria'] + $usia[$label]['wanita'],
                ],
                $usiaKeys
            )),
            // Grafik Golongan tidak menampilkan kategori "BELUM DIISI"
            // (konsisten dengan Pendidikan di atas: data tak dikenal
            // dilewatkan dari grafik, bukan ditumpuk jadi satu batang
            // tersendiri).
            'golongan' => array_values(array_filter(
                array_map(
                    fn($label, $jumlah) => ['label' => $label, 'jumlah' => $jumlah],
                    array_keys($golonganGroup),
                    array_values($golonganGroup)
                ),
                fn($row) => $row['label'] !== 'BELUM DIISI'
            )),
            'pendidikan' => array_map(
                fn($label) => [
                    'label' => $label,
                    'pria' => $pendidikanGroup[$label]['pria'],
                    'wanita' => $pendidikanGroup[$label]['wanita'],
                    'jumlah' => $pendidikanGroup[$label]['pria'] + $pendidikanGroup[$label]['wanita'],
                ],
                $pendidikanList
            ),
            'unit_kerja' => $unitKerja,
            'agama' => $agama,

            // Seluruh status (termasuk PPPK Paruh Waktu & Lainnya), tidak
            // ikut ter-scope asnPenuh() seperti angka lain di dashboard.
            // Dipakai kartu "Status Kepegawaian" di halaman utama.
            'ringkasan_status' => $this->ringkasanStatusKepegawaian(),
        ];

        // Status "Lainnya" disebutkan rinci (NABAN, Pegawai Titipan) di
        // kedua dashboard, karena kartu Status Kepegawaian tampil di keduanya.
        $hasil['rincian_lainnya'] = $this->rincianStatusLainnya();

        // Catatan selisih terhadap jumlah baris di Excel (hanya jumlah, tanpa
        // nama: endpoint dashboard publik).
        $hasil['catatan_data'] = $this->catatanData($termasukParuhWaktu);

        // Dashboard Total: grafik Golongan yang jumlahnya = Total Pegawai.
        // 'BELUM DIISI' (PNS/PPPK tanpa golongan) hanya muncul kalau ada.
        if ($termasukParuhWaktu) {
            $hasil['golongan_lengkap'] = array_values(array_filter(
                array_map(
                    fn($label, $jumlah) => ['label' => $label, 'jumlah' => $jumlah],
                    array_keys($golonganLengkap),
                    array_values($golonganLengkap)
                ),
                fn($row) => $row['label'] !== 'BELUM DIISI' || $row['jumlah'] > 0
            ));
        }

        return $hasil;
    }

    /**
     * Menjelaskan kenapa jumlah di dashboard bisa lebih sedikit daripada
     * jumlah baris di Excel. Seluruh pegawai tersimpan dibagi tiga yang
     * saling lepas, sehingga dihitung + tidak_aktif + di_luar_cakupan =
     * total_tersimpan:
     *  - dihitung        : berstatus aktif dan termasuk cakupan dashboard ini
     *  - tidak_aktif     : status_aktif bukan 'aktif' (meninggal, mutasi
     *                      keluar, pensiun), apa pun status kepegawaiannya
     *  - di_luar_cakupan : aktif tetapi bukan cakupan dashboard ini (mis.
     *                      PPPK Paruh Waktu & Lainnya pada Dashboard biasa)
     * Hanya jumlah yang dikembalikan, tanpa nama/NIP.
     */
    public function catatanData(bool $termasukParuhWaktu = false): array
    {
        $statusCakupan = $termasukParuhWaktu
            ? ['PNS', 'PPPK', 'PPPK_PW', 'LAINNYA']
            : ['PNS', 'PPPK'];

        $rows = Pegawai::select('status_aktif', 'status_kepegawaian', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('status_aktif', 'status_kepegawaian')
            ->get();

        $total = 0;
        $dihitung = 0;
        $diLuarCakupan = 0;
        $tidakAktif = [];

        foreach ($rows as $r) {
            $n = (int) $r->jumlah;
            $total += $n;

            if ($r->status_aktif === 'aktif') {
                in_array($r->status_kepegawaian, $statusCakupan, true)
                    ? $dihitung += $n
                    : $diLuarCakupan += $n;
            } else {
                $kunci = $r->status_aktif ?: 'tidak_diketahui';
                $tidakAktif[$kunci] = ($tidakAktif[$kunci] ?? 0) + $n;
            }
        }

        ksort($tidakAktif);

        return [
            'total_tersimpan' => $total,
            'dihitung' => $dihitung,
            'tidak_aktif' => [
                'jumlah' => array_sum($tidakAktif),
                'rincian' => $tidakAktif,
            ],
            'di_luar_cakupan' => $diLuarCakupan,
            'total_dashboard' => $termasukParuhWaktu,
        ];
    }

    /**
     * Rincian pegawai berstatus LAINNYA per nama aslinya. NABAN dan
     * Pegawai Titipan SELALU disebutkan (walau 0) supaya keterangannya
     * konsisten. Pegawai yang diimport sebelum kolom status_lainnya ada
     * belum punya nama; mereka dikelompokkan sebagai "Lainnya (belum
     * terklasifikasi)" (hanya tampil bila ada) sampai file kepegawaian
     * diimport ulang.
     */
    public function rincianStatusLainnya(): array
    {
        $rows = Pegawai::select('status_lainnya', DB::raw('COUNT(*) as jumlah'))
            ->where('status_kepegawaian', 'LAINNYA')
            ->where('status_aktif', 'aktif')
            ->groupBy('status_lainnya')
            ->get();

        $hitung = [];
        foreach ($rows as $row) {
            $label = $row->status_lainnya ?: 'Lainnya (belum terklasifikasi)';
            $hitung[$label] = ($hitung[$label] ?? 0) + (int) $row->jumlah;
        }

        $hasil = [];
        foreach (['NABAN', 'Pegawai Titipan'] as $label) {
            $hasil[] = ['label' => $label, 'total' => $hitung[$label] ?? 0];
            unset($hitung[$label]);
        }

        $sisa = [];
        foreach ($hitung as $label => $total) {
            $sisa[] = ['label' => $label, 'total' => $total];
        }
        usort($sisa, fn ($a, $b) => [$b['total'], $a['label']] <=> [$a['total'], $b['label']]);

        return array_merge($hasil, $sisa);
    }

    /**
     * Ringkasan jumlah pegawai aktif per status kepegawaian, untuk
     * rekonsiliasi hasil import. Sengaja memakai Pegawai::select() (bukan
     * Pegawai::query()) supaya tidak ikut ter-scope asnPenuh().
     */
    public function ringkasanStatusKepegawaian(): array
    {
        $rows = Pegawai::select('status_kepegawaian', DB::raw('COUNT(*) as jumlah'))
            ->where('status_aktif', 'aktif')
            ->groupBy('status_kepegawaian')
            ->pluck('jumlah', 'status_kepegawaian');

        $hasil = [];
        foreach (['PNS', 'PPPK', 'PPPK_PW', 'LAINNYA'] as $status) {
            $hasil[$status] = (int) ($rows[$status] ?? 0);
        }
        $hasil['TOTAL'] = array_sum($hasil);

        return $hasil;
    }

    /** Rekap PPPK paruh waktu per instansi (unit), pria/wanita/jumlah. */
    public function rekapParuhWaktu(?string $periode = null): array
    {
        $rows = Pegawai::paruhWaktu()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->groupBy('instansi.id', 'instansi.nama', 'pegawai.jenis_kelamin')
            ->get();

        $perInstansi = [];
        foreach ($rows as $row) {
            $id = $row->instansi_id;
            $perInstansi[$id] ??= ['instansi' => $row->instansi_nama, 'pria' => 0, 'wanita' => 0];
            $perInstansi[$id][$row->jenis_kelamin === 'L' ? 'pria' : 'wanita'] += (int) $row->jumlah;
        }

        foreach ($perInstansi as &$data) {
            $data['jumlah'] = $data['pria'] + $data['wanita'];
        }
        unset($data);

        usort($perInstansi, fn ($a, $b) => $b['jumlah'] <=> $a['jumlah']);

        return $perInstansi;
    }

    /**
     * Rekap PPPK paruh waktu per tingkat pendidikan, pria/wanita/jumlah.
     * Kategori sama dengan rekapPendidikan() (10 jenjang). Pegawai tanpa
     * pendidikan (atau jenjang di luar daftar) masuk baris "Belum diisi /
     * lainnya" supaya total tetap sama dengan jumlah pegawai paruh waktu.
     */
    public function rekapParuhWaktuPendidikan(?string $periode = null): array
    {
        $jenjang = ['SD', 'SLTP', 'SLTA', 'D I', 'D II', 'D III', 'D IV', 'S1', 'S2', 'S3'];

        $rows = Pegawai::paruhWaktu()
            ->leftJoin('pendidikan', 'pendidikan.id', '=', 'pegawai.pendidikan_id')
            ->select(
                'pendidikan.jenjang as kategori',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->groupBy('pendidikan.jenjang', 'pegawai.jenis_kelamin')
            ->get();

        return $this->susunRekapKategori($rows, array_combine($jenjang, $jenjang));
    }

    /**
     * Rekap PPPK paruh waktu per jenis jabatan (pelaksana / fungsional /
     * struktural), pria/wanita/jumlah. Sumbernya pegawai.jenis_kedudukan,
     * sama dengan yang dipakai rekap Jabatan.
     */
    public function rekapParuhWaktuJenisJabatan(?string $periode = null): array
    {
        $rows = Pegawai::paruhWaktu()
            ->select(
                'pegawai.jenis_kedudukan as kategori',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->groupBy('pegawai.jenis_kedudukan', 'pegawai.jenis_kelamin')
            ->get();

        return $this->susunRekapKategori($rows, [
            'PELAKSANA' => 'Jabatan Pelaksana',
            'FUNGSIONAL' => 'Jabatan Fungsional',
            'STRUKTURAL' => 'Jabatan Struktural',
        ]);
    }

    /**
     * Susun baris rekap (kategori x pria/wanita) dari hasil query.
     *
     * @param  iterable  $rows   objek dengan kategori, jenis_kelamin, jumlah
     * @param  array     $label  [nilai di database => nama yang ditampilkan]
     */
    private function susunRekapKategori(iterable $rows, array $label): array
    {
        $hasil = [];
        foreach ($label as $kunci => $nama) {
            $hasil[$kunci] = ['kategori' => $nama, 'pria' => 0, 'wanita' => 0];
        }
        $hasil['_kosong'] = ['kategori' => 'Belum diisi / lainnya', 'pria' => 0, 'wanita' => 0];

        foreach ($rows as $row) {
            $kunci = ($row->kategori !== null && isset($hasil[$row->kategori]))
                ? $row->kategori
                : '_kosong';

            $hasil[$kunci][$row->jenis_kelamin === 'L' ? 'pria' : 'wanita'] += (int) $row->jumlah;
        }

        // Baris "Belum diisi" hanya ditampilkan kalau memang ada isinya.
        if ($hasil['_kosong']['pria'] + $hasil['_kosong']['wanita'] === 0) {
            unset($hasil['_kosong']);
        }

        foreach ($hasil as &$data) {
            $data['jumlah'] = $data['pria'] + $data['wanita'];
        }
        unset($data);

        return array_values($hasil);
    }

    /**
     * Klasifikasi rincian Jabatan Fungsional Tertentu (JFT) untuk kartu
     * "JFT - Pendidikan / Kesehatan / Teknis" di Ringkasan Dashboard.
     * Versi ringkas (3 rumpun) dari rumpunJabatanFungsional() milik
     * StatistikService (6 rumpun, dipakai di halaman Statistik) —
     * aturannya sekarang dipusatkan di App\Support\KlasifikasiJft.
     */
    private function klasifikasiJft(string $jabatan): string
    {
        // Aturan pengelompokan ada di satu tempat (mengikuti dashboard resmi):
        // lihat App\Support\KlasifikasiJft.
        return KlasifikasiJft::kelompok($jabatan);
    }

    public function rekapEselonGolonganGender(?string $periode = null): array
    {
        $golonganList = ['III/a', 'III/b', 'III/c', 'III/d', 'IV/a', 'IV/b', 'IV/c', 'IV/d', 'IV/e'];
        $eselonList = ['II A', 'II B', 'III A', 'III B', 'IV A', 'IV B'];

        $rows = DB::table('eselon')
            ->leftJoin('pegawai', function ($join) {
                $join->on('pegawai.eselon_id', '=', 'eselon.id')
                    ->where('pegawai.status_aktif', '=', 'aktif');
            })
            ->leftJoin('golongan_ruang', 'golongan_ruang.id', '=', 'pegawai.golongan_ruang_id')
            ->select(
                'eselon.kode as eselon_kode',
                'golongan_ruang.kode as golongan_kode',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(pegawai.id) as jumlah')
            )
            ->whereIn('eselon.kode', $eselonList)
            ->groupBy('eselon.kode', 'golongan_ruang.kode', 'pegawai.jenis_kelamin')
            ->get();

        $perEselon = [];
        foreach ($eselonList as $kode) {
            $perEselon[$kode] = [
                'eselon' => $kode,
                'pria' => array_fill_keys($golonganList, 0),
                'wanita' => array_fill_keys($golonganList, 0),
            ];
        }

        foreach ($rows as $row) {
            if (!$row->golongan_kode || !in_array($row->golongan_kode, $golonganList) || !$row->jenis_kelamin) {
                continue;
            }
            $kelompok = $row->jenis_kelamin === 'L' ? 'pria' : 'wanita';
            $perEselon[$row->eselon_kode][$kelompok][$row->golongan_kode] = (int) $row->jumlah;
        }

        foreach ($perEselon as &$data) {
            $data['jml_pria'] = array_sum($data['pria']);
            $data['jml_wanita'] = array_sum($data['wanita']);
            $data['jml_total'] = $data['jml_pria'] + $data['jml_wanita'];
        }

        return array_values($perEselon);
    }

    private const ESELON_LIST = ['II A', 'II B', 'III A', 'III B', 'IV A', 'IV B'];

    private const STRUK_GOL_LIST = [
        'III/a',
        'III/b',
        'III/c',
        'III/d',
        'IV/a',
        'IV/b',
        'IV/c',
        'IV/d',
    ];

    private const JF_TERTENTU_GOL_LIST = [
        'II/a',
        'II/b',
        'II/c',
        'II/d',
        'III/a',
        'III/b',
        'III/c',
        'III/d',
        'IV/a',
        'IV/b',
        'IV/c',
        'IV/d',
        'IV/e',
    ];
    private const JF_TERTENTU_PPPK_LIST = ['V', 'VII', 'IX', 'X', 'XI'];

    private const JF_PELAKSANA_GOL_LIST = [
        'I/a',
        'I/b',
        'I/c',
        'I/d',
        'II/a',
        'II/b',
        'II/c',
        'II/d',
        'III/a',
        'III/b',
        'III/c',
        'III/d',
        'IV/a',
        'IV/b',
        'IV/c',
        'IV/d',
        'IV/e',
    ];
    private const JF_PELAKSANA_PPPK_LIST = ['I', 'III', 'V', 'VII', 'IX', 'X'];

    /**
     * Sheet "struk gol": Struktural per Instansi x Golongan (III/a-IV/d) x
     * Gender. Struktural ditentukan dari eselon.kode (sama seperti
     * rekapJabatan()), bukan jenis_kedudukan.
     */
    public function rekapStrukturGolonganInstansi(?string $periode = null): array
    {
        $golonganList = self::STRUK_GOL_LIST;

        $rows = Pegawai::asnPenuh()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->leftJoin('eselon', 'eselon.id', '=', 'pegawai.eselon_id')
            ->leftJoin('golongan_ruang', 'golongan_ruang.id', '=', 'pegawai.golongan_ruang_id')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'golongan_ruang.kode as golongan_kode',
                'eselon.kode as eselon_kode',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->groupBy(
                'instansi.id',
                'instansi.nama',
                'golongan_ruang.kode',
                'eselon.kode',
                'pegawai.jenis_kelamin'
            )
            ->get();

        $perInstansi = [];

        foreach ($rows as $row) {
            $eselonKode = preg_replace('/\s+/', ' ', strtoupper(trim((string) $row->eselon_kode)));
            if (!in_array($eselonKode, self::ESELON_LIST, true)) {
                continue; // hanya struktural
            }

            $id = $row->instansi_id;

            if (!isset($perInstansi[$id])) {
                $perInstansi[$id] = [
                    'instansi' => $row->instansi_nama,
                    'pria' => array_fill_keys($golonganList, 0),
                    'wanita' => array_fill_keys($golonganList, 0),
                ];
            }

            $kode = trim((string) $row->golongan_kode);
            if (!in_array($kode, $golonganList, true)) {
                continue;
            }

            $kelompok = $row->jenis_kelamin === 'L' ? 'pria' : 'wanita';
            $perInstansi[$id][$kelompok][$kode] += (int) $row->jumlah;
        }

        foreach ($perInstansi as &$data) {
            $data['jml_pria'] = array_sum($data['pria']);
            $data['jml_wanita'] = array_sum($data['wanita']);
            $data['jml_total'] = $data['jml_pria'] + $data['jml_wanita'];
        }

        return UrutanInstansi::abjad(array_values($perInstansi));
    }

    /**
     * Sheet "struk es": Struktural per Instansi x Eselon x Gender.
     */
    public function rekapStrukturEselonInstansi(?string $periode = null): array
    {
        $eselonList = self::ESELON_LIST;

        $rows = Pegawai::asnPenuh()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->join('eselon', 'eselon.id', '=', 'pegawai.eselon_id')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'eselon.kode as eselon_kode',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->groupBy('instansi.id', 'instansi.nama', 'eselon.kode', 'pegawai.jenis_kelamin')
            ->get();

        $perInstansi = [];

        foreach ($rows as $row) {
            $kodeEselon = preg_replace('/\s+/', ' ', strtoupper(trim((string) $row->eselon_kode)));
            if (!in_array($kodeEselon, $eselonList, true)) {
                continue;
            }

            $id = $row->instansi_id;

            if (!isset($perInstansi[$id])) {
                $perInstansi[$id] = [
                    'instansi' => $row->instansi_nama,
                    'pria' => array_fill_keys($eselonList, 0),
                    'wanita' => array_fill_keys($eselonList, 0),
                ];
            }

            $kelompok = $row->jenis_kelamin === 'L' ? 'pria' : 'wanita';
            $perInstansi[$id][$kelompok][$kodeEselon] += (int) $row->jumlah;
        }

        foreach ($perInstansi as &$data) {
            $data['jml_pria'] = array_sum($data['pria']);
            $data['jml_wanita'] = array_sum($data['wanita']);
            $data['jml_total'] = $data['jml_pria'] + $data['jml_wanita'];
        }

        return UrutanInstansi::abjad(array_values($perInstansi));
    }

    /**
     * Sheet "jf tertentu": JF Tertentu per Instansi x Golongan (II/a-IV/e,
     * diselang PPPK V,VII,IX,X,XI) x Gender + agregat PNS (I-IV) & PPPK.
     * JF Tertentu = jenis_kedudukan FUNGSIONAL dan TIDAK sedang menjabat
     * eselon (supaya konsisten dgn prioritas eselon di rekapJabatan()).
     */
    public function rekapJfTertentu(?string $periode = null): array
    {
        $golonganList = self::JF_TERTENTU_GOL_LIST;
        $pppkList = self::JF_TERTENTU_PPPK_LIST;
        $kolomList = array_merge($golonganList, $pppkList);

        $rows = Pegawai::asnPenuh()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->leftJoin('eselon', 'eselon.id', '=', 'pegawai.eselon_id')
            ->leftJoin('golongan_ruang', 'golongan_ruang.id', '=', 'pegawai.golongan_ruang_id')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'golongan_ruang.kode as golongan_kode',
                'golongan_ruang.kelompok as golongan_kelompok',
                'eselon.kode as eselon_kode',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->whereRaw("UPPER(TRIM(pegawai.jenis_kedudukan)) = 'FUNGSIONAL'")
            ->groupBy(
                'instansi.id',
                'instansi.nama',
                'golongan_ruang.kode',
                'golongan_ruang.kelompok',
                'eselon.kode',
                'pegawai.jenis_kelamin'
            )
            ->get();

        $perInstansi = [];

        foreach ($rows as $row) {
            $eselonKode = preg_replace('/\s+/', ' ', strtoupper(trim((string) $row->eselon_kode)));
            if (in_array($eselonKode, self::ESELON_LIST, true)) {
                continue; // sudah tercatat di struk gol / struk es
            }

            $id = $row->instansi_id;

            if (!isset($perInstansi[$id])) {
                $perInstansi[$id] = [
                    'instansi' => $row->instansi_nama,
                    'pria' => array_fill_keys($kolomList, 0),
                    'wanita' => array_fill_keys($kolomList, 0),
                ];
            }

            $jumlah = (int) $row->jumlah;
            $kelompok = $row->jenis_kelamin === 'L' ? 'pria' : 'wanita';
            $kode = trim((string) $row->golongan_kode);

            if ($row->golongan_kelompok === 'PPPK') {
                if (in_array($kode, $pppkList, true)) {
                    $perInstansi[$id][$kelompok][$kode] += $jumlah;
                }
                continue;
            }

            if (in_array($kode, $golonganList, true)) {
                $perInstansi[$id][$kelompok][$kode] += $jumlah;
            }
        }

        foreach ($perInstansi as &$data) {
            $data['jml_pria'] = array_sum($data['pria']);
            $data['jml_wanita'] = array_sum($data['wanita']);
            $data['jml_total'] = $data['jml_pria'] + $data['jml_wanita'];

            $pnsAgg = ['I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0];
            $pppkAgg = array_fill_keys($pppkList, 0);

            foreach ($data['pria'] as $kode => $jml) {
                $total = $jml + $data['wanita'][$kode];
                if (in_array($kode, $pppkList, true)) {
                    $pppkAgg[$kode] = $total;
                    continue;
                }
                $romawi = explode('/', $kode)[0];
                if (isset($pnsAgg[$romawi])) {
                    $pnsAgg[$romawi] += $total;
                }
            }

            $data['pns_agg'] = $pnsAgg;
            $data['pns_total'] = array_sum($pnsAgg);
            $data['pppk'] = $pppkAgg;
            $data['pppk_total'] = array_sum($pppkAgg);
        }

        return UrutanInstansi::abjad(array_values($perInstansi));
    }

    /**
     * Sheet "jf pelaksana": Fungsional Umum/Pelaksana per Instansi x
     * Golongan (I/a-IV/e, diselang PPPK I,III,V,VII,IX,X) x Gender +
     * agregat PNS (I-IV) & PPPK.
     */
    public function rekapJfPelaksana(?string $periode = null): array
    {
        $golonganList = self::JF_PELAKSANA_GOL_LIST;
        $pppkList = self::JF_PELAKSANA_PPPK_LIST;
        $kolomList = array_merge($golonganList, $pppkList);

        $rows = Pegawai::asnPenuh()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->leftJoin('eselon', 'eselon.id', '=', 'pegawai.eselon_id')
            ->leftJoin('golongan_ruang', 'golongan_ruang.id', '=', 'pegawai.golongan_ruang_id')
            ->select(
                'instansi.id as instansi_id',
                'instansi.nama as instansi_nama',
                'golongan_ruang.kode as golongan_kode',
                'golongan_ruang.kelompok as golongan_kelompok',
                'eselon.kode as eselon_kode',
                'pegawai.jenis_kedudukan',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->groupBy(
                'instansi.id',
                'instansi.nama',
                'golongan_ruang.kode',
                'golongan_ruang.kelompok',
                'eselon.kode',
                'pegawai.jenis_kedudukan',
                'pegawai.jenis_kelamin'
            )
            ->get();

        $perInstansi = [];

        foreach ($rows as $row) {
            $eselonKode = preg_replace('/\s+/', ' ', strtoupper(trim((string) $row->eselon_kode)));
            $jenisKedudukan = strtoupper(trim((string) $row->jenis_kedudukan));

            if (in_array($eselonKode, self::ESELON_LIST, true) || $jenisKedudukan === 'FUNGSIONAL') {
                continue;
            }

            $id = $row->instansi_id;

            if (!isset($perInstansi[$id])) {
                $perInstansi[$id] = [
                    'instansi' => $row->instansi_nama,
                    'pria' => array_fill_keys($kolomList, 0),
                    'wanita' => array_fill_keys($kolomList, 0),
                ];
            }

            $jumlah = (int) $row->jumlah;
            $kelompok = $row->jenis_kelamin === 'L' ? 'pria' : 'wanita';
            $kode = trim((string) $row->golongan_kode);

            if ($row->golongan_kelompok === 'PPPK') {
                if (in_array($kode, $pppkList, true)) {
                    $perInstansi[$id][$kelompok][$kode] += $jumlah;
                }
                continue;
            }

            if (in_array($kode, $golonganList, true)) {
                $perInstansi[$id][$kelompok][$kode] += $jumlah;
            }
        }

        foreach ($perInstansi as &$data) {
            $data['jml_pria'] = array_sum($data['pria']);
            $data['jml_wanita'] = array_sum($data['wanita']);
            $data['jml_total'] = $data['jml_pria'] + $data['jml_wanita'];

            $pnsAgg = ['I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0];
            $pppkAgg = array_fill_keys($pppkList, 0);

            foreach ($data['pria'] as $kode => $jml) {
                $total = $jml + $data['wanita'][$kode];
                if (in_array($kode, $pppkList, true)) {
                    $pppkAgg[$kode] = $total;
                    continue;
                }
                $romawi = explode('/', $kode)[0];
                if (isset($pnsAgg[$romawi])) {
                    $pnsAgg[$romawi] += $total;
                }
            }

            $data['pns_agg'] = $pnsAgg;
            $data['pns_total'] = array_sum($pnsAgg);
            $data['pppk'] = $pppkAgg;
            $data['pppk_total'] = array_sum($pppkAgg);
        }

        return UrutanInstansi::abjad(array_values($perInstansi));
    }

    private const NAKES_LIST = [
        'UPT PUSKESMAS DANUREJAN 1',
        'UPT PUSKESMAS DANUREJAN 2',
        'UPT PUSKESMAS GEDONGTENGEN',
        'UPT PUSKESMAS GONDOKUSUMAN 1',
        'UPT PUSKESMAS GONDOKUSUMAN 2',
        'UPT PUSKESMAS GONDOMANAN',
        'UPT PUSKESMAS JETIS',
        'UPT PUSKESMAS KOTAGEDE 1',
        'UPT PUSKESMAS KOTAGEDE 2',
        'UPT PUSKESMAS KRATON',
        'UPT PUSKESMAS MANTRIJERON',
        'UPT PUSKESMAS MERGANGSAN',
        'UPT PUSKESMAS NGAMPILAN',
        'UPT PUSKESMAS PAKUALAMAN',
        'UPT PUSKESMAS TEGALREJO',
        'UPT PUSKESMAS UMBULHARJO 1',
        'UPT PUSKESMAS UMBULHARJO 2',
        'UPT PUSKESMAS WIROBRAJAN',
        'RUMAH SAKIT PRATAMA',
    ];

    private const NAKES_RSUD_UNIT = 'RUMAH SAKIT UMUM DAERAH KOTA YOGYAKARTA';

    /**
     * Peta Kemantren -> daftar Kelurahan di bawahnya, Kota Yogyakarta
     * (14 Kemantren, 45 Kelurahan). Dipakai oleh rekapKecamatanKelurahan()
     * untuk membangun baris output & mencocokkan sub_unit pegawai.
     *
     * FIX: konstanta ini sebelumnya dipakai di rekapKecamatanKelurahan()
     * tapi tidak pernah didefinisikan -> Fatal Error "Undefined constant"
     * (500 Internal Server Error) setiap kali endpoint /rekap/kecamatan
     * diakses. Datanya diambil dari database/seeders/AlamatWilayahSeeder.php
     * supaya konsisten dengan data alamat yang sudah di-seed ke
     * tabel alamat_wilayah.
     */
    private const KEMANTREN_KELURAHAN_MAP = [
        'DANUREJAN' => ['BAUSASRAN', 'SURYATMAJAN', 'TEGALPANGGUNG'],
        'GEDONGTENGEN' => ['PRINGGOKUSUMAN', 'SOSROMENDURAN'],
        'GONDOKUSUMAN' => ['BACIRO', 'DEMANGAN', 'KLITREN', 'KOTABARU', 'TERBAN'],
        'GONDOMANAN' => ['NGUPASAN', 'PRAWIRODIRJAN'],
        'JETIS' => ['BUMIJO', 'COKRODININGRATAN', 'GOWONGAN'],
        'KOTAGEDE' => ['PRENGGAN', 'PURBAYAN', 'REJOWINANGUN'],
        'KRATON' => ['KADIPATEN', 'PANEMBAHAN', 'PATEHAN'],
        'MANTRIJERON' => ['GEDONGKIWO', 'MANTRIJERON', 'SURYODININGRATAN'],
        'MERGANGSAN' => ['BRONTOKUSUMAN', 'KEPARAKAN', 'WIROGUNAN'],
        'NGAMPILAN' => ['NGAMPILAN', 'NOTOPRAJAN'],
        'PAKUALAMAN' => ['GUNUNGKETUR', 'PURWOKINANTI'],
        'TEGALREJO' => ['BENER', 'KARANGWARU', 'KRICAK', 'TEGALREJO'],
        'UMBULHARJO' => [
            'GIWANGAN', 'MUJA-MUJU', 'PANDEYAN', 'SEMAKI', 'SOROSUTAN', 'TAHUNAN', 'WARUNGBOTO',
        ],
        'WIROBRAJAN' => ['PAKUNCEN', 'PATANGPULUHAN', 'WIROBRAJAN'],
    ];

    /**
     * Rekap Pejabat Fungsional Nakes (Puskesmas, RS Pratama, RSUD).
     *
     * Hanya jabatan fungsional bidang kesehatan yang dihitung. Jabatan
     * fungsional non-kesehatan di fasilitas yang sama (mis. Pranata Komputer,
     * Analis Kebijakan, Analis SDM Aparatur) tidak ikut. Klasifikasi memakai
     * klasifikasiJft() supaya sama dengan kartu "JFT - Kesehatan" di dashboard.
     *
     * Nama fasilitas Puskesmas / RS Pratama ada di sub_unit (unit =
     * 'DINAS KESEHATAN'), kadang berprefix "SUB BAGIAN TATA USAHA ...",
     * sehingga dicocokkan dengan str_contains. RSUD adalah instansi sendiri:
     * seluruh jabatan fungsional kesehatan di unit RSUD dihitung.
     */
    public function rekapNakes(?string $periode = null): array
    {
        $rowsDinkes = Pegawai::asnPenuh()
            ->select('sub_unit', 'jabatan', 'jenis_kelamin', DB::raw('COUNT(*) as jumlah'))
            ->where('status_aktif', 'aktif')
            ->whereRaw("UPPER(TRIM(jenis_kedudukan)) = 'FUNGSIONAL'")
            ->whereRaw("UPPER(TRIM(unit)) = 'DINAS KESEHATAN'")
            ->whereNotNull('sub_unit')
            ->groupBy('sub_unit', 'jabatan', 'jenis_kelamin')
            ->get();

        $agregat = [];
        foreach (self::NAKES_LIST as $nama) {
            $agregat[$nama] = ['pria' => 0, 'wanita' => 0];
        }

        foreach ($rowsDinkes as $row) {
            if (!$this->adalahJabatanNakes((string) $row->jabatan)) {
                continue;
            }

            $subUnit = strtoupper(trim((string) $row->sub_unit));
            $jumlah = (int) $row->jumlah;
            $kelompok = $row->jenis_kelamin === 'L' ? 'pria' : 'wanita';

            foreach (self::NAKES_LIST as $nama) {
                if (str_contains($subUnit, $nama)) {
                    $agregat[$nama][$kelompok] += $jumlah;
                    break; // satu sub_unit cuma cocok ke 1 fasilitas
                }
            }
        }

        $rowsRsud = Pegawai::asnPenuh()
            ->select('jabatan', 'jenis_kelamin', DB::raw('COUNT(*) as jumlah'))
            ->where('status_aktif', 'aktif')
            ->whereRaw("UPPER(TRIM(jenis_kedudukan)) = 'FUNGSIONAL'")
            ->whereRaw('UPPER(TRIM(unit)) = ?', [self::NAKES_RSUD_UNIT])
            ->groupBy('jabatan', 'jenis_kelamin')
            ->get();

        $rsudPria = 0;
        $rsudWanita = 0;
        foreach ($rowsRsud as $row) {
            if (!$this->adalahJabatanNakes((string) $row->jabatan)) {
                continue;
            }

            $row->jenis_kelamin === 'L'
                ? $rsudPria += (int) $row->jumlah
                : $rsudWanita += (int) $row->jumlah;
        }

        $alamatMap = \App\Models\AlamatFasilitas::where('jenis', 'nakes')->pluck('alamat', 'nama');

        $hasil = [];
        foreach (self::NAKES_LIST as $nama) {
            $pria = $agregat[$nama]['pria'];
            $wanita = $agregat[$nama]['wanita'];

            $hasil[] = [
                'fasilitas' => $nama,
                'alamat' => $alamatMap[$nama] ?? null,
                'pria' => $pria,
                'wanita' => $wanita,
                'jumlah' => $pria + $wanita,
            ];
        }

        $hasil[] = [
            'fasilitas' => self::NAKES_RSUD_UNIT,
            'alamat' => $alamatMap[self::NAKES_RSUD_UNIT] ?? null,
            'pria' => $rsudPria,
            'wanita' => $rsudWanita,
            'jumlah' => $rsudPria + $rsudWanita,
        ];

        return $hasil;
    }

    /**
     * Jabatan fungsional bidang kesehatan? (definisi sama dengan kartu
     * "JFT - Kesehatan" di Ringkasan Dashboard.)
     */
    private function adalahJabatanNakes(string $jabatan): bool
    {
        return $this->klasifikasiJft($jabatan) === 'kesehatan';
    }


    /**
     * Rekap Guru Fungsional per SD Negeri.
     *
     * REVISI: nama sekolah ternyata ada di kolom sub_unit, BUKAN unit
     * (unit-nya sama untuk semua guru: "DINAS PENDIDIKAN PEMUDA DAN
     * OLAHRAGA"). Tidak pakai daftar sekolah hardcode — GROUP BY sub_unit
     * otomatis menangkap sekolah manapun yang namanya diawali "SD NEGERI".
     */
    public function rekapSdFungsional(?string $periode = null): array
    {
        $rows = Pegawai::asnPenuh()
            ->select('sub_unit', 'jenis_kelamin', DB::raw('COUNT(*) as jumlah'))
            ->where('status_aktif', 'aktif')
            ->whereRaw("UPPER(TRIM(jenis_kedudukan)) = 'FUNGSIONAL'")
            ->whereRaw("UPPER(TRIM(unit)) = 'DINAS PENDIDIKAN PEMUDA DAN OLAHRAGA'")
            ->whereRaw("UPPER(TRIM(sub_unit)) LIKE 'SD NEGERI%'")
            ->groupBy('sub_unit', 'jenis_kelamin')
            ->get();

        return $this->aggregasiSekolah($rows);
    }

    /**
     * Rekap Guru Fungsional per SMP Negeri. Logic sama seperti
     * rekapSdFungsional(), hanya beda prefix nama sekolah.
     */
    public function rekapSmpFungsional(?string $periode = null): array
    {
        $rows = Pegawai::asnPenuh()
            ->select('sub_unit', 'jenis_kelamin', DB::raw('COUNT(*) as jumlah'))
            ->where('status_aktif', 'aktif')
            ->whereRaw("UPPER(TRIM(jenis_kedudukan)) = 'FUNGSIONAL'")
            ->whereRaw("UPPER(TRIM(unit)) = 'DINAS PENDIDIKAN PEMUDA DAN OLAHRAGA'")
            ->whereRaw("UPPER(TRIM(sub_unit)) LIKE 'SMP NEGERI%'")
            ->groupBy('sub_unit', 'jenis_kelamin')
            ->get();

        return $this->aggregasiSekolah($rows);
    }

    /**
     * Normalisasi nama untuk pencocokan: huruf besar, hanya huruf & angka.
     * "Muja-Muju" dan "MUJAMUJU" menghasilkan "MUJAMUJU".
     */
    private function normalisasiNama(string $nama): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($nama));
    }

    /** Angka Romawi di akhir nama sekolah => angka Arab. */
    private const ROMAWI_KE_ARAB = [
        'I' => '1', 'II' => '2', 'III' => '3', 'IV' => '4', 'V' => '5',
    ];

    /**
     * Rapikan nama sekolah: spasi ganda dibuang dan angka Romawi I-V di akhir
     * nama diganti angka Arab, mis. "SD NEGERI JETIS I" => "SD NEGERI JETIS 1",
     * "SD NEGERI TAMANSARI II" => "SD NEGERI TAMANSARI 2",
     * "SD NEGERI REJOWINANGUN III" => "SD NEGERI REJOWINANGUN 3".
     */
    private function samakanNamaSekolah(string $nama): string
    {
        $nama = trim(preg_replace('/\s+/', ' ', $nama));

        return preg_replace_callback(
            '/\b(I{1,3}|IV|V)$/',
            fn (array $m) => self::ROMAWI_KE_ARAB[$m[1]],
            $nama
        );
    }

    private function aggregasiSekolah($rows): array
    {
        $agregat = [];

        foreach ($rows as $row) {
            // Diambil dari sub_unit, lalu disamakan penulisannya ("... I" => "... 1")
            // agar satu sekolah tidak terpecah menjadi dua baris.
            $nama = $this->samakanNamaSekolah((string) $row->sub_unit);
            $key = strtoupper($nama);

            if (!isset($agregat[$key])) {
                $agregat[$key] = ['nama' => $nama, 'pria' => 0, 'wanita' => 0];
            }

            $kelompok = $row->jenis_kelamin === 'L' ? 'pria' : 'wanita';
            $agregat[$key][$kelompok] += (int) $row->jumlah;
        }

        ksort($agregat);

        $hasil = [];
        foreach ($agregat as $data) {
            $hasil[] = [
                'sekolah' => $data['nama'],
                'pria' => $data['pria'],
                'wanita' => $data['wanita'],
                'jumlah' => $data['pria'] + $data['wanita'],
            ];
        }

        return $hasil;
    }

    /**
     * Rekap PNS Kecamatan (Kemantren) & Kelurahan berdasarkan Jenis
     * Kedudukan (Fungsional/Struktural/Pelaksana) dan Jenis Kelamin,
     * dilengkapi alamat kantor dari alamat_wilayah.
     *
     * Struktur STRUKTURAL ditentukan dari eselon.kode (bukan
     * jenis_kedudukan), karena kolom itu masih NULL untuk sebagian pegawai
     * lama yang belum backfill — lihat docblock statistikPejabatStruktural()
     * di StatistikService untuk detail alasannya.
     *
     * Return berbentuk flat array: satu baris = satu Kelurahan, dengan info
     * Kemantren induk HANYA diisi di baris pertama tiap grup (baris
     * selanjutnya null) — supaya gampang di-export dengan merge cell meniru
     * tampilan Excel aslinya.
     */
    public function rekapKecamatanKelurahan(?string $periode = null): array
    {
        $eselonStruktural = ['II A', 'II B', 'III A', 'III B', 'IV A', 'IV B'];

        $rows = Pegawai::asnPenuh()
            ->leftJoin('eselon', 'eselon.id', '=', 'pegawai.eselon_id')
            ->select(
                'pegawai.unit',
                'pegawai.sub_unit',
                'pegawai.jenis_kedudukan',
                'eselon.kode as eselon_kode',
                'pegawai.jenis_kelamin',
                DB::raw('COUNT(*) as jumlah')
            )
            ->where('pegawai.status_aktif', 'aktif')
            ->where('pegawai.status_kepegawaian', 'PNS')
            ->whereNotNull('pegawai.unit')
            ->whereRaw("UPPER(TRIM(pegawai.unit)) LIKE 'KEMANTREN %'")
            ->groupBy('pegawai.unit', 'pegawai.sub_unit', 'pegawai.jenis_kedudukan', 'eselon.kode', 'pegawai.jenis_kelamin')
            ->get();

        $agregat = [];
        foreach (self::KEMANTREN_KELURAHAN_MAP as $kemantren => $kelurahanList) {
            $agregat[$kemantren] = [
                'kecamatan' => [
                    'fungsional' => ['laki_laki' => 0, 'perempuan' => 0],
                    'struktural' => ['laki_laki' => 0, 'perempuan' => 0],
                    'pelaksana' => ['laki_laki' => 0, 'perempuan' => 0],
                ],
                'kelurahan' => array_fill_keys($kelurahanList, [
                    'struktural' => ['laki_laki' => 0, 'perempuan' => 0],
                    'pelaksana' => ['laki_laki' => 0, 'perempuan' => 0],
                ]),
            ];
        }

        foreach ($rows as $row) {
            $unit = strtoupper(trim((string) $row->unit));
            $namaKemantren = trim(str_replace('KEMANTREN', '', $unit));

            if (!isset($agregat[$namaKemantren])) {
                continue;
            }

            $jumlah = (int) $row->jumlah;
            $gender = $row->jenis_kelamin === 'L' ? 'laki_laki' : 'perempuan';

            $eselonKode = strtoupper(trim((string) $row->eselon_kode));
            $jenisKedudukan = strtoupper(trim((string) $row->jenis_kedudukan));

            if (in_array($eselonKode, $eselonStruktural, true)) {
                $kedudukan = 'struktural';
            } elseif ($jenisKedudukan === 'PELAKSANA') {
                $kedudukan = 'pelaksana';
            } elseif ($jenisKedudukan === 'FUNGSIONAL') {
                $kedudukan = 'fungsional';
            } else {
                $kedudukan = null;
            }

            $subUnit = strtoupper(trim((string) $row->sub_unit));
            $isKelurahan = $subUnit !== '' && str_contains($subUnit, 'KELURAHAN');

            if ($isKelurahan) {
                // Cocokkan nama kelurahan tanpa memedulikan tanda hubung, spasi, atau tanda baca
                // (mis. "MUJA-MUJU" di daftar vs "MUJAMUJU" di data sumber).
                $subUnitNorm = $this->normalisasiNama($subUnit);

                $kelurahanDitemukan = null;
                foreach (self::KEMANTREN_KELURAHAN_MAP[$namaKemantren] as $kelurahan) {
                    if (str_contains($subUnitNorm, 'KELURAHAN' . $this->normalisasiNama($kelurahan))) {
                        $kelurahanDitemukan = $kelurahan;
                        break;
                    }
                }

                // Jangan biarkan pegawai hilang diam-diam: catat bila kelurahan tidak dikenali.
                if ($kelurahanDitemukan === null) {
                    Log::warning('RekapKecamatan: kelurahan tidak terpetakan, pegawai tidak ikut terhitung', [
                        'kemantren' => $namaKemantren,
                        'sub_unit' => $subUnit,
                        'jenis_kelamin' => $row->jenis_kelamin,
                        'jumlah' => $jumlah,
                    ]);
                }

                if ($kelurahanDitemukan !== null && in_array($kedudukan, ['struktural', 'pelaksana'], true)) {
                    $agregat[$namaKemantren]['kelurahan'][$kelurahanDitemukan][$kedudukan][$gender] += $jumlah;
                }
            } elseif ($kedudukan !== null) {
                $agregat[$namaKemantren]['kecamatan'][$kedudukan][$gender] += $jumlah;
            }
        }

        $alamatKemantren = \App\Models\AlamatWilayah::where('jenis', 'kemantren')->pluck('alamat', 'kemantren');
        $alamatKelurahan = \App\Models\AlamatWilayah::where('jenis', 'kelurahan')->get()
            ->keyBy(fn($r) => $r->kemantren . '|' . $r->kelurahan);

        $hasil = [];

        foreach (self::KEMANTREN_KELURAHAN_MAP as $kemantren => $kelurahanList) {
            $kec = $agregat[$kemantren]['kecamatan'];
            $first = true;

            foreach ($kelurahanList as $kelurahan) {
                $kel = $agregat[$kemantren]['kelurahan'][$kelurahan];
                $alamatKel = $alamatKelurahan->get($kemantren . '|' . $kelurahan);

                $hasil[] = [
                    'kemantren' => $first ? $kemantren : null,
                    'kemantren_alamat' => $first ? ($alamatKemantren[$kemantren] ?? null) : null,
                    'fungsional_l' => $first ? $kec['fungsional']['laki_laki'] : null,
                    'fungsional_p' => $first ? $kec['fungsional']['perempuan'] : null,
                    'struktural_l' => $first ? $kec['struktural']['laki_laki'] : null,
                    'struktural_p' => $first ? $kec['struktural']['perempuan'] : null,
                    'pelaksana_l' => $first ? $kec['pelaksana']['laki_laki'] : null,
                    'pelaksana_p' => $first ? $kec['pelaksana']['perempuan'] : null,
                    // Rollup total gender Kecamatan (Fungsional+Struktural+Pelaksana
                    // digabung jadi satu angka L, satu angka P) — sesuai kotak
                    // ringkasan di pojok kanan Excel asli (baris 74-78).
                    'kecamatan_total_l' => $first
                        ? $kec['fungsional']['laki_laki'] + $kec['struktural']['laki_laki'] + $kec['pelaksana']['laki_laki']
                        : null,
                    'kecamatan_total_p' => $first
                        ? $kec['fungsional']['perempuan'] + $kec['struktural']['perempuan'] + $kec['pelaksana']['perempuan']
                        : null,
                    'kelurahan' => $kelurahan,
                    'kelurahan_alamat' => $alamatKel->alamat ?? null,
                    'kel_struktural_l' => $kel['struktural']['laki_laki'],
                    'kel_struktural_p' => $kel['struktural']['perempuan'],
                    'kel_pelaksana_l' => $kel['pelaksana']['laki_laki'],
                    'kel_pelaksana_p' => $kel['pelaksana']['perempuan'],
                    // Rollup total gender Kelurahan (Struktural+Pelaksana digabung).
                    'kelurahan_total_l' => $kel['struktural']['laki_laki'] + $kel['pelaksana']['laki_laki'],
                    'kelurahan_total_p' => $kel['struktural']['perempuan'] + $kel['pelaksana']['perempuan'],
                    'jumlah_baris_kemantren' => $first ? count($kelurahanList) : null,
                ];

                $first = false;
            }
        }

        return $hasil;
    }

    /**
     * Daftar unit kerja pada tabel "Rekapitulasi Pensiun" — urutan & ejaan
     * sama persis dengan Excel referensi (51 unit, urut abjad) dan dengan
     * storage/daftar_instansi.txt. Dipakai sebagai kerangka baris supaya
     * unit yang belum punya calon pensiunan tetap tampil dengan angka 0
     * (bukan hilang dari tabel seperti rekap lain yang berbasis JOIN).
     */
    public const PENSIUN_UNIT_LIST = [
        'BADAN KEPEGAWAIAN DAN PENGEMBANGAN SUMBER DAYA MANUSIA',
        'BADAN KESATUAN BANGSA DAN POLITIK',
        'BADAN PENANGGULANGAN BENCANA DAERAH',
        'BADAN PENGELOLAAN KEUANGAN DAN ASET DAERAH',
        'BADAN PERENCANAAN PEMBANGUNAN DAERAH',
        'BAGIAN ADMINISTRASI DAN KEUANGAN',
        'BAGIAN ADMINISTRASI PEMBANGUNAN',
        'BAGIAN HUKUM',
        'BAGIAN KESEJAHTERAAN RAKYAT',
        'BAGIAN ORGANISASI',
        'BAGIAN PENGADAAN BARANG DAN JASA',
        'BAGIAN PEREKONOMIAN DAN KERJASAMA',
        'BAGIAN TATA PEMERINTAHAN',
        'BAGIAN UMUM DAN PROTOKOL',
        'DINAS KEBUDAYAAN (KUNDHA KABUDAYAN)',
        'DINAS KEPENDUDUKAN DAN PENCATATAN SIPIL',
        'DINAS KESEHATAN',
        'DINAS KOMUNIKASI INFORMATIKA DAN PERSANDIAN',
        'DINAS LINGKUNGAN HIDUP',
        'DINAS PARIWISATA',
        'DINAS PEKERJAAN UMUM PERUMAHAN DAN KAWASAN PERMUKIMAN',
        'DINAS PEMADAM KEBAKARAN DAN PENYELAMATAN',
        'DINAS PEMBERDAYAAN PEREMPUAN PERLINDUNGAN ANAK DAN PENGENDALIAN PENDUDUK DAN KELUARGA BERENCANA',
        'DINAS PENANAMAN MODAL DAN PELAYANAN TERPADU SATU PINTU',
        'DINAS PENDIDIKAN PEMUDA DAN OLAHRAGA',
        'DINAS PERDAGANGAN',
        'DINAS PERHUBUNGAN',
        'DINAS PERINDUSTRIAN KOPERASI USAHA KECIL DAN MENENGAH',
        'DINAS PERPUSTAKAAN DAN KEARSIPAN',
        'DINAS PERTANAHAN DAN TATA RUANG (KUNDHA NITI MANDALA SARTA TATA SASANA)',
        'DINAS PERTANIAN DAN PANGAN',
        'DINAS SOSIAL TENAGA KERJA DAN TRANSMIGRASI',
        'INSPEKTORAT',
        'KEMANTREN DANUREJAN',
        'KEMANTREN GEDONGTENGEN',
        'KEMANTREN GONDOKUSUMAN',
        'KEMANTREN GONDOMANAN',
        'KEMANTREN JETIS',
        'KEMANTREN KOTAGEDE',
        'KEMANTREN KRATON',
        'KEMANTREN MANTRIJERON',
        'KEMANTREN MERGANGSAN',
        'KEMANTREN NGAMPILAN',
        'KEMANTREN PAKUALAMAN',
        'KEMANTREN TEGALREJO',
        'KEMANTREN UMBULHARJO',
        'KEMANTREN WIROBRAJAN',
        'RUMAH SAKIT UMUM DAERAH KOTA YOGYAKARTA',
        'SATUAN POLISI PAMONG PRAJA',
        'SEKRETARIAT DAERAH',
        'SEKRETARIAT DPRD',
    ];

    /** Jumlah tahun proyeksi pada Rekapitulasi Pensiun (2026–2035 = 10). */
    public const PENSIUN_JUMLAH_TAHUN = 10;

    /**
     * Rekapitulasi Pensiun: jumlah PNS yang memasuki masa pensiun per
     * Unit x Tahun untuk 10 tahun ke depan, dihitung mulai dari tahun
     * pada $periode (mis. periode 2026-09 -> kolom 2026 s/d 2035).
     *
     * Definisi "pensiun" SAMA dengan StatistikService::statistikPensiunanPNS()
     * dan statistikPensiunanDinas(), supaya angkanya konsisten dengan halaman
     * Statistik: hanya PNS (status_kepegawaian = 'PNS') dengan
     * pegawai.tanggal_pensiun terisi dan jatuh pada tahun bersangkutan —
     * bukan berdasarkan status_aktif.
     *
     * Agregasi dilakukan di database dengan SUM(CASE WHEN ...) per tahun
     * (bukan fungsi YEAR()) supaya portabel MySQL/SQLite dan index pada
     * tanggal_pensiun tetap terpakai lewat filter rentang di WHERE.
     *
     * Baris mengikuti PENSIUN_UNIT_LIST. Instansi di luar daftar tersebut
     * yang ternyata punya calon pensiunan ditambahkan di bawahnya (urut
     * abjad) agar total tidak lebih kecil dari data sebenarnya.
     *
     * @return array{
     *   tahun_awal:int, tahun_list:int[],
     *   rows:array<int, array{instansi:string, per_tahun:array<int,int>, sub_total:int}>,
     *   total:array{per_tahun:array<int,int>, sub_total:int}
     * }
     */
    public function rekapPensiun(?string $periode = null): array
    {
        $tahunAwal = (int) substr($periode ?: now()->format('Y-m'), 0, 4);
        $tahunList = range($tahunAwal, $tahunAwal + self::PENSIUN_JUMLAH_TAHUN - 1);

        $select = ['instansi.nama as instansi_nama'];
        $bindings = [];

        foreach ($tahunList as $i => $tahun) {
            $select[] = "SUM(CASE WHEN pegawai.tanggal_pensiun >= ? AND pegawai.tanggal_pensiun < ? THEN 1 ELSE 0 END) as t{$i}";
            $bindings[] = sprintf('%04d-01-01', $tahun);
            $bindings[] = sprintf('%04d-01-01', $tahun + 1);
        }

        $rows = Pegawai::asnPenuh()
            ->join('instansi', 'instansi.id', '=', 'pegawai.instansi_id')
            ->selectRaw(implode(', ', $select), $bindings)
            ->where('pegawai.status_kepegawaian', 'PNS')
            ->whereNotNull('pegawai.tanggal_pensiun')
            ->where('pegawai.tanggal_pensiun', '>=', sprintf('%04d-01-01', $tahunAwal))
            ->where('pegawai.tanggal_pensiun', '<', sprintf('%04d-01-01', $tahunAwal + self::PENSIUN_JUMLAH_TAHUN))
            ->groupBy('instansi.nama')
            ->get();

        $kosong = array_fill_keys($tahunList, 0);

        $perUnit = [];
        foreach (self::PENSIUN_UNIT_LIST as $unit) {
            $perUnit[$unit] = $kosong;
        }

        $tambahan = [];
        foreach ($rows as $row) {
            $nama = trim((string) $row->instansi_nama);

            $angka = [];
            foreach ($tahunList as $i => $tahun) {
                $angka[$tahun] = (int) $row->{"t{$i}"};
            }

            if (isset($perUnit[$nama])) {
                $perUnit[$nama] = $angka;
            } else {
                $tambahan[$nama] = $angka;
            }
        }

        ksort($tambahan);
        $perUnit += $tambahan;

        $hasil = [];
        $totalPerTahun = $kosong;

        foreach ($perUnit as $unit => $angka) {
            foreach ($angka as $tahun => $jumlah) {
                $totalPerTahun[$tahun] += $jumlah;
            }

            $hasil[] = [
                'instansi' => $unit,
                'per_tahun' => $angka,
                'sub_total' => array_sum($angka),
            ];
        }

        return [
            'tahun_awal' => $tahunAwal,
            'tahun_list' => $tahunList,
            'rows' => $hasil,
            'total' => [
                'per_tahun' => $totalPerTahun,
                'sub_total' => array_sum($totalPerTahun),
            ],
        ];
    }
}