<?php

namespace App\Exports;

use App\Exports\Concerns\PrintsConsistently;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Sheet "Keterangan": cara membaca file ini dalam bahasa sederhana.
 * Urutannya: isi file, arti empat tabel, contoh angka, lalu hal yang
 * sering salah dipahami.
 */
class IndeksKumulatifKeteranganExport implements FromArray, WithTitle, WithEvents
{
    use PrintsConsistently;

    /** Baris judul bagian (dicetak tebal). */
    private array $barisJudul = [];

    /** Baris tabel contoh (header, terakhir). */
    private array $contoh = [0, 0];

    public function __construct(private string $tahun, private array $periode) {}

    public function title(): string
    {
        return 'Keterangan';
    }

    public function array(): array
    {
        $awal = IndeksKumulatifSheetExport::BULAN[substr($this->periode[0] ?? "{$this->tahun}-01", 5, 2)] ?? 'Januari';
        $akhir = IndeksKumulatifSheetExport::BULAN[substr(end($this->periode) ?: "{$this->tahun}-01", 5, 2)] ?? '';

        $rows = [];
        $judul = function (string $teks) use (&$rows) {
            $rows[] = [$teks];
            $this->barisJudul[] = count($rows);
        };

        $judul('CARA MEMBACA FILE INI');
        $rows[] = ["File ini membandingkan jumlah ASN aktif dari bulan ke bulan, {$awal} sampai {$akhir} {$this->tahun}."];
        $rows[] = [];

        $judul('Isi file');
        $rows[] = ['1. Ringkasan: kesimpulan singkat dan angka terpenting. Mulai membaca dari sini.'];
        $rows[] = ['2. Keterangan: halaman ini.'];
        $rows[] = ['3. Sheet per kelompok (Agama, Pendidikan, Golongan Ruang, JFT, JFU, Status Kepegawaian): tabel lengkap per bulan.'];
        $rows[] = [];

        $judul('Yang dimaksud ASN aktif');
        $rows[] = ['PNS dan PPPK yang berstatus aktif. CPNS tidak dihitung. Pegawai yang sudah pensiun, meninggal, atau mutasi keluar juga tidak dihitung.'];
        $rows[] = ['PPPK paruh waktu hanya dihitung di sheet Status Kepegawaian (PNS + PPPK + PPPK Paruh Waktu). Di sheet kelompok lain PPPK paruh waktu tidak ikut, sama dengan menu Rekapitulasi.'];
        $rows[] = [];

        $judul('Arti keempat tabel di tiap sheet kelompok');
        $rows[] = ['1. Jumlah: banyaknya ASN aktif pada tiap bulan.'];
        $rows[] = ["2. Selisih per bulan: jumlah bulan ini dikurangi jumlah bulan sebelumnya. Tanda + berarti bertambah, tanda - berarti berkurang."];
        $rows[] = ["3. Selisih kumulatif: jumlah bulan ini dikurangi jumlah {$awal} (bulan awal). Menunjukkan total perubahan sejak awal, bukan hanya dari bulan lalu."];
        $rows[] = ["4. Indeks kumulatif: jumlah bulan ini dibagi jumlah {$awal}, lalu dikali 100. {$awal} selalu 100. Di atas 100 berarti lebih banyak dari awal, di bawah 100 berarti lebih sedikit. Selisihnya dari 100 adalah persen perubahan (110 = naik 10%, 90 = turun 10%)."];
        $rows[] = [];

        $judul('Contoh angka (bukan data asli)');
        $rows[] = ['Bulan', 'Jumlah', 'Selisih per bulan', 'Selisih kumulatif', 'Indeks'];
        $awalContoh = count($rows);
        $rows[] = ['Bulan 1', 50, '-', 0, 100.0];
        $rows[] = ['Bulan 2', 55, 5, 5, 110.0];
        $rows[] = ['Bulan 3', 45, -10, -5, 90.0];
        $rows[] = ['Bulan 4', 60, 15, 10, 120.0];
        $this->contoh = [$awalContoh, count($rows)];
        $rows[] = [];
        $rows[] = ['Cara membaca Bulan 3: jumlah 45, turun 10 dari Bulan 2 (55), dan turun 5 dari Bulan 1 (50). Indeks 90 = 45 dibagi 50 dikali 100, artinya turun 10% dari awal.'];
        $rows[] = [];

        $judul('Hal yang sering salah dipahami');
        $rows[] = ['- Tanda "-" berarti angka tidak tersedia (bulan belum diupload atau tidak bisa dihitung). Tanda "-" BUKAN 0 dan BUKAN berarti tidak ada perubahan.'];
        $rows[] = ["- Bulan yang tidak diupload dilewati. Perbandingan \"bulan sebelumnya\" memakai bulan terdekat yang punya data."];
        $rows[] = ['- Total tiap kelompok bisa berbeda. Agama, Pendidikan, dan Golongan Ruang mencakup seluruh ASN aktif, JFT dan JFU hanya mencakup ASN jabatan fungsional, dan Status Kepegawaian lebih besar karena ikut menghitung PPPK Paruh Waktu.'];
        $rows[] = ['- Semua angka di sini adalah jumlah per kategori, bukan perpindahan orang. Naiknya satu kategori dan turunnya kategori lain tidak selalu berarti ada orang yang berpindah antar kategori. Untuk pegawai yang masuk, keluar, atau berubah datanya, lihat bagian D di sheet Ringkasan.'];

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                foreach (['A' => 22, 'B' => 14, 'C' => 20, 'D' => 20, 'E' => 12] as $col => $lebar) {
                    $sheet->getColumnDimension($col)->setWidth($lebar);
                }

                // Teks penjelasan digabung A:E supaya terbaca penuh & membungkus.
                for ($r = 1; $r <= $lastRow; $r++) {
                    $nilai = $sheet->getCell("A{$r}")->getValue();
                    $dalamTabel = $r >= $this->contoh[0] && $r <= $this->contoh[1];

                    if ($nilai === null || $dalamTabel) {
                        continue;
                    }

                    $sheet->mergeCells("A{$r}:E{$r}");
                    $sheet->getStyle("A{$r}")->getAlignment()
                        ->setWrapText(true)
                        ->setVertical(Alignment::VERTICAL_TOP);

                    // Perkiraan tinggi baris: ~90 karakter per baris pada lebar A:E.
                    $baris = max(1, (int) ceil(mb_strlen((string) $nilai) / 90));
                    $sheet->getRowDimension($r)->setRowHeight(15 * $baris);
                }

                foreach ($this->barisJudul as $r) {
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true)->setSize($r === 1 ? 14 : 12);
                }

                [$a, $b] = $this->contoh;
                $sheet->getStyle("A{$a}:E{$a}")->getFont()->setBold(true);
                $sheet->getStyle("A{$a}:E{$a}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCE6F2');
                $sheet->getStyle("A{$a}:E{$b}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle('B' . ($a + 1) . ":E{$b}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('C' . ($a + 1) . ":D{$b}")->getNumberFormat()
                    ->setFormatCode('[Color10]+#,##0;[Red]-#,##0;0');
                $sheet->getStyle('E' . ($a + 1) . ":E{$b}")->getNumberFormat()->setFormatCode('0.0');

                $this->applyPrintSetup($sheet, "A1:E{$lastRow}", 0, 'portrait');
            },
        ];
    }
}