<?php

namespace App\Support;

use App\Models\Pegawai;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aturan "data pegawai lengkap" di SATU tempat.
 *
 * Dipakai oleh: filter Daftar Pegawai, daftar isian kosong per pegawai,
 * ringkasan hasil import, dan banner peringatan di halaman Admin.
 * Mau mengubah isian apa yang wajib diisi? Cukup ubah ATURAN di bawah.
 *
 * Tiap aturan:
 *  - label  : nama isian yang tampil ke admin
 *  - sumber : 'kolom' (kolom tabel pegawai) atau 'detail' (pegawai_detail)
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

    /** Daftar isian yang kosong untuk satu pegawai (relasi detail harus sudah dimuat). */
    public static function kosong(Pegawai $pegawai): array
    {
        $hasil = [];

        foreach (self::ATURAN as $kunci => $aturan) {
            if (! in_array($pegawai->status_kepegawaian, $aturan['status'], true)) {
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
        ];
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

    private static function kondisi(string $kunci): Closure
    {
        $aturan = self::ATURAN[$kunci];

        return function (Builder $q) use ($aturan) {
            $q->whereIn('pegawai.status_kepegawaian', $aturan['status']);

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