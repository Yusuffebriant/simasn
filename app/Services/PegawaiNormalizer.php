<?php

namespace App\Services;

use App\Models\GolonganRuang;
use App\Models\Eselon;
use App\Models\Pendidikan;
use App\Models\Agama;
use App\Models\Instansi;
use Carbon\Carbon;

class PegawaiNormalizer
{
    public function isRowValid(array $row): array
    {
        $nip = trim((string)($row['NIP'] ?? ''));
        $nama = trim((string)($row['NAMA'] ?? ''));

        if ($nip === '' || $nama === '') {
            return [false, 'NIP atau Nama kosong'];
        }

        if (is_numeric($nama)) {
            return [false, 'Baris terdeteksi sebagai index palsu, bukan data pegawai'];
        }

        return [true, null];
    }

    public function normalisasiKodeGolongan(?string $raw): ?string
    {
        if (!$raw || trim($raw) === '-' || trim($raw) === '') {
            return null;
        }

        $parts = explode(',', $raw);
        $kode = trim(end($parts));

        return $kode === '' ? null : $kode;
    }

    public function resolveGolonganRuang(?string $raw, string $statusKepegawaian): ?int
    {
        $kode = $this->normalisasiKodeGolongan($raw);
        if (!$kode) {
            return null;
        }

        $kelompok = $statusKepegawaian === 'PPPK' ? 'PPPK' : 'PNS';

        $golongan = GolonganRuang::firstOrCreate(
            ['kode' => $kode, 'kelompok' => $kelompok],
            ['urutan' => 999]
        );

        return $golongan->id;
    }

    public function resolveEselon(?string $raw): int
    {
        $kode = trim((string)$raw);
        if ($kode === '' || $kode === '-') {
            $kode = 'Non Eselon';
        }

        return Eselon::firstOrCreate(['kode' => $kode], ['urutan' => 999])->id;
    }

    public function resolvePendidikan(?string $raw): ?int
    {
        if (!$raw || trim($raw) === '') {
            return null;
        }

        $map = [
            'SD' => 'SD',
            'SMP' => 'SLTP',
            'SLTP' => 'SLTP',
            'SMA' => 'SLTA',
            'SMK' => 'SLTA',
            'SLTA' => 'SLTA',
            'D-1' => 'D I',
            'D1' => 'D I',
            'D-2' => 'D II',
            'D2' => 'D II',
            'D-3' => 'D III',
            'D3' => 'D III',
            'D-4' => 'D IV',
            'D4' => 'D IV',
            'S-1/SARJANA' => 'S1',
            'S-1' => 'S1',
            'S1' => 'S1',
            'S-2' => 'S2',
            'S2' => 'S2',
            'S-3' => 'S3',
            'S3' => 'S3',
        ];

        $key = strtoupper(trim($raw));
        $jenjang = $map[$key] ?? null;

        if (!$jenjang) {
            return null;
        }

        return Pendidikan::firstOrCreate(['jenjang' => $jenjang], ['urutan' => 999])->id;
    }

    public function resolveJenisKelamin(?string $raw): ?string
    {
        $val = strtoupper(trim((string)$raw));

        return match (true) {
            in_array($val, ['L', 'LAKI-LAKI', 'PRIA']) => 'L',
            in_array($val, ['P', 'PEREMPUAN', 'WANITA']) => 'P',
            default => null,
        };
    }

    public function resolveAgama(?string $raw): ?int
    {
        if (!$raw || trim($raw) === '') {
            return null;
        }

        return Agama::firstOrCreate(['nama' => trim($raw)], ['urutan' => 999])->id;
    }

    public function resolveInstansi(?string $unit): ?int
    {
        if (!$unit || trim($unit) === '') {
            return null;
        }

        return Instansi::firstOrCreate(['nama' => trim($unit)])->id;
    }

    public function resolveStatusAktif(?string $raw): string
    {
        $val = strtoupper(trim((string)$raw));

        return match ($val) {
            'AKTIF' => 'aktif',
            'PENSIUN' => 'pensiun',
            'MUTASI KELUAR', 'MUTASI' => 'mutasi_keluar',
            'MENINGGAL' => 'meninggal',
            default => 'aktif',
        };
    }

    private function cleanZero($val): ?string
    {
        $val = trim((string)$val);
        return ($val === '' || $val === '0' || $val === '-') ? null : $val;
    }

    private function toDate($val): ?string
    {
        if (!$val) return null;
        if ($val instanceof \DateTimeInterface) {
            return Carbon::instance($val)->format('Y-m-d');
        }
        try {
            return Carbon::parse($val)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function normalisasiBaris(array $row): array
    {
        $statusKepegawaian = strtoupper(trim((string)($row['STATUS_KEPEGAWAIAN'] ?? 'PNS')));
        $statusKepegawaian = in_array($statusKepegawaian, ['PNS', 'PPPK']) ? $statusKepegawaian : 'PNS';

        $pegawai = [
            'nip' => trim((string)$row['NIP']),
            'nama' => trim((string)$row['NAMA']),
            'instansi_id' => $this->resolveInstansi($row['UNIT'] ?? null),
            'unit' => $row['UNIT'] ?? null,
            'sub_unit' => $row['SUB_UNIT'] ?? null,
            'jenis_kelamin' => $this->resolveJenisKelamin($row['JENIS_KELAMIN'] ?? null),
            'status_kepegawaian' => $statusKepegawaian,
            'golongan_ruang_id' => $this->resolveGolonganRuang($row['PANGKAT_GOLRU'] ?? null, $statusKepegawaian),
            'eselon_id' => $this->resolveEselon($row['ESELON'] ?? null),
            'agama_id' => $this->resolveAgama($row['AGAMA'] ?? null),
            'pendidikan_id' => $this->resolvePendidikan($row['TINGKAT_PENDIDIKAN'] ?? null),
            'jabatan' => $row['JABATAN'] ?? null,
            'tanggal_lahir' => $this->toDate($row['TGL_LAHIR'] ?? null),
            'tmt_pangkat' => $this->toDate($row['PANGKAT_TMT'] ?? null),
            'tanggal_pensiun' => $this->toDate($row['TGL_PENSIUN'] ?? null),
            'status_aktif' => $this->resolveStatusAktif($row['KEDUDUKAN_KEPEGAWAIAN'] ?? null),
        ];

        $alamatParts = array_filter([
            $row['ALAMAT'] ?? null,
            isset($row['RT'], $row['RW']) ? 'RT ' . $this->cleanZero($row['RT']) . '/RW ' . $this->cleanZero($row['RW']) : null,
            $row['KELURAHAN'] ?? null,
            $row['KECAMATAN'] ?? null,
            $row['KABUPATEN'] ?? null,
            $row['PROPINSI'] ?? null,
            $this->cleanZero($row['KODEPOS'] ?? null),
        ]);

        $detail = [
            'nik' => $this->cleanZero($row['NIK'] ?? null),
            'alamat' => implode(', ', $alamatParts),
            'hp' => $this->cleanZero($row['HP'] ?? null),
            'email' => $this->cleanZero($row['EMAIL'] ?? null),
        ];

        return compact('pegawai', 'detail');
    }
}
