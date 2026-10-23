<?php

namespace App\Exports;

use App\Services\PerbandinganPeriodeService;
use App\Support\PerubahanAtribut;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Export Excel "Perubahan Individual". Datanya diambil dari
 * PerbandinganPeriodeService dengan filter yang sama seperti halaman,
 * sehingga angka di Excel = angka di layar.
 *
 * Sheet: Ringkasan, Perubahan (sesuai mode halaman), Riwayat (semua kejadian
 * berurutan di rentang), Keterangan.
 */
class PerbandinganPeriodeExport implements Export, WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private string $awal,
        private string $akhir,
        private string $mode = 'kondisi',
        private array $filter = []
    ) {}

    public function sheets(): array
    {
        $service = app(PerbandinganPeriodeService::class);
        $ringkasan = $service->ringkasan($this->awal, $this->akhir, $this->filter);
        $deret = $service->deret($this->awal, $this->akhir, $this->filter);

        $perubahan = $this->mode === 'kejadian'
            ? $service->semuaKejadian($this->awal, $this->akhir, $this->filter)
            : $service->semuaKondisi($this->awal, $this->akhir, $this->filter);
        $riwayat = $service->semuaKejadian($this->awal, $this->akhir, $this->filter);

        return [
            new class($this->ringkasanBaris($ringkasan, $deret)) implements FromArray, WithTitle, ShouldAutoSize {
                public function __construct(private array $baris) {}

                public function array(): array
                {
                    return $this->baris;
                }

                public function title(): string
                {
                    return 'Ringkasan';
                }
            },
            $this->sheetDaftar($this->mode === 'kejadian' ? 'Perubahan (kejadian)' : 'Perubahan (awal-akhir)', $perubahan),
            $this->sheetDaftar('Riwayat', $riwayat),
            new class($this->keterangan()) implements FromArray, WithTitle, ShouldAutoSize {
                public function __construct(private array $baris) {}

                public function array(): array
                {
                    return $this->baris;
                }

                public function title(): string
                {
                    return 'Keterangan';
                }
            },
        ];
    }

    private function sheetDaftar(string $judul, array $baris)
    {
        $data = [];
        $no = 1;
        foreach ($baris as $r) {
            $data[] = [
                $no++,
                // Tanda kutip depan supaya Excel tidak mengubah NIP jadi notasi ilmiah.
                ! empty($r['nip']) ? "'" . $r['nip'] : '',
                $r['nama'],
                $r['unit'] ?? '',
                $r['kategori_label'],
                $r['atribut_label'],
                $r['jenis_label'],
                $r['nilai_sebelum'] ?? '',
                $r['nilai_sesudah'] ?? '',
                $r['periode_dari'],
                $r['periode_ke'],
                $r['tanggal_efektif'] ?? '',
                $r['sumber'] ?? '',
                $r['perlu_verifikasi'] ? 'Perlu verifikasi' : 'Terdeteksi',
                $r['alasan_verifikasi'] ?? '',
            ];
        }

        return new class($judul, $data) implements FromArray, WithTitle, WithHeadings, ShouldAutoSize {
            public function __construct(private string $judul, private array $data) {}

            public function headings(): array
            {
                return ['No', 'NIP', 'Nama', 'Unit kerja', 'Kategori', 'Atribut', 'Jenis', 'Nilai sebelum', 'Nilai sesudah',
                    'Periode awal', 'Periode akhir', 'Tanggal efektif', 'Sumber data', 'Status', 'Alasan verifikasi'];
            }

            public function array(): array
            {
                return $this->data;
            }

            public function title(): string
            {
                return mb_substr($this->judul, 0, 31);
            }
        };
    }

    private function ringkasanBaris(array $r, array $deret): array
    {
        $baris = [
            ['PERUBAHAN INDIVIDUAL PEGAWAI'],
            ["Periode {$this->awal} s.d. {$this->akhir}"],
            [],
        ];

        $blok = function (string $judul, ?array $d) use (&$baris) {
            $baris[] = [$judul];
            if ($d === null) {
                $baris[] = ['Perbandingan tidak tersedia (snapshot individual periode awal/akhir belum ada).'];
                $baris[] = [];

                return;
            }
            $baris[] = ['Pegawai unik yang mengalami perubahan', $d['pegawai_unik_berubah']];
            $baris[] = ['Jumlah kejadian perubahan (atribut)', $d['kejadian_perubahan']];
            $baris[] = ['Jumlah atribut yang berubah', $d['atribut_berubah']];
            $baris[] = ['Kejadian perlu verifikasi', $d['perlu_verifikasi']];
            $baris[] = ['Tidak ada lagi di data bulan akhir', $d['tidak_ditemukan']];
            $baris[] = ['Baru muncul di data', $d['muncul']];
            foreach ($d['perpindahan_jabatan'] as $p) {
                $baris[] = ["Perpindahan {$p['dari']} -> {$p['ke']} (terkonfirmasi)", $p['jumlah']];
            }
            $baris[] = [];
        };

        $blok('A. KONDISI AWAL vs AKHIR (perubahan yang kembali ke nilai awal tidak dihitung)', $r['kondisi']);
        $blok('B. SEMUA KEJADIAN DI ANTARA PERIODE (perubahan yang kembali ke nilai awal tetap dihitung)', $r['kejadian']);

        $baris[] = ['STATUS SNAPSHOT PER PERIODE'];
        $baris[] = ['Periode', 'Status', 'Jumlah pegawai', 'Perlu verifikasi', 'Versi'];
        foreach ($r['status_periode'] as $s) {
            $baris[] = [$s['periode'], $s['keterangan'], $s['jumlah_pegawai'] ?? '-', $s['jumlah_perlu_verifikasi'] ?? '-', $s['versi'] ?? '-'];
        }
        $baris[] = [];

        foreach (['berurutan' => 'C. PERBANDINGAN BERURUTAN', 'kumulatif' => "D. PERBANDINGAN KUMULATIF DARI {$this->awal}"] as $kunci => $judul) {
            $baris[] = [$judul];
            $baris[] = ['Periode', 'Dibandingkan dengan', 'Pegawai unik berubah', 'Kejadian', 'Atribut berubah', 'Perlu verifikasi', 'Keterangan'];
            foreach ($deret[$kunci] as $d) {
                $baris[] = $d['status'] === 'tersedia'
                    ? [$d['periode'], $d['dibandingkan_dengan'], $d['pegawai_unik_berubah'], $d['kejadian_perubahan'], $d['atribut_berubah'], $d['perlu_verifikasi'],
                        ($d['bulan_terlewati'] ?? 0) > 0 ? "{$d['bulan_terlewati']} bulan di antaranya tanpa snapshot" : '']
                    : [$d['periode'], '-', '-', '-', '-', '-', match ($d['status']) {
                        'dasar' => 'Periode dasar',
                        default => 'Tidak tersedia (bukan berarti tidak ada perubahan)',
                    }];
            }
            $baris[] = [];
        }

        return $baris;
    }

    private function keterangan(): array
    {
        return [
            ['KETERANGAN'],
            [],
            ['Sumber data: snapshot individual pegawai per periode (dibuat saat import selesai). Perubahan masa lalu yang belum pernah di-snapshot tidak dikarang.'],
            ['Pegawai dicocokkan lewat NIP (18 digit). NIP tidak standar/kosong dicocokkan lewat NIK dan ditandai "Perlu verifikasi". Pencocokan tidak pernah memakai nama saja.'],
            ['Kondisi awal vs akhir: membandingkan dua snapshot. Atribut yang berubah lalu kembali ke nilai awal tidak dihitung.'],
            ['Kejadian: semua perubahan pada tiap pasangan periode berurutan. Perubahan yang kembali ke nilai awal tetap tampil.'],
            ['Nilai sebelumnya/sesudahnya kosong = "terisi"/"menjadi kosong" dan perlu verifikasi, bukan perubahan terkonfirmasi.'],
            ['Tanggal efektif hanya diisi bila sumber data menyediakannya (TMT pangkat). Bukan tanggal impor atau tanggal snapshot.'],
            ['Tidak ada lagi di data bulan akhir belum tentu berarti pensiun/keluar; penyebabnya perlu diperiksa admin.'],
            ['Angka di sini adalah hitungan pegawai/atribut individual, bukan selisih jumlah agregat rekap.'],
            ['Kategori jabatan (JFT/JFU/Struktural) diturunkan dengan aturan yang sama seperti rekap: eselon II-IV = struktural; selain itu jenis kedudukan FUNGSIONAL = JFT; sisanya JFU.'],
        ];
    }
}