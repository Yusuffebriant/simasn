<?php

namespace App\Support;

use App\Models\Pegawai;
use Closure;
use Illuminate\Database\Eloquent\Builder;

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
        ];
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