<?php

namespace App\Support;

/**
 * Pengurutan baris nama instansi (perangkat daerah) menurut abjad A-Z,
 * tidak membedakan huruf besar/kecil. Satu tempat supaya tabel di layar,
 * file Excel, dan arsip bulanan memakai aturan yang sama.
 */
class UrutanInstansi
{
    /** Nama arsip (rekap_snapshot_data.nama) yang barisnya per instansi. */
    private const NAMA_ARSIP_PER_INSTANSI = [
        'agama',
        'pendidikan',
        'jabatan',
        'golongan',
        'struktur-golongan',
        'struktur-eselon',
        'jf-tertentu',
        'jf-pelaksana',
    ];

    /**
     * Urutkan baris menurut abjad nilai $kunci (default 'instansi').
     * usort stabil di PHP 8, jadi nama yang sama tetap pada urutan asal.
     *
     * @param  array<int, array<string, mixed>>  $baris
     */
    public static function abjad(array $baris, string $kunci = 'instansi'): array
    {
        usort(
            $baris,
            fn ($a, $b) => strnatcasecmp((string) ($a[$kunci] ?? ''), (string) ($b[$kunci] ?? ''))
        );

        return $baris;
    }

    /**
     * Arsip bulanan lama dibekukan sebelum pengurutan abjad ada. Urutkan
     * saat dibaca supaya arsip lama tampil sama dengan data baru.
     * Arsip lain dikembalikan apa adanya.
     */
    public static function untukArsip(string $nama, mixed $payload): mixed
    {
        if (
            ! in_array($nama, self::NAMA_ARSIP_PER_INSTANSI, true)
            || ! is_array($payload)
            || $payload === []
            || ! is_array($payload[array_key_first($payload)] ?? null)
            || ! array_key_exists('instansi', $payload[array_key_first($payload)])
        ) {
            return $payload;
        }

        return self::abjad(array_values($payload));
    }
}