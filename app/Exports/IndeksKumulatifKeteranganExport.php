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

/** Sheet "Keterangan": penjelasan cara menghitung + contoh angka. */
class IndeksKumulatifKeteranganExport implements FromArray, WithTitle, WithEvents
{
    use PrintsConsistently;

    /** Baris judul bagian (dicetak tebal). */
    private array $barisJudul = [];

    /** Baris tabel contoh (awal, akhir). */
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

        $rows = [
            ['KETERANGAN CARA MENGHITUNG INDEKS KUMULATIF'],
            [],
            ['Sumber data'],
            ["Jumlah ASN aktif (PNS + PPPK) dari arsip rekap tiap bulan, {$awal} s.d. {$akhir} {$this->tahun}. Arsip dibuat otomatis setiap import selesai, jadi angka bulan lama tidak berubah walau data pegawai sudah ditimpa import berikutnya. Bulan yang belum pernah diupload ditulis \"-\" dan dilewati dalam perhitungan."],
            [],
            ['Rumus'],
            ['1. Jumlah: jumlah ASN aktif pada bulan tersebut.'],
            ['2. Selisih per bulan: jumlah bulan ini - jumlah bulan sebelumnya.'],
            ["3. Selisih kumulatif: jumlah bulan ini - jumlah bulan pertama ({$awal})."],
            ["4. Indeks kumulatif (berantai): indeks bulan lalu x (jumlah bulan ini / jumlah bulan lalu). Bulan pertama ({$awal}) = 100. Hasilnya sama dengan jumlah bulan ini / jumlah {$awal} x 100."],
            [],
            ['Cara membaca indeks'],
            ['Indeks di atas 100 = jumlah naik dibanding bulan pertama; di bawah 100 = turun. Selisih angkanya dari 100 adalah persen perubahan (110 = naik 10%, 90 = turun 10%).'],
            [],
            ['Contoh angka (bukan data asli)'],
        ];

        $this->barisJudul = [1, 3, 6, 12, 15];

        $rows[] = ['Bulan', 'Jumlah', 'Selisih per bulan', 'Selisih kumulatif', 'Indeks'];
        $awalContoh = count($rows) + 1;
        $rows[] = ['Bulan 1', 50, '-', 0, 100.0];
        $rows[] = ['Bulan 2', 55, 5, 5, 110.0];
        $rows[] = ['Bulan 3', 45, -10, -5, 90.0];
        $rows[] = ['Bulan 4', 60, 15, 10, 120.0];
        $this->contoh = [$awalContoh - 1, count($rows)];

        $rows[] = [];
        $rows[] = ['Cara hitung Bulan 3: indeks = 110 x (45 / 55) = 90,0. Bulan 4: indeks = 90 x (60 / 45) = 120,0.'];

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

                    // Perkiraan tinggi baris: ~95 karakter per baris pada lebar A:E.
                    $baris = max(1, (int) ceil(mb_strlen((string) $nilai) / 95));
                    $sheet->getRowDimension($r)->setRowHeight(15 * $baris);
                }

                foreach ($this->barisJudul as $r) {
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                }
                $sheet->getStyle('A1')->getFont()->setSize(13);

                [$a, $b] = $this->contoh;
                $sheet->getStyle("A{$a}:E{$a}")->getFont()->setBold(true);
                $sheet->getStyle("A{$a}:E{$a}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCE6F2');
                $sheet->getStyle("A{$a}:E{$b}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("B" . ($a + 1) . ":E{$b}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("C" . ($a + 1) . ":D{$b}")->getNumberFormat()
                    ->setFormatCode('+#,##0;-#,##0;0');
                $sheet->getStyle("E" . ($a + 1) . ":E{$b}")->getNumberFormat()->setFormatCode('0.0');

                $this->applyPrintSetup($sheet, "A1:E{$lastRow}", 0, 'portrait');
            },
        ];
    }
}