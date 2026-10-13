<?php

namespace App\Console\Commands;

use App\Services\RekapSnapshotService;
use App\Support\RekapCache;
use Illuminate\Console\Command;

class BekukanRekap extends Command
{
    protected $signature = 'rekap:bekukan {periode? : Format YYYY-MM, default periode aktif}';

    protected $description = 'Bekukan rekap kondisi data saat ini sebagai snapshot satu periode';

    public function handle(RekapSnapshotService $snapshot): int
    {
        $periode = $this->argument('periode') ?: RekapCache::periodeAktif();

        if (! $periode || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode)) {
            $this->error('Periode tidak valid. Pakai format YYYY-MM, contoh: 2026-10.');

            return self::FAILURE;
        }

        $jumlah = $snapshot->bekukan($periode);

        $this->info("Rekap periode {$periode} dibekukan ({$jumlah} endpoint).");

        return self::SUCCESS;
    }
}