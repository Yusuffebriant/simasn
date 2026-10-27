<?php

namespace App\Services;

use App\Models\Eselon;
use App\Models\GolonganRuang;
use App\Models\ImportBatch;
use App\Models\Instansi;
use App\Models\Pendidikan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mencatat apa saja yang berubah pada pegawai antara import sebelumnya
 * dan import yang baru saja selesai (mis. Februari -> Maret).
 *
 * Cara pakai (lihat ProcessPegawaiImport):
 *   1. ambilFoto()  SEBELUM import mengubah/menghapus apa pun.
 *   2. catat()      SETELAH import + penghapusan pegawai lama selesai.
 *
 * Pegawai dicocokkan lewat pegawai.id. Itu aman karena PegawaiImport
 * meng-update baris yang sama (cocok NIP/NIK), jadi id-nya tidak berganti.
 * Pegawai yang id-nya hilang setelah import = sudah tidak ada di file baru.
 */
class PerubahanPegawaiService
{
    public const LABEL = [
        'pegawai_baru' => 'Pegawai baru',
        'pensiun' => 'Pensiun',
        'kontrak_selesai' => 'Kontrak selesai',
        'meninggal' => 'Meninggal',
        'mutasi_keluar' => 'Mutasi keluar',
        'keluar_lainnya' => 'Tidak ada lagi di data (perlu dicek)',
        'naik_pangkat' => 'Naik pangkat',
        'ubah_golongan' => 'Perubahan golongan lain',
        'pindah_unit' => 'Pindah unit kerja',
        'ganti_jabatan' => 'Ganti jabatan',
        'ganti_status' => 'Ganti status kepegawaian',
        'ganti_pendidikan' => 'Ganti pendidikan',
    ];

    private const KOLOM = [
        'id', 'nip', 'nama', 'instansi_id', 'unit', 'sub_unit',
        'status_kepegawaian', 'status_lainnya', 'golongan_ruang_id',
        'eselon_id', 'pendidikan_id', 'jabatan', 'tmt_pangkat',
        'tanggal_pensiun', 'tanggal_selesai_kontrak', 'status_aktif',
    ];

    private array $golongan = [];
    private array $eselon = [];
    private array $pendidikan = [];
    private array $instansi = [];

    /**
     * Salinan kondisi pegawai (kolom penting saja) sebelum import, dikunci id.
     * Hanya sekitar belasan kolom per pegawai, jadi ringan di memori.
     */
    public function ambilFoto(): array
    {
        $foto = [];

        DB::table('pegawai')
            ->whereNull('deleted_at')
            ->select(self::KOLOM)
            ->chunkById(2000, function ($rows) use (&$foto) {
                foreach ($rows as $r) {
                    $foto[$r->id] = (array) $r;
                }
            });

        return $foto;
    }

    /**
     * Bandingkan foto sebelum import dengan kondisi sekarang, simpan
     * hasilnya ke import_perubahan. Mengembalikan jumlah perubahan tercatat.
     */
    public function catat(ImportBatch $batch, array $foto): int
    {
        DB::table('import_perubahan')->where('import_batch_id', $batch->id)->delete();

        // Import pertama / database kosong: semua pegawai "baru" tidak
        // informatif, jadi tidak ada yang dibandingkan.
        if ($foto === []) {
            $this->setStatus($batch, 'tanpa_pembanding');

            return 0;
        }

        $this->muatReferensi();

        $akhirPeriode = $this->akhirPeriode($batch->periode);
        $antrean = [];
        $jumlah = 0;

        $simpan = function (array $baris) use (&$antrean, &$jumlah, $batch) {
            $baris['import_batch_id'] = $batch->id;
            $baris['periode'] = $batch->periode;
            $baris['created_at'] = $baris['updated_at'] = now();
            $antrean[] = $baris;
            $jumlah++;

            if (count($antrean) >= 500) {
                DB::table('import_perubahan')->insert($antrean);
                $antrean = [];
            }
        };

        // 1) Pegawai yang masih ada: cek tiap kolom yang berubah,
        //    dan yang id-nya hilang = tidak ada lagi di file baru.
        foreach (array_chunk(array_keys($foto), 1000) as $idChunk) {
            $sekarang = DB::table('pegawai')
                ->whereIn('id', $idChunk)
                ->whereNull('deleted_at')
                ->select(self::KOLOM)
                ->get()
                ->keyBy('id');

            foreach ($idChunk as $id) {
                $lama = $foto[$id];

                if (! isset($sekarang[$id])) {
                    // Yang sudah tidak aktif (meninggal / mutasi keluar / pensiun)
                    // di data sebelumnya sudah tercatat pada bulan kejadiannya.
                    // Hilang dari file baru bukan kejadian bulan ini, jadi dilewati.
                    if (! $this->sudahTidakAktif($lama)) {
                        $simpan($this->catatanKeluar($lama, $akhirPeriode));
                    }

                    continue;
                }

                foreach ($this->bandingkanPegawai($lama, (array) $sekarang[$id]) as $baris) {
                    $simpan($baris);
                }
            }
        }

        // 2) Pegawai baru: ada di batch ini tapi tidak ada di foto.
        DB::table('pegawai')
            ->where('raw_import_id', $batch->id)
            ->whereNull('deleted_at')
            ->select(self::KOLOM)
            ->chunkById(2000, function ($rows) use ($foto, $simpan) {
                foreach ($rows as $r) {
                    if (isset($foto[$r->id])) {
                        continue;
                    }

                    $simpan($this->baris('pegawai_baru', (array) $r, null, $this->ringkasJabatan((array) $r)));
                }
            });

        if ($antrean !== []) {
            DB::table('import_perubahan')->insert($antrean);
        }

        $this->setStatus($batch, 'selesai');

        return $jumlah;
    }

    public function setStatus(ImportBatch $batch, string $status): void
    {
        DB::table('import_batches')->where('id', $batch->id)->update(['perubahan_status' => $status]);
    }

    // ------------------------------------------------------------------

    private function bandingkanPegawai(array $lama, array $baru): array
    {
        $hasil = [];

        // Status aktif (pensiun / meninggal / mutasi keluar) walau masih ada di file.
        if ($lama['status_aktif'] !== $baru['status_aktif']) {
            $jenis = match ($baru['status_aktif']) {
                'pensiun' => 'pensiun',
                'meninggal' => 'meninggal',
                'mutasi_keluar' => 'mutasi_keluar',
                default => null,
            };

            if ($jenis) {
                $hasil[] = $this->baris($jenis, $baru, $lama['status_aktif'], $baru['status_aktif'], 'Status di file berubah');
            }
        }

        // Golongan / pangkat.
        if ($lama['golongan_ruang_id'] !== $baru['golongan_ruang_id']) {
            $g1 = $this->golongan[$lama['golongan_ruang_id']] ?? null;
            $g2 = $this->golongan[$baru['golongan_ruang_id']] ?? null;
            $naik = $g1 && $g2 && $g1['kelompok'] === $g2['kelompok'] && $g2['urutan'] > $g1['urutan'];

            $hasil[] = $this->baris(
                $naik ? 'naik_pangkat' : 'ubah_golongan',
                $baru,
                $g1['kode'] ?? '-',
                $g2['kode'] ?? '-',
                $baru['tmt_pangkat'] ? 'TMT ' . Carbon::parse($baru['tmt_pangkat'])->format('d-m-Y') : null
            );
        }

        // Unit kerja.
        if ($lama['instansi_id'] !== $baru['instansi_id']
            || $this->norm($lama['unit']) !== $this->norm($baru['unit'])
            || $this->norm($lama['sub_unit']) !== $this->norm($baru['sub_unit'])) {
            $hasil[] = $this->baris('pindah_unit', $baru, $this->ringkasUnit($lama), $this->ringkasUnit($baru));
        }

        // Jabatan / eselon.
        if ($this->norm($lama['jabatan']) !== $this->norm($baru['jabatan'])
            || $lama['eselon_id'] !== $baru['eselon_id']) {
            $hasil[] = $this->baris('ganti_jabatan', $baru, $this->ringkasJabatan($lama), $this->ringkasJabatan($baru));
        }

        // Status kepegawaian (mis. PPPK Paruh Waktu -> PPPK).
        if ($lama['status_kepegawaian'] !== $baru['status_kepegawaian']
            || $this->norm($lama['status_lainnya']) !== $this->norm($baru['status_lainnya'])) {
            $hasil[] = $this->baris(
                'ganti_status',
                $baru,
                trim($lama['status_kepegawaian'] . ' ' . ($lama['status_lainnya'] ?? '')),
                trim($baru['status_kepegawaian'] . ' ' . ($baru['status_lainnya'] ?? ''))
            );
        }

        // Pendidikan.
        if ($lama['pendidikan_id'] !== $baru['pendidikan_id']) {
            $hasil[] = $this->baris(
                'ganti_pendidikan',
                $baru,
                $this->pendidikan[$lama['pendidikan_id']] ?? '-',
                $this->pendidikan[$baru['pendidikan_id']] ?? '-'
            );
        }

        return $hasil;
    }

    /** Status kedudukan di data sebelum import sudah bukan 'aktif'. */
    private function sudahTidakAktif(array $lama): bool
    {
        $status = $this->norm($lama['status_aktif'] ?? null);

        return $status !== '' && $status !== 'aktif';
    }

    /** Pegawai yang ada di database lama tetapi tidak ada di file baru. */
    private function catatanKeluar(array $lama, ?Carbon $akhirPeriode): array
    {
        $statusAktif = $lama['status_aktif'];

        $jatuhTempoPensiun = $lama['status_kepegawaian'] === 'PNS'
            && $lama['tanggal_pensiun']
            && $akhirPeriode
            // TMT pensiun PNS selalu tanggal 1. SIMPEG sudah mengeluarkan pegawai
            // dari ekspor sebelum TMT-nya (mis. TMT 01-10 tidak ada di ekspor
            // September), jadi TMT sehari setelah akhir periode ikut dihitung.
            && Carbon::parse($lama['tanggal_pensiun'])->lte($akhirPeriode->copy()->addDay());

        $kontrakHabis = $lama['status_kepegawaian'] !== 'PNS'
            && $lama['tanggal_selesai_kontrak']
            && $akhirPeriode
            && Carbon::parse($lama['tanggal_selesai_kontrak'])->lte($akhirPeriode);

        if ($statusAktif === 'meninggal') {
            return $this->baris('meninggal', $lama, 'aktif', null, 'Sudah tidak ada di file baru');
        }

        if ($statusAktif === 'mutasi_keluar') {
            return $this->baris('mutasi_keluar', $lama, 'aktif', null, 'Sudah tidak ada di file baru');
        }

        if ($statusAktif === 'pensiun' || $jatuhTempoPensiun) {
            $ket = $lama['tanggal_pensiun']
                ? 'TMT pensiun ' . Carbon::parse($lama['tanggal_pensiun'])->format('d-m-Y')
                    . ($akhirPeriode && Carbon::parse($lama['tanggal_pensiun'])->gt($akhirPeriode) ? ' (SIMPEG sudah mengeluarkan dari ekspor sebelum TMT)' : '')
                : 'Status pensiun di data lama';

            return $this->baris('pensiun', $lama, 'aktif', null, $ket . '; tidak ada di file baru');
        }

        if ($kontrakHabis) {
            return $this->baris(
                'kontrak_selesai',
                $lama,
                'aktif',
                null,
                'Kontrak berakhir ' . Carbon::parse($lama['tanggal_selesai_kontrak'])->format('d-m-Y') . '; tidak ada di file baru'
            );
        }

        return $this->baris(
            'keluar_lainnya',
            $lama,
            'aktif',
            null,
            'Tidak ada di file baru, penyebab tidak diketahui (cek: mutasi, berhenti, meninggal, atau terlewat saat ekspor)'
        );
    }

    private function baris(string $jenis, array $pegawai, ?string $dari, ?string $ke, ?string $keterangan = null): array
    {
        return [
            'jenis' => $jenis,
            'pegawai_id' => $pegawai['id'],
            'nip' => $pegawai['nip'],
            'nama' => $pegawai['nama'],
            'unit' => $pegawai['unit'] ?? null,
            'status_kepegawaian' => $pegawai['status_kepegawaian'] ?? null,
            'dari' => $dari,
            'ke' => $ke,
            'keterangan' => $keterangan,
        ];
    }

    private function ringkasUnit(array $p): string
    {
        $unit = $p['unit'] ?: ($this->instansi[$p['instansi_id']] ?? '-');

        return $p['sub_unit'] ? "{$unit} / {$p['sub_unit']}" : $unit;
    }

    private function ringkasJabatan(array $p): string
    {
        $teks = $p['jabatan'] ?: '-';
        $eselon = $this->eselon[$p['eselon_id'] ?? 0] ?? null;

        // 'Non Eselon' tidak perlu ditulis supaya tidak terbaca "Eselon Non Eselon".
        return ($eselon && $eselon !== 'Non Eselon') ? "{$teks} (Eselon {$eselon})" : $teks;
    }

    private function norm(?string $v): string
    {
        return mb_strtolower(trim((string) $v));
    }

    private function akhirPeriode(?string $periode): ?Carbon
    {
        if (! $periode || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m', $periode)->endOfMonth();
    }

    private function muatReferensi(): void
    {
        foreach (GolonganRuang::all() as $g) {
            $this->golongan[$g->id] = ['kode' => $g->kode, 'kelompok' => $g->kelompok, 'urutan' => $g->urutan];
        }
        foreach (Eselon::all() as $e) {
            $this->eselon[$e->id] = $e->kode;
        }
        foreach (Pendidikan::all() as $p) {
            $this->pendidikan[$p->id] = $p->jenjang;
        }
        foreach (Instansi::all() as $i) {
            $this->instansi[$i->id] = $i->nama;
        }
    }
}