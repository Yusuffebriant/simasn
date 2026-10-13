<?php

namespace App\Exports;

use App\Services\RekapService;
use App\Services\RekapParuhWaktuTambahanService;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Rekap PPPK Paruh Waktu, dipisah pria / wanita / jumlah. Satu class untuk
 * tiga sudut pandang, dipilih lewat $jenis:
 *
 *   'instansi'      (default) per perangkat daerah
 *   'pendidikan'              per tingkat pendidikan
 *   'jenis_jabatan'           per jenis jabatan (pelaksana/fungsional/struktural)
 *   'usia'                    per kelompok usia
 *   'pensiun'                 perkiraan masa pensiun per tahun
 *   'jf_rumpun'               rincian JF (Pendidikan / Kesehatan / Teknis)
 *   'jabatan'                 jabatan terbanyak
 *
 * Data diambil dari RekapService — sumber yang sama dengan endpoint JSON
 * /rekap/paruh-waktu, jadi angka di Excel = angka di sistem.
 *
 * Gaya (warna, border, judul 3 baris) disamakan dengan RekapNakesExport.
 */
class RekapParuhWaktuExport implements FromArray, WithEvents, WithTitle
{
    protected array $data;
    protected int $headerRows = 5; // baris 1-3 judul, 4 kosong, 5 header kolom
    protected int $jumlahBaris;

    protected string $warnaBiru = 'DCE6F2';   // header tabel & kolom Jumlah
    protected string $warnaOranye = 'FCD5B5'; // baris TOTAL

    /** Rincian "Jabatan lainnya" (hanya untuk $jenis = 'jabatan'). */
    protected array $rincianLainnya = [];
    protected ?int $totalRekap = null; // total Paruh Waktu menurut rekap per perangkat daerah

    public function __construct(protected string $periode, protected string $jenis = 'instansi')
    {
        $service = new RekapService();
        $tambahan = new RekapParuhWaktuTambahanService();

        $this->data = match ($jenis) {
            'pendidikan' => $service->rekapParuhWaktuPendidikan($periode),
            'jenis_jabatan' => $service->rekapParuhWaktuJenisJabatan($periode),
            'usia' => $tambahan->rekapUsia($periode),
            'pensiun' => $tambahan->rekapPerkiraanPensiun($periode),
            'jf_rumpun' => $tambahan->rekapJfRumpun($periode),
            'jabatan' => $tambahan->rekapJabatanTerbanyak($periode),
            default => $service->rekapParuhWaktu($periode),
        };

        if ($jenis === 'jabatan') {
            $this->rincianLainnya = $tambahan->rincianJabatanLainnya($periode);
            $this->totalRekap = array_sum(array_column($service->rekapParuhWaktu($periode), 'jumlah'));
        }

        // Minimal 1 baris supaya border/total tidak error saat belum ada data.
        $this->jumlahBaris = max(1, count($this->data));
    }

    public function title(): string
    {
        return $this->formatPeriode($this->periode); // contoh: "Oktober 2026"
    }

    public function array(): array
    {
        if (count($this->data) === 0) {
            return [[1, '(belum ada data PPPK paruh waktu)', 0, 0, 0]];
        }

        $rows = [];
        $no = 1;
        foreach ($this->data as $d) {
            $rows[] = [$no++, $d['instansi'] ?? $d['kategori'], $d['pria'], $d['wanita'], $d['jumlah']];
        }

        return $rows;
    }

    protected function subjudul(): string
    {
        return match ($this->jenis) {
            'pendidikan' => 'DATA PPPK PARUH WAKTU, DIPERINCI MENURUT TINGKAT PENDIDIKAN DAN JENIS KELAMIN',
            'jenis_jabatan' => 'DATA PPPK PARUH WAKTU, DIPERINCI MENURUT JENIS JABATAN DAN JENIS KELAMIN',
            'usia' => 'DATA PPPK PARUH WAKTU, DIPERINCI MENURUT KELOMPOK USIA DAN JENIS KELAMIN',
            'pensiun' => 'DATA PPPK PARUH WAKTU, PERKIRAAN MASA PENSIUN PER TAHUN DAN JENIS KELAMIN',
            'jf_rumpun' => 'DATA PPPK PARUH WAKTU JABATAN FUNGSIONAL, DIPERINCI MENURUT RUMPUN DAN JENIS KELAMIN',
            'jabatan' => 'DATA PPPK PARUH WAKTU, JABATAN TERBANYAK MENURUT JENIS KELAMIN',
            default => 'DATA PPPK PARUH WAKTU, DIPERINCI MENURUT PERANGKAT DAERAH DAN JENIS KELAMIN',
        };
    }

    protected function labelKolom(): string
    {
        return match ($this->jenis) {
            'pendidikan' => 'Tingkat Pendidikan',
            'jenis_jabatan' => 'Jenis Jabatan',
            'usia' => 'Kelompok Usia',
            'pensiun' => 'Perkiraan Pensiun',
            'jf_rumpun' => 'Rumpun JF',
            'jabatan' => 'Jabatan',
            default => 'Perangkat Daerah',
        };
    }

    private function angka(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }

    /**
     * Kalimat keterangan "Jabatan lainnya", disusun dari data terbaru
     * sehingga otomatis menyesuaikan setiap import baru. Null kalau tidak
     * ada jabatan yang digabung.
     */
    protected function keteranganJabatanLainnya(): ?string
    {
        $r = $this->rincianLainnya;
        if ($r === []) {
            return null;
        }

        $jenis = count($r);
        $jumlahPerJabatan = array_unique(array_column($r, 'jumlah'));
        $pria = array_sum(array_column($r, 'pria'));
        $wanita = array_sum(array_column($r, 'wanita'));

        $isi = "Jabatan lainnya ({$jenis} jenis) berisi {$jenis} jabatan";
        if (count($jumlahPerJabatan) === 1) {
            $isi .= ", masing-masing {$jumlahPerJabatan[0]} orang";
        } else {
            $isi .= ', masing-masing ' . min($jumlahPerJabatan) . '-' . max($jumlahPerJabatan) . ' orang';
        }
        $isi .= match (true) {
            $pria === 0 => ', semuanya wanita',
            $wanita === 0 => ', semuanya pria',
            default => ", dengan total {$pria} pria dan {$wanita} wanita",
        };
        $isi .= '.';

        $total = array_sum(array_column($this->data, 'jumlah'));
        $isi .= ' Dihitung otomatis dari data PPPK Paruh Waktu aktif hasil import terakhir; ';
        if ($this->totalRekap === null || $total === $this->totalRekap) {
            $isi .= 'total ' . $this->angka($total) . ' cocok dengan rekap.';
        } else {
            $isi .= 'PERHATIAN: total ' . $this->angka($total) . ' tidak sama dengan rekap per perangkat daerah ('
                . $this->angka($this->totalRekap) . ').';
        }

        return $isi;
    }

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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->insertNewRowBefore(1, $this->headerRows);

                $sheet->setCellValue(
                    'A1',
                    'REKAPITULASI ASN PEMERINTAH DAERAH KAB/KOTA PEMERINTAH KOTA YOGYAKARTA'
                );
                $sheet->setCellValue('A2', $this->subjudul());
                $sheet->setCellValue(
                    'A3',
                    'KEADAAN : ' . mb_strtoupper($this->formatPeriode($this->periode))
                );
                foreach ([1, 2, 3] as $r) {
                    $sheet->mergeCells("A{$r}:E{$r}");
                    $sheet->getStyle("A{$r}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$r}")->getAlignment()
                        ->setHorizontal('center')
                        ->setVertical('center');
                }

                $sheet->setCellValue('A5', 'No.');
                $sheet->setCellValue('B5', $this->labelKolom());
                $sheet->setCellValue('C5', 'Pria');
                $sheet->setCellValue('D5', 'Wanita');
                $sheet->setCellValue('E5', 'Jumlah');

                $firstDataRow = $this->headerRows + 1;
                $lastDataRow = $this->headerRows + $this->jumlahBaris;
                $totalRow = $lastDataRow + 1;

                $sheet->setCellValue('A' . $totalRow, '');
                $sheet->setCellValue('B' . $totalRow, 'TOTAL');

                foreach (['C', 'D', 'E'] as $col) {
                    $sum = 0;
                    for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                        $sum += (float) $sheet->getCell($col . $r)->getValue();
                    }
                    $sheet->setCellValue($col . $totalRow, $sum);
                }

                $sheet->getStyle('A5:E5')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A5:E5')->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center')
                    ->setWrapText(true);

                // ===== Styling =====
                $thin = Border::BORDER_THIN;
                $hair = Border::BORDER_HAIR;

                $warnai = function (string $range, string $rgb) use ($sheet) {
                    $sheet->getStyle($range)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($rgb);
                };

                // Header tabel
                $sheet->getStyle('A5:E5')->getBorders()->getAllBorders()->setBorderStyle($thin);
                $warnai('A5:E5', $this->warnaBiru);

                // Baris data
                for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
                    $top = $r === $firstDataRow ? $thin : $hair;
                    $bottom = $r === $lastDataRow ? $thin : $hair;
                    $borders = $sheet->getStyle("A{$r}:E{$r}")->getBorders();
                    $borders->getLeft()->setBorderStyle($thin);
                    $borders->getRight()->setBorderStyle($thin);
                    $borders->getVertical()->setBorderStyle($thin);
                    $borders->getTop()->setBorderStyle($top);
                    $borders->getBottom()->setBorderStyle($bottom);
                }

                // Nomor rata tengah
                $sheet->getStyle("A{$firstDataRow}:A{$lastDataRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_TOP);

                // Kolom Jumlah (E): biru muda + bold
                $warnai("E{$firstDataRow}:E{$lastDataRow}", $this->warnaBiru);
                $sheet->getStyle("E{$firstDataRow}:E{$lastDataRow}")->getFont()->setBold(true);

                // Baris TOTAL
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle($thin);
                $sheet->getStyle("A{$totalRow}:E{$totalRow}")->getFont()->setBold(true);
                $warnai("A{$totalRow}:E{$totalRow}", $this->warnaOranye);
                $sheet->getStyle("C{$totalRow}:E{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Lebar kolom
                $sheet->getColumnDimension('A')->setWidth(8);
                $sheet->getColumnDimension('B')->setWidth(55);
                $sheet->getColumnDimension('C')->setWidth(10);
                $sheet->getColumnDimension('D')->setWidth(10);
                $sheet->getColumnDimension('E')->setWidth(12);

                $sheet->getStyle("B{$firstDataRow}:B{$lastDataRow}")->getAlignment()
                    ->setWrapText(true)
                    ->setVertical('top');
                $sheet->getStyle("C{$firstDataRow}:E{$lastDataRow}")->getAlignment()
                    ->setHorizontal('center');

                // Keterangan "Jabatan lainnya" + rincian (hanya sheet jabatan)
                $this->tulisKeterangan($sheet, $totalRow + 2);
            },
        ];
    }

    protected function tulisKeterangan($sheet, int $row): void
    {
        $teks = $this->keteranganJabatanLainnya();
        if ($teks === null) {
            return;
        }

        $thin = Border::BORDER_THIN;

        $sheet->setCellValue("A{$row}", 'Keterangan: ' . $teks);
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
        $sheet->getStyle("A{$row}")->getAlignment()
            ->setWrapText(true)
            ->setVertical('top')
            ->setHorizontal('left');
        // Sel gabungan tidak auto-tinggi di Excel: hitung manual (+-90 huruf per baris).
        $sheet->getRowDimension($row)->setRowHeight(max(1, (int) ceil(mb_strlen($teks) / 90)) * 15 + 4);

        // Tabel rincian
        $head = $row + 2;
        $sheet->setCellValue("B{$head}", 'Rincian Jabatan lainnya');
        $sheet->setCellValue("C{$head}", 'Pria');
        $sheet->setCellValue("D{$head}", 'Wanita');
        $sheet->setCellValue("E{$head}", 'Jumlah');
        $sheet->getStyle("B{$head}:E{$head}")->getFont()->setBold(true);
        $sheet->getStyle("B{$head}:E{$head}")->getAlignment()->setHorizontal('center');
        $sheet->getStyle("B{$head}:E{$head}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->warnaBiru);

        $r = $head;
        foreach ($this->rincianLainnya as $d) {
            $r++;
            $sheet->setCellValue("B{$r}", $d['kategori']);
            $sheet->setCellValue("C{$r}", $d['pria']);
            $sheet->setCellValue("D{$r}", $d['wanita']);
            $sheet->setCellValue("E{$r}", $d['jumlah']);
        }

        $sheet->getStyle("B{$head}:E{$r}")->getBorders()->getAllBorders()->setBorderStyle($thin);
        $sheet->getStyle("B" . ($head + 1) . ":B{$r}")->getAlignment()->setWrapText(true)->setVertical('top');
        $sheet->getStyle("C" . ($head + 1) . ":E{$r}")->getAlignment()->setHorizontal('center');
    }
}