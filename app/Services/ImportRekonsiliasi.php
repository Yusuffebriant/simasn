<?php

namespace App\Services;

use App\Models\Agama;
use App\Models\GolonganRuang;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

/**
 * Rekonsiliasi hasil import: membandingkan apa yang DIBACA dari file
 * (tally yang dicatat PegawaiImport saat memproses baris) dengan apa yang
 * BENAR-BENAR tersimpan di database untuk batch ini.
 *
 * Selisih biasanya berarti ada baris ganda di file (NIP/NIK sama sehingga
 * saling menimpa) atau ada baris yang gagal tersimpan diam-diam.
 */
class ImportRekonsiliasi
{
    private const KATEGORI = [
        'status_kepegawaian' => 'Status kepegawaian',
        'status_aktif' => 'Status aktif',
        'status_perkawinan' => 'Status perkawinan',
        'agama' => 'Agama',
        'golongan' => 'Golongan/pangkat',
    ];

    public function bandingkan(ImportBatch $batch, array $tally): array
    {
        $db = $this->bacaDatabase($batch->id);

        $totalFile = (int) ($tally['total'] ?? 0);
        $totalDb = $db['total'];

        $selisih = [];

        foreach (self::KATEGORI as $kunci => $label) {
            $file = $tally[$kunci] ?? [];
            $dbKat = $db[$kunci] ?? [];

            foreach (array_unique(array_merge(array_keys($file), array_keys($dbKat))) as $nilai) {
                $nFile = (int) ($file[$nilai] ?? 0);
                $nDb = (int) ($dbKat[$nilai] ?? 0);

                if ($nFile !== $nDb) {
                    $selisih[] = [
                        'kategori' => $label,
                        'nilai' => $this->labelNilai($kunci, (string) $nilai),
                        'file' => $nFile,
                        'db' => $nDb,
                    ];
                }
            }
        }

        $status = ($totalFile === $totalDb && $selisih === []) ? 'cocok' : 'selisih';

        return [
            'status' => $status,
            'total' => ['file' => $totalFile, 'db' => $totalDb],
            'ganda_dalam_file' => max(0, $totalFile - $totalDb),
            'selisih' => $selisih,
        ];
    }

    private function bacaDatabase(int $batchId): array
    {
        $dasar = fn () => DB::table('pegawai')
            ->where('pegawai.raw_import_id', $batchId)
            ->whereNull('pegawai.deleted_at');

        return [
            'total' => $dasar()->count(),
            'status_kepegawaian' => $this->kelompokkan($dasar(), 'pegawai.status_kepegawaian'),
            'status_aktif' => $this->kelompokkan($dasar(), 'pegawai.status_aktif'),
            'agama' => $this->kelompokkan($dasar(), 'pegawai.agama_id'),
            'golongan' => $this->kelompokkan($dasar(), 'pegawai.golongan_ruang_id'),
            'status_perkawinan' => $this->kelompokkan(
                $dasar()->leftJoin('pegawai_detail', 'pegawai_detail.pegawai_id', '=', 'pegawai.id'),
                'pegawai_detail.status_perkawinan'
            ),
        ];
    }

    private function kelompokkan($query, string $kolom): array
    {
        $hasil = [];

        $rows = $query
            ->select(DB::raw("{$kolom} as kunci"), DB::raw('COUNT(*) as jumlah'))
            ->groupBy($kolom)
            ->get();

        foreach ($rows as $row) {
            $hasil[$row->kunci === null ? '_null' : (string) $row->kunci] = (int) $row->jumlah;
        }

        return $hasil;
    }

    private function labelNilai(string $kategori, string $nilai): string
    {
        if ($nilai === '_null') {
            return 'Kosong';
        }

        if ($kategori === 'agama') {
            return Agama::find((int) $nilai)?->nama ?? "ID {$nilai}";
        }

        if ($kategori === 'golongan') {
            $g = GolonganRuang::find((int) $nilai);
            return $g ? "{$g->kelompok} {$g->kode}" : "ID {$nilai}";
        }

        return $nilai;
    }
}