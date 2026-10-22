<?php

namespace App\Support;

use App\Models\Pegawai;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan "data pegawai lengkap" di SATU tempat.
 *
 * Dipakai oleh: filter Daftar Pegawai, daftar isian kosong per pegawai,
 * ringkasan hasil import, dan banner peringatan di halaman Admin.
 * Mau mengubah isian apa yang wajib diisi? Cukup ubah ATURAN di bawah.
 *
 * Tiap aturan:
 *  - label  : nama isian yang tampil ke admin
 *  - sumber : 'kolom' (kolom tabel pegawai), 'detail' (pegawai_detail), atau
 *             'identitas' (NIP dan NIK sama-sama kosong; lihat nikSumber())
 *  - kolom  : nama kolom (untuk sumber 'kolom'; untuk 'detail' dipakai kolom
 *             status_perkawinan)
 *  - teks   : true bila kolom berupa teks, sehingga string kosong ikut
 *             dianggap belum diisi
 *  - status : isian ini hanya wajib untuk status kepegawaian tersebut
 *             (mis. golongan tidak berlaku untuk PPPK Paruh Waktu).
 */
class KelengkapanPegawai
{
    private const SEMUA = ['PNS', 'PPPK', 'PPPK_PW', 'LAINNYA'];
    private const ASN = ['PNS', 'PPPK', 'PPPK_PW'];

    public const ATURAN = [
        // Pegawai tanpa NIP DAN tanpa NIK tidak punya identitas sama sekali.
        // NIP kosong saja (NIK terisi) tidak dihitung belum lengkap, karena
        // wajar untuk pegawai non-PNS; itu hanya diberi peringatan lewat
        // identitas().
        'nip_nik' => [
            'label' => 'NIP / NIK', 'sumber' => 'identitas', 'kolom' => 'nip',
            'teks' => true, 'status' => self::SEMUA,
        ],
        'jenis_kelamin' => [
            'label' => 'Jenis kelamin', 'sumber' => 'kolom', 'kolom' => 'jenis_kelamin',
            'teks' => false, 'status' => self::SEMUA,
        ],
        'tanggal_lahir' => [
            'label' => 'Tanggal lahir', 'sumber' => 'kolom', 'kolom' => 'tanggal_lahir',
            'teks' => false, 'status' => self::SEMUA,
        ],
        'instansi' => [
            'label' => 'Perangkat daerah', 'sumber' => 'kolom', 'kolom' => 'instansi_id',
            'teks' => false, 'status' => self::SEMUA,
        ],
        'agama' => [
            'label' => 'Agama', 'sumber' => 'kolom', 'kolom' => 'agama_id',
            'teks' => false, 'status' => self::ASN,
        ],
        'pendidikan' => [
            'label' => 'Pendidikan', 'sumber' => 'kolom', 'kolom' => 'pendidikan_id',
            'teks' => false, 'status' => self::ASN,
        ],
        'jabatan' => [
            'label' => 'Jabatan', 'sumber' => 'kolom', 'kolom' => 'jabatan',
            'teks' => true, 'status' => self::ASN,
        ],
        'jenis_kedudukan' => [
            'label' => 'Jenis kedudukan', 'sumber' => 'kolom', 'kolom' => 'jenis_kedudukan',
            'teks' => true, 'status' => self::ASN,
        ],
        'golongan' => [
            'label' => 'Pangkat / golongan', 'sumber' => 'kolom', 'kolom' => 'golongan_ruang_id',
            'teks' => false, 'status' => ['PNS', 'PPPK'],
        ],
        'status_perkawinan' => [
            'label' => 'Status perkawinan', 'sumber' => 'detail', 'kolom' => 'status_perkawinan',
            'teks' => true, 'status' => self::ASN,
        ],
    ];

    /** Cache hasil nikSumber() selama proses PHP berjalan. */
    private static bool $nikSudahDicek = false;
    private static ?string $nikSumber = null;

    /**
     * Di mana NIK disimpan: 'pegawai' (kolom nik di tabel pegawai), 'detail'
     * (kolom nik di tabel detail pegawai), atau null bila tidak ditemukan.
     * Dicek otomatis dari skema database.
     *
     * Bila null, pemeriksaan NIK dilewati: aturan 'nip_nik' tidak menandai
     * siapa pun, dan identitas() hanya memeriksa NIP.
     */
    public static function nikSumber(): ?string
    {
        if (! self::$nikSudahDicek) {
            self::$nikSudahDicek = true;

            $tabelPegawai = (new Pegawai())->getTable();
            $tabelDetail = (new Pegawai())->detail()->getRelated()->getTable();

            if (Schema::hasColumn($tabelPegawai, 'nik')) {
                self::$nikSumber = 'pegawai';
            } elseif (Schema::hasColumn($tabelDetail, 'nik')) {
                self::$nikSumber = 'detail';
            }
        }

        return self::$nikSumber;
    }

    /** Daftar isian yang kosong untuk satu pegawai (relasi detail harus sudah dimuat). */
    public static function kosong(Pegawai $pegawai): array
    {
        $hasil = [];

        foreach (self::ATURAN as $kunci => $aturan) {
            if (! in_array($pegawai->status_kepegawaian, $aturan['status'], true)) {
                continue;
            }

            if ($aturan['sumber'] === 'identitas') {
                $nik = self::nilaiNik($pegawai);

                if (self::nikSumber() !== null && self::kosongTeks($pegawai->nip) && self::kosongTeks($nik)) {
                    $hasil[] = ['kunci' => $kunci, 'label' => $aturan['label']];
                }

                continue;
            }

            $nilai = $aturan['sumber'] === 'detail'
                ? $pegawai->detail?->status_perkawinan
                : $pegawai->{$aturan['kolom']};

            if ($nilai === null || $nilai === '') {
                $hasil[] = ['kunci' => $kunci, 'label' => $aturan['label']];
            }
        }

        return $hasil;
    }

    /**
     * Batasi query pegawai ke yang BELUM lengkap. Bila $kunci diisi, hanya
     * yang isian tersebut kosong; bila tidak, yang salah satu isiannya kosong.
     */
    public static function terapkanBelumLengkap(Builder $query, ?string $kunci = null): Builder
    {
        if ($kunci !== null && isset(self::ATURAN[$kunci])) {
            return $query->where(self::kondisi($kunci));
        }

        return $query->where(function (Builder $q) {
            foreach (array_keys(self::ATURAN) as $k) {
                $q->orWhere(self::kondisi($k));
            }
        });
    }

    /** Batasi query pegawai ke yang SUDAH lengkap. */
    public static function terapkanLengkap(Builder $query): Builder
    {
        return $query->whereNot(fn (Builder $q) => self::terapkanBelumLengkap($q));
    }

    /**
     * Ringkasan untuk pegawai aktif (opsional: hanya dari satu batch import):
     * jumlah pegawai yang belum lengkap, dan jumlah per isian yang kosong.
     */
    public static function ringkasan(?int $batchId = null): array
    {
        $dasar = fn (): Builder => Pegawai::query()
            ->where('status_aktif', 'aktif')
            ->when($batchId, fn (Builder $q) => $q->where('raw_import_id', $batchId));

        $perKolom = [];

        foreach (self::ATURAN as $kunci => $aturan) {
            $jumlah = self::terapkanBelumLengkap($dasar(), $kunci)->count();

            if ($jumlah > 0) {
                $perKolom[] = [
                    'kunci' => $kunci,
                    'label' => $aturan['label'],
                    'jumlah' => $jumlah,
                ];
            }
        }

        usort($perKolom, fn ($a, $b) => $b['jumlah'] <=> $a['jumlah']);

        return [
            'total_pegawai' => $dasar()->count(),
            'total_belum_lengkap' => self::terapkanBelumLengkap($dasar())->count(),
            'per_kolom' => $perKolom,
            // Perangkat daerah mana saja yang punya pegawai belum lengkap.
            'per_instansi' => self::perInstansi($batchId),
            // Peringatan NIP / NIK (kosong atau formatnya tidak valid).
            'identitas' => self::identitas($batchId),
        ];
    }

    /**
     * Peringatan identitas untuk pegawai aktif (opsional: satu batch import).
     *
     * Kategori:
     *  - tanpa_keduanya    : NIP dan NIK sama-sama kosong
     *  - tanpa_nip         : NIP kosong, NIK terisi (wajar untuk non-PNS)
     *  - tanpa_nik         : NIK kosong, NIP terisi
     *  - format_tidak_valid: NIP bukan 18 digit angka, atau NIK bukan 16 digit angka
     *
     * Tiap kategori berisi 'jumlah' (semua) dan 'daftar' (maksimal $batas
     * nama pertama, supaya respons tetap ringan).
     *
     * @return array<string, mixed>
     */
    public static function identitas(?int $batchId = null, int $batas = 10): array
    {
        $kategori = [
            'tanpa_keduanya' => ['jumlah' => 0, 'daftar' => []],
            'tanpa_nip' => ['jumlah' => 0, 'daftar' => []],
            'tanpa_nik' => ['jumlah' => 0, 'daftar' => []],
            'format_tidak_valid' => ['jumlah' => 0, 'daftar' => []],
        ];

        $nikAda = self::nikSumber() !== null;

        $catat = function (string $kunci, Pegawai $p, string $keterangan) use (&$kategori, $batas): void {
            $kategori[$kunci]['jumlah']++;

            if (count($kategori[$kunci]['daftar']) < $batas) {
                $kategori[$kunci]['daftar'][] = ['nama' => $p->nama, 'keterangan' => $keterangan];
            }
        };

        Pegawai::query()
            ->where('status_aktif', 'aktif')
            ->when($batchId, fn (Builder $q) => $q->where('raw_import_id', $batchId))
            ->when(self::nikSumber() === 'detail', fn (Builder $q) => $q->with('detail'))
            ->chunkById(500, function ($daftar) use ($nikAda, $catat) {
                foreach ($daftar as $p) {
                    $nip = trim((string) $p->nip);
                    $nik = $nikAda ? trim((string) self::nilaiNik($p)) : null;

                    if ($nip === '' && $nik === '') {
                        $catat('tanpa_keduanya', $p, 'NIP dan NIK kosong');
                    } elseif ($nip === '') {
                        $catat('tanpa_nip', $p, $nikAda ? 'NIP kosong, NIK terisi' : 'NIP kosong');
                    } elseif ($nik === '') {
                        $catat('tanpa_nik', $p, 'NIK kosong, NIP terisi');
                    }

                    $masalah = [];

                    if ($nip !== '' && ! preg_match('/^\d{18}$/', $nip)) {
                        $masalah[] = 'NIP ' . self::ringkasPanjang($nip) . ' (seharusnya 18 digit angka)';
                    }

                    if ($nik !== null && $nik !== '' && ! preg_match('/^\d{16}$/', $nik)) {
                        $masalah[] = 'NIK ' . self::ringkasPanjang($nik) . ' (seharusnya 16 digit angka)';
                    }

                    if ($masalah) {
                        $catat('format_tidak_valid', $p, implode('; ', $masalah));
                    }
                }
            });

        return ['nik_diperiksa' => $nikAda] + $kategori;
    }

    /**
     * Daftar perangkat daerah yang punya pegawai aktif BELUM LENGKAP, supaya
     * admin cukup mengecek perangkat daerah yang kurang saja, tidak perlu
     * menyisir seluruh Daftar Pegawai.
     *
     * Hanya perangkat daerah yang punya kekurangan yang dimuat, urut dari
     * yang paling banyak kurang. Pegawai tanpa perangkat daerah dikelompokkan
     * sebagai "Tanpa perangkat daerah" (instansi_id = null).
     *
     * Aturan "belum lengkap" sama persis dengan ATURAN di atas (termasuk
     * pembatasan per status kepegawaian), jadi angkanya selalu sama dengan
     * hasil filter di Daftar Pegawai.
     *
     * @return array<int, array{
     *     instansi_id: ?int, instansi: string, total_pegawai: int,
     *     belum_lengkap: int, rincian: array<int, array{kunci:string,label:string,jumlah:int}>
     * }>
     */
    public static function perInstansi(?int $batchId = null): array
    {
        $dasar = fn (): Builder => Pegawai::query()
            ->where('pegawai.status_aktif', 'aktif')
            ->when($batchId, fn (Builder $q) => $q->where('pegawai.raw_import_id', $batchId));

        // Hitung jumlah pegawai dari query $q per instansi_id (null jadi 0).
        $hitung = fn (Builder $q): array => $q
            ->select('pegawai.instansi_id', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('pegawai.instansi_id')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->instansi_id => (int) $r->jumlah])
            ->all();

        $nama = DB::table('instansi')->pluck('nama', 'id');

        $total = $hitung($dasar());
        $belum = $hitung(self::terapkanBelumLengkap($dasar()));

        // Isian apa saja yang kosong di tiap perangkat daerah.
        $rincianPerInstansi = [];
        foreach (self::ATURAN as $kunci => $aturan) {
            foreach ($hitung(self::terapkanBelumLengkap($dasar(), $kunci)) as $id => $jumlah) {
                $rincianPerInstansi[$id][] = [
                    'kunci' => $kunci,
                    'label' => $aturan['label'],
                    'jumlah' => $jumlah,
                ];
            }
        }

        $hasil = [];
        foreach ($belum as $id => $jumlahBelum) {
            $rincian = $rincianPerInstansi[$id] ?? [];
            usort($rincian, fn ($a, $b) => $b['jumlah'] <=> $a['jumlah']);

            $hasil[] = [
                'instansi_id' => $id ?: null,
                'instansi' => $id ? ($nama[$id] ?? "Perangkat daerah #{$id}") : 'Tanpa perangkat daerah',
                'total_pegawai' => $total[$id] ?? 0,
                'belum_lengkap' => $jumlahBelum,
                'rincian' => $rincian,
            ];
        }

        usort($hasil, fn ($a, $b) => [$b['belum_lengkap'], $a['instansi']] <=> [$a['belum_lengkap'], $b['instansi']]);

        return $hasil;
    }

    /** Nilai NIK satu pegawai (relasi detail harus sudah dimuat bila NIK ada di detail). */
    private static function nilaiNik(Pegawai $pegawai): ?string
    {
        return match (self::nikSumber()) {
            'pegawai' => $pegawai->nik,
            'detail' => $pegawai->detail?->nik,
            default => null,
        };
    }

    private static function kosongTeks(mixed $nilai): bool
    {
        return $nilai === null || trim((string) $nilai) === '';
    }

    /** "17 digit" bila semua angka tapi panjangnya salah, selain itu "17 karakter". */
    private static function ringkasPanjang(string $nilai): string
    {
        $panjang = strlen($nilai);

        return ctype_digit($nilai) ? "{$panjang} digit" : "{$panjang} karakter, ada selain angka";
    }

    private static function kondisi(string $kunci): Closure
    {
        $aturan = self::ATURAN[$kunci];

        return function (Builder $q) use ($aturan) {
            $q->whereIn('pegawai.status_kepegawaian', $aturan['status']);

            if ($aturan['sumber'] === 'identitas') {
                $sumber = self::nikSumber();

                // Kolom NIK tidak ditemukan: aturan ini tidak menandai siapa pun.
                if ($sumber === null) {
                    $q->whereRaw('1 = 0');

                    return;
                }

                $q->where(function (Builder $c) {
                    $c->whereNull('pegawai.nip')->orWhere('pegawai.nip', '');
                });

                if ($sumber === 'pegawai') {
                    $q->where(function (Builder $c) {
                        $c->whereNull('pegawai.nik')->orWhere('pegawai.nik', '');
                    });

                    return;
                }

                $q->where(function (Builder $d) {
                    $d->whereDoesntHave('detail')
                        ->orWhereHas('detail', fn ($x) => $x
                            ->whereNull('nik')
                            ->orWhere('nik', ''));
                });

                return;
            }

            if ($aturan['sumber'] === 'detail') {
                $q->where(function (Builder $d) {
                    $d->whereDoesntHave('detail')
                        ->orWhereHas('detail', fn ($x) => $x
                            ->whereNull('status_perkawinan')
                            ->orWhere('status_perkawinan', ''));
                });

                return;
            }

            $kolom = 'pegawai.' . $aturan['kolom'];

            $q->where(function (Builder $c) use ($kolom, $aturan) {
                $c->whereNull($kolom);

                if ($aturan['teks']) {
                    $c->orWhere($kolom, '');
                }
            });
        };
    }
}