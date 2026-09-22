<?php

namespace App\Exports;

use App\Services\RekapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RekapKecamatanExport implements FromArray, WithEvents
{
    protected array $data;

    /**
     * 3 baris judul (Jumlah ASN.../di Kota Yogyakarta/bulan-tahun)
     * + 3 baris header tabel (kategori/"Jumlah PNS..."/Laki-Laki-Perempuan).
     */
    protected int $titleRows = 3;
    protected int $tableHeaderRows = 3;
    protected int $headerRows = 6;

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())
            ->rekapKecamatanKelurahan($periode);
    }

    /**
     * Data yang akan dimasukkan ke Excel
     *
     * Kolom "Alamat Kelurahan" dihapus sesuai permintaan — kolom
     * "Alamat Kemantren" tetap dipertahankan. Kolom pertama "No" berisi
     * nomor urut per Kemantren/Kecamatan (hanya terisi di baris pertama
     * tiap grup, sama seperti kolom Kemantren, supaya bisa di-merge).
     */
    public function array(): array
    {
        $rows = [];
        $no = 0;

        foreach ($this->data as $d) {
            if ($d['jumlah_baris_kemantren'] !== null) {
                $no++;
            }

            $rows[] = [
                // No urut
                $d['jumlah_baris_kemantren'] !== null ? $no : null,

                // Kemantren
                $d['kemantren'],
                $d['kemantren_alamat'],

                // Kecamatan
                $d['fungsional_l'],
                $d['fungsional_p'],
                $d['struktural_l'],
                $d['struktural_p'],
                $d['pelaksana_l'],
                $d['pelaksana_p'],
                $d['kecamatan_total_l'],
                $d['kecamatan_total_p'],

                // Kelurahan (tanpa alamat)
                $d['kelurahan'],
                $d['kel_struktural_l'],
                $d['kel_struktural_p'],
                $d['kel_pelaksana_l'],
                $d['kel_pelaksana_p'],
                $d['kelurahan_total_l'],
                $d['kelurahan_total_p'],
            ];
        }

        return $rows;
    }

    /**
     * Format periode "Y-m" (mis. "2025-12") menjadi "Desember 2025",
     * dipakai untuk baris judul ketiga.
     */
    protected function formatPeriode(string $periode): string
    {
        $bulan = [
            '01' => 'Januari',
            '02' => 'Februari',
            '03' => 'Maret',
            '04' => 'April',
            '05' => 'Mei',
            '06' => 'Juni',
            '07' => 'Juli',
            '08' => 'Agustus',
            '09' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember',
        ];

        [$tahun, $bln] = explode('-', $periode);

        return ($bulan[$bln] ?? $bln) . ' ' . $tahun;
    }

    /**
     * Event untuk melakukan formatting Excel
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $sheet = $event->sheet->getDelegate();

                /*
                |--------------------------------------------------------------------------
                | Tambahkan baris untuk title (3 baris) dan header tabel (3 baris)
                |--------------------------------------------------------------------------
                */
                $sheet->insertNewRowBefore(1, $this->headerRows);

                /*
                |--------------------------------------------------------------------------
                | TITLE (3 baris). Baris 1 (judul utama) dibuat 1 ukuran lebih
                | besar dibanding baris 2 & 3 ("di Kota Yogyakarta" dan
                | bulan-tahun) supaya ada penekanan/hirarki.
                |--------------------------------------------------------------------------
                */
                $sheet->setCellValue('A1', 'Jumlah ASN yang bekerja di Kecamatan / Kelurahan');
                $sheet->setCellValue('A2', 'di Kota Yogyakarta');
                $sheet->setCellValue('A3', $this->formatPeriode($this->periode));

                $sheet->mergeCells('A1:R1');
                $sheet->mergeCells('A2:R2');
                $sheet->mergeCells('A3:R3');

                $sheet->getStyle('A1:R3')
                    ->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center');

                // Baris 1: judul utama, 1 ukuran lebih besar.
                $sheet->getStyle('A1:R1')
                    ->getFont()
                    ->setName('Calibri')
                    ->setBold(true)
                    ->setSize(16);

                // Baris 2-3: "di Kota Yogyakarta" & bulan-tahun, 1 ukuran lebih kecil.
                $sheet->getStyle('A2:R3')
                    ->getFont()
                    ->setName('Calibri')
                    ->setBold(true)
                    ->setSize(14);

                /*
                |--------------------------------------------------------------------------
                | HEADER TABEL — 3 tingkat, mengikuti format pada gambar referensi:
                | baris 4  : nama kategori (Fungsional/Struktural/Pelaksana/Total)
                | baris 5  : "Jumlah PNS yang bekerja di Kecamatan/Kelurahan"
                | baris 6  : Laki-Laki / Perempuan
                |--------------------------------------------------------------------------
                */
                $headerRow1 = $this->titleRows + 1; // 4
                $headerRow2 = $this->titleRows + 2; // 5
                $headerRow3 = $this->titleRows + 3; // 6

                // Kolom yang tidak dibagi Laki-Laki/Perempuan -> merge vertikal 3 baris
                $sheet->setCellValue("A{$headerRow1}", 'No');
                $sheet->mergeCells("A{$headerRow1}:A{$headerRow3}");

                $sheet->setCellValue("B{$headerRow1}", 'Kemantren');
                $sheet->mergeCells("B{$headerRow1}:B{$headerRow3}");

                $sheet->setCellValue("C{$headerRow1}", 'Alamat Kemantren');
                $sheet->mergeCells("C{$headerRow1}:C{$headerRow3}");

                $sheet->setCellValue("L{$headerRow1}", 'Kelurahan');
                $sheet->mergeCells("L{$headerRow1}:L{$headerRow3}");

                // Grup kolom Laki-Laki/Perempuan: [kolom mulai, kolom akhir, label kategori, "di Kecamatan"/"di Kelurahan"]
                $groups = [
                    ['D', 'E', 'Fungsional', 'Kecamatan'],
                    ['F', 'G', 'Struktural', 'Kecamatan'],
                    ['H', 'I', 'Pelaksana', 'Kecamatan'],
                    ['J', 'K', 'Total Kecamatan', 'Kecamatan'],
                    ['M', 'N', 'Struktural', 'Kelurahan'],
                    ['O', 'P', 'Pelaksana', 'Kelurahan'],
                    ['Q', 'R', 'Total Kelurahan', 'Kelurahan'],
                ];

                foreach ($groups as [$colL, $colP, $label, $wilayah]) {
                    // Baris 4: nama kategori
                    $sheet->setCellValue("{$colL}{$headerRow1}", $label);
                    $sheet->mergeCells("{$colL}{$headerRow1}:{$colP}{$headerRow1}");

                    // Baris 5: "Jumlah PNS yang bekerja di Kecamatan/Kelurahan"
                    $sheet->setCellValue("{$colL}{$headerRow2}", "Jumlah PNS yang bekerja di {$wilayah}");
                    $sheet->mergeCells("{$colL}{$headerRow2}:{$colP}{$headerRow2}");

                    // Baris 6: Laki-Laki / Perempuan
                    $sheet->setCellValue("{$colL}{$headerRow3}", 'Laki-Laki');
                    $sheet->setCellValue("{$colP}{$headerRow3}", 'Perempuan');
                }

                /*
                |--------------------------------------------------------------------------
                | FORMAT HEADER TABEL
                |--------------------------------------------------------------------------
                */
                $sheet->getStyle("A{$headerRow1}:R{$headerRow3}")
                    ->getFont()
                    ->setBold(true);

                $sheet->getStyle("A{$headerRow1}:R{$headerRow3}")
                    ->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center')
                    ->setWrapText(true);

                /*
                |--------------------------------------------------------------------------
                | MERGE KEMANTREN
                |
                | Kolom A-K (No, Kemantren, Alamat Kemantren, Fungsional,
                | Struktural, Pelaksana, Total Kecamatan) akan di-merge sesuai
                | jumlah baris Kemantren.
                |
                | CATATAN: Excel TIDAK auto-fit tinggi baris untuk cell yang
                | di-merge, jadi kalau tidak diatur manual, alamat yang
                | panjang & di-wrap bisa terpotong / tidak kelihatan penuh.
                | Karena itu tinggi barisnya dihitung dari perkiraan jumlah
                | baris teks alamat (berdasarkan lebar kolom C), lalu dibagi
                | rata ke seluruh baris dalam grup Kemantren tsb.
                |--------------------------------------------------------------------------
                */
                $row = $this->headerRows + 1;

                $charsPerLine = 30; // kira-kira muat berapa karakter per baris di kolom C (lebar 32, sudah diperbesar)
                $lineHeightPt = 16;
                $minRowHeight = 24;

                foreach ($this->data as $d) {

                    if ($d['jumlah_baris_kemantren'] !== null) {

                        $span = $d['jumlah_baris_kemantren'];
                        $endRow = $row + $span - 1;

                        foreach (
                            [
                                'A',
                                'B',
                                'C',
                                'D',
                                'E',
                                'F',
                                'G',
                                'H',
                                'I',
                                'J',
                                'K'
                            ] as $col
                        ) {
                            $sheet->mergeCells(
                                "{$col}{$row}:{$col}{$endRow}"
                            );
                        }

                        // Sesuaikan tinggi baris supaya alamat kemantren yang
                        // di-wrap tetap kelihatan penuh (memanjang ke bawah).
                        $alamat = (string) ($d['kemantren_alamat'] ?? '');

                        if ($alamat !== '') {
                            $estimatedLines = (int) ceil(mb_strlen($alamat) / $charsPerLine);
                            $neededHeight = max($minRowHeight * $span, $estimatedLines * $lineHeightPt);
                            $perRowHeight = $neededHeight / $span;

                            for ($r = $row; $r <= $endRow; $r++) {
                                $sheet->getRowDimension($r)->setRowHeight($perRowHeight);
                            }
                        }
                    }

                    $row++;
                }

                /*
                |--------------------------------------------------------------------------
                | BARIS DATA TERAKHIR
                |--------------------------------------------------------------------------
                */
                $lastDataRow =
                    $this->headerRows + count($this->data);

                $dataStart = $this->headerRows + 1;

                /*
                |--------------------------------------------------------------------------
                | BORDER DATA
                |--------------------------------------------------------------------------
                */
                $sheet->getStyle(
                    "A{$headerRow1}:R{$lastDataRow}"
                )
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                /*
                |--------------------------------------------------------------------------
                | ALIGNMENT DATA
                |--------------------------------------------------------------------------
                */
                $sheet->getStyle(
                    "A{$dataStart}:R{$lastDataRow}"
                )
                    ->getAlignment()
                    ->setVertical('center');

                /*
                |--------------------------------------------------------------------------
                | CENTER KOLOM ANGKA & NO (kolom B/C -Kemantren & Alamat- dibiarkan
                | rata kiri karena berisi teks/alamat)
                |--------------------------------------------------------------------------
                */
                $sheet->getStyle(
                    "A{$dataStart}:A{$lastDataRow}"
                )
                    ->getAlignment()
                    ->setHorizontal('center');

                $sheet->getStyle(
                    "D{$dataStart}:R{$lastDataRow}"
                )
                    ->getAlignment()
                    ->setHorizontal('center');

                /*
                |--------------------------------------------------------------------------
                | ALAMAT KEMANTREN — dibuat memanjang ke BAWAH (wrap text), bukan
                | melebar ke samping. Lebar kolom C dibuat cukup lega (lihat
                | bagian LEBAR KOLOM di bawah) supaya teks alamat tidak
                | terlihat kekecilan/kepenuhan, dan otomatis turun ke baris
                | berikutnya kalau masih panjang.
                |--------------------------------------------------------------------------
                */
                $sheet->getStyle(
                    "C{$dataStart}:C{$lastDataRow}"
                )
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setHorizontal('left')
                    ->setVertical('center');

                /*
                |--------------------------------------------------------------------------
                | GRAND TOTAL
                |
                | Posisi 2 baris setelah data terakhir, di bawah kolom B-C
                |--------------------------------------------------------------------------
                */
                $sumRow = $lastDataRow + 2;

                /*
                |--------------------------------------------------------------------------
                | LABEL GRAND TOTAL
                |--------------------------------------------------------------------------
                */
                $sheet->setCellValue(
                    "B{$sumRow}",
                    'TOTAL L'
                );

                $sheet->setCellValue(
                    "C{$sumRow}",
                    'TOTAL P'
                );

                /*
                |--------------------------------------------------------------------------
                | HITUNG TOTAL L
                |--------------------------------------------------------------------------
                */
                $totalL =
                    array_sum(
                        array_filter(
                            array_column(
                                $this->data,
                                'kecamatan_total_l'
                            )
                        )
                    )
                    +
                    array_sum(
                        array_column(
                            $this->data,
                            'kelurahan_total_l'
                        )
                    );

                /*
                |--------------------------------------------------------------------------
                | HITUNG TOTAL P
                |--------------------------------------------------------------------------
                */
                $totalP =
                    array_sum(
                        array_filter(
                            array_column(
                                $this->data,
                                'kecamatan_total_p'
                            )
                        )
                    )
                    +
                    array_sum(
                        array_column(
                            $this->data,
                            'kelurahan_total_p'
                        )
                    );

                /*
                |--------------------------------------------------------------------------
                | MASUKKAN NILAI GRAND TOTAL
                |--------------------------------------------------------------------------
                */
                $sheet->setCellValue(
                    "B" . ($sumRow + 1),
                    $totalL
                );

                $sheet->setCellValue(
                    "C" . ($sumRow + 1),
                    $totalP
                );

                /*
                |--------------------------------------------------------------------------
                | FORMAT KOTAK GRAND TOTAL
                |--------------------------------------------------------------------------
                */
                $sheet->getStyle(
                    "B{$sumRow}:C" . ($sumRow + 1)
                )
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->getStyle(
                    "B{$sumRow}:C{$sumRow}"
                )
                    ->getFont()
                    ->setBold(true);

                $sheet->getStyle(
                    "B{$sumRow}:C" . ($sumRow + 1)
                )
                    ->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center');

                /*
                |--------------------------------------------------------------------------
                | LEBAR KOLOM
                |
                | Kolom B (Kemantren) & C (Alamat Kemantren) dibuat lebih lega
                | supaya kotaknya tidak terlihat kekecilan, tapi tetap lebih
                | sempit dibanding kolom lain supaya teks alamat turun ke
                | bawah (wrap) bukan melebar ke samping.
                |--------------------------------------------------------------------------
                */
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(24);
                $sheet->getColumnDimension('C')->setWidth(32);

                $sheet->getColumnDimension('D')->setWidth(15);
                $sheet->getColumnDimension('E')->setWidth(15);

                $sheet->getColumnDimension('F')->setWidth(15);
                $sheet->getColumnDimension('G')->setWidth(15);

                $sheet->getColumnDimension('H')->setWidth(15);
                $sheet->getColumnDimension('I')->setWidth(15);

                $sheet->getColumnDimension('J')->setWidth(18);
                $sheet->getColumnDimension('K')->setWidth(18);

                $sheet->getColumnDimension('L')->setWidth(20);

                $sheet->getColumnDimension('M')->setWidth(15);
                $sheet->getColumnDimension('N')->setWidth(15);

                $sheet->getColumnDimension('O')->setWidth(15);
                $sheet->getColumnDimension('P')->setWidth(15);

                $sheet->getColumnDimension('Q')->setWidth(20);
                $sheet->getColumnDimension('R')->setWidth(20);

                /*
                |--------------------------------------------------------------------------
                | TINGGI BARIS
                |
                | Baris data TIDAK diberi tinggi tetap (dibiarkan auto) supaya
                | Excel otomatis menambah tinggi baris ketika teks alamat
                | (wrap text) turun ke bawah.
                |--------------------------------------------------------------------------
                */
                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->getRowDimension(2)->setRowHeight(24);
                $sheet->getRowDimension(3)->setRowHeight(24);

                $sheet->getRowDimension($headerRow1)->setRowHeight(20);
                $sheet->getRowDimension($headerRow2)->setRowHeight(35);
                $sheet->getRowDimension($headerRow3)->setRowHeight(20);

                /*
                |--------------------------------------------------------------------------
                | FREEZE PANE
                |
                | Tabel bisa digeser ke kanan/kiri, tapi kolom A-C (No,
                | Kemantren, Alamat Kemantren) tetap terlihat/tidak ikut
                | tergeser. Referensi 'D1' (baris 1) sengaja dipakai supaya
                | HANYA kolom yang dibekukan — baris tetap bisa digeser
                | seperti biasa.
                |--------------------------------------------------------------------------
                */
                $sheet->freezePane('D1');
            },
        ];
    }
}
