<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\ImportBatchCleaner;
use Illuminate\Console\Command;

class BersihkanSisaImport extends Command
{
    protected $signature = 'import:bersihkan-sisa {--force : Lewati pertanyaan konfirmasi}';

    protected $description = 'Hapus permanen data pegawai dan file sisa yang tidak terhubung ke riwayat import mana pun';

    public function handle(ImportBatchCleaner $cleaner): int
    {
        $sisa = $cleaner->hitungSisa();

        if (array_sum($sisa) === 0) {
            $this->info('Tidak ada data sisa. Semua data terhubung ke riwayat import.');

            return self::SUCCESS;
        }

        $this->table(['Data sisa', 'Jumlah'], [
            ['Pegawai tanpa riwayat import', $sisa['pegawai']],
            ['Rekap beku periode tanpa import', $sisa['snapshot']],
            ['File cadangan yatim', $sisa['file_cadangan']],
            ['File upload yatim', $sisa['file_upload']],
        ]);

        $jumlahImport = ImportBatch::count();
        if ($jumlahImport > 0) {
            $this->warn("Masih ada {$jumlahImport} riwayat import; pegawai yang terhubung ke riwayat itu TIDAK dihapus.");
        }

        if (!$this->option('force') && !$this->confirm('Hapus semua data di atas secara permanen? Tidak bisa dibatalkan.')) {
            $this->line('Dibatalkan.');

            return self::FAILURE;
        }

        $hasil = $cleaner->hapusSisa();

        $this->info(sprintf(
            'Selesai: %d pegawai, %d rekap beku, %d file cadangan, %d file upload dihapus.',
            $hasil['pegawai'], $hasil['snapshot'], $hasil['file_cadangan'], $hasil['file_upload']
        ));

        return self::SUCCESS;
    }
}