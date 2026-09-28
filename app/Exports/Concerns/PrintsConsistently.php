<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Menyamakan pengaturan print SEMUA sheet export SIMASN, supaya rapi &
 * konsisten dilihat/di-print user, apa pun ukuran kertas dan orientasi
 * (portrait/landscape) yang dipilih user di dialog print Excel-nya.
 *
 * Sebelumnya tidak ada satu pun Export class yang mengatur PageSetup,
 * jadi Excel memakai setting print bawaan (kertas & margin default,
 * TANPA fit-to-page) — itu sebabnya tabel lebar sering terpotong jadi
 * beberapa halaman secara acak tergantung ukuran kertas/orientasi yang
 * kebetulan aktif di komputer user.
 *
 * Kunci perbaikannya: fitToWidth(1) + fitToHeight(0). Ini membuat Excel
 * SELALU menyusutkan/membesarkan seluruh lebar tabel supaya pas dalam
 * SATU halaman, apa pun ukuran kertas & orientasinya — jadi kolom tidak
 * akan pernah lagi terpotong ke halaman lain secara acak. Tinggi
 * dibiarkan mengalir ke beberapa halaman (fitToHeight 0 = tidak
 * dibatasi) karena tabel rekapnya bisa panjang ke bawah.
 *
 * Print area dibatasi persis ke rentang isi tabel (supaya tidak ada
 * kolom/baris kosong sisa yang ikut ke-print), baris judul & header
 * kolom diulang otomatis di tiap halaman lewat rowsToRepeatAtTop (jadi
 * halaman ke-2/3/dst tetap ada label kolomnya), dan seluruh konten
 * dipusatkan secara horizontal supaya rapi di tengah kertas.
 */
trait PrintsConsistently
{
    /**
     * @param  Worksheet  $sheet
     * @param  string  $range  Rentang isi tabel, mis. "A1:D27".
     * @param  int  $headerRowsToRepeat  Baris terakhir dari judul/header kolom yang harus diulang di tiap halaman (dihitung dari baris 1).
     * @param  string|null  $orientation  'portrait' | 'landscape'. Kalau null, ditebak otomatis dari lebar tabel.
     * @param  int  $firstRowToRepeat  Baris pertama yang diulang (default 1). Kalau diisi selain 1, hanya baris $firstRowToRepeat s.d. $headerRowsToRepeat yang diulang (mis. satu baris header kolom tabel di tengah sheet).
     */
    protected function applyPrintSetup(
        Worksheet $sheet,
        string $range,
        int $headerRowsToRepeat = 1,
        ?string $orientation = null,
        int $firstRowToRepeat = 1
    ): void {
        [$start, $end] = explode(':', $range);
        $endCol = rtrim($end, '0123456789');

        $orientation ??= $this->guessPrintOrientation($endCol);

        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation(
            $orientation === 'landscape'
                ? PageSetup::ORIENTATION_LANDSCAPE
                : PageSetup::ORIENTATION_PORTRAIT
        );
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_A4);
        $pageSetup->setFitToPage(true);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $pageSetup->setHorizontalCentered(true);
        $pageSetup->setVerticalCentered(false);
        $pageSetup->setPrintArea($range);

        if ($headerRowsToRepeat >= 1) {
            $pageSetup->setRowsToRepeatAtTopByStartAndEnd($firstRowToRepeat, $headerRowsToRepeat);
        }

        $sheet->getPageMargins()
            ->setTop(0.5)
            ->setBottom(0.5)
            ->setLeft(0.35)
            ->setRight(0.35)
            ->setHeader(0.2)
            ->setFooter(0.2);

        $sheet->setPrintGridlines(false);
    }

    private function guessPrintOrientation(string $lastColumn): string
    {
        return Coordinate::columnIndexFromString($lastColumn) > 6
            ? 'landscape'
            : 'portrait';
    }
}