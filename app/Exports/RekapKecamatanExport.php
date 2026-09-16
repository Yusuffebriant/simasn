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
    protected int $headerRows = 2;

    public function __construct(protected string $periode)
    {
        $this->data = (new RekapService())
            ->rekapKecamatanKelurahan($periode);
    }

    /**
     * Data yang akan dimasukkan ke Excel
     */
    public function array(): array
    {
        $rows = [];

        foreach ($this->data as $d) {
            $rows[] = [
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

                // Kelurahan
                $d['kelurahan'],
                $d['kelurahan_alamat'],
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
     * Event untuk melakukan formatting Excel
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $sheet = $event->sheet->getDelegate();

                /*
                |--------------------------------------------------------------------------
                | Tambahkan baris untuk title dan header
                |--------------------------------------------------------------------------
                */
                $sheet->insertNewRowBefore(1, $this->headerRows);

                /*
                |--------------------------------------------------------------------------
                | TITLE
                |--------------------------------------------------------------------------
                */
                $sheet->setCellValue(
                    'A1',
                    'REKAPITULASI PNS KECAMATAN (KEMANTREN) DAN KELURAHAN'
                );

                $sheet->mergeCells('A1:R1');

                $sheet->getStyle('A1:R1')->getFont()->setBold(true);
                $sheet->getStyle('A1:R1')
                    ->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center');

                /*
                |--------------------------------------------------------------------------
                | HEADER
                |--------------------------------------------------------------------------
                */
                $headers = [
                    'A2' => 'Kemantren',
                    'B2' => 'Alamat Kemantren',

                    'C2' => 'Fungsional L',
                    'D2' => 'Fungsional P',

                    'E2' => 'Struktural L',
                    'F2' => 'Struktural P',

                    'G2' => 'Pelaksana L',
                    'H2' => 'Pelaksana P',

                    'I2' => 'Total Kecamatan L',
                    'J2' => 'Total Kecamatan P',

                    'K2' => 'Kelurahan',
                    'L2' => 'Alamat Kelurahan',

                    'M2' => 'Struktural L',
                    'N2' => 'Struktural P',

                    'O2' => 'Pelaksana L',
                    'P2' => 'Pelaksana P',

                    'Q2' => 'Total Kelurahan L',
                    'R2' => 'Total Kelurahan P',
                ];

                foreach ($headers as $cell => $label) {
                    $sheet->setCellValue($cell, $label);
                }

                /*
                |--------------------------------------------------------------------------
                | FORMAT HEADER
                |--------------------------------------------------------------------------
                */
                $sheet->getStyle('A2:R2')
                    ->getFont()
                    ->setBold(true);

                $sheet->getStyle('A2:R2')
                    ->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center')
                    ->setWrapText(true);

                /*
                |--------------------------------------------------------------------------
                | MERGE KEMANTREN
                |
                | Kolom A-J akan di-merge sesuai jumlah baris Kemantren
                |--------------------------------------------------------------------------
                */
                $row = $this->headerRows + 1;

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
                                'J'
                            ] as $col
                        ) {
                            $sheet->mergeCells(
                                "{$col}{$row}:{$col}{$endRow}"
                            );
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

                /*
                |--------------------------------------------------------------------------
                | BORDER DATA
                |--------------------------------------------------------------------------
                */
                $sheet->getStyle(
                    "A2:R{$lastDataRow}"
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
                    "A3:R{$lastDataRow}"
                )
                    ->getAlignment()
                    ->setVertical('center');

                /*
                |--------------------------------------------------------------------------
                | CENTER KOLOM ANGKA
                |--------------------------------------------------------------------------
                */
                $sheet->getStyle(
                    "C3:K{$lastDataRow}"
                )
                    ->getAlignment()
                    ->setHorizontal('center');

                $sheet->getStyle(
                    "M3:R{$lastDataRow}"
                )
                    ->getAlignment()
                    ->setHorizontal('center');

                /*
                |--------------------------------------------------------------------------
                | GRAND TOTAL
                |
                | Posisi 2 baris setelah data terakhir
                |--------------------------------------------------------------------------
                */
                $sumRow = $lastDataRow + 2;

                /*
                |--------------------------------------------------------------------------
                | LABEL GRAND TOTAL
                |--------------------------------------------------------------------------
                */
                $sheet->setCellValue(
                    "K{$sumRow}",
                    'TOTAL L'
                );

                $sheet->setCellValue(
                    "L{$sumRow}",
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
                    "K" . ($sumRow + 1),
                    $totalL
                );

                $sheet->setCellValue(
                    "L" . ($sumRow + 1),
                    $totalP
                );

                /*
                |--------------------------------------------------------------------------
                | FORMAT KOTAK GRAND TOTAL
                |--------------------------------------------------------------------------
                */
                $sheet->getStyle(
                    "K{$sumRow}:L" . ($sumRow + 1)
                )
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                $sheet->getStyle(
                    "K{$sumRow}:L{$sumRow}"
                )
                    ->getFont()
                    ->setBold(true);

                $sheet->getStyle(
                    "K{$sumRow}:L" . ($sumRow + 1)
                )
                    ->getAlignment()
                    ->setHorizontal('center')
                    ->setVertical('center');

                /*
                |--------------------------------------------------------------------------
                | LEBAR KOLOM
                |--------------------------------------------------------------------------
                */
                $sheet->getColumnDimension('A')->setWidth(20);
                $sheet->getColumnDimension('B')->setWidth(35);

                $sheet->getColumnDimension('C')->setWidth(15);
                $sheet->getColumnDimension('D')->setWidth(15);

                $sheet->getColumnDimension('E')->setWidth(15);
                $sheet->getColumnDimension('F')->setWidth(15);

                $sheet->getColumnDimension('G')->setWidth(15);
                $sheet->getColumnDimension('H')->setWidth(15);

                $sheet->getColumnDimension('I')->setWidth(18);
                $sheet->getColumnDimension('J')->setWidth(18);

                $sheet->getColumnDimension('K')->setWidth(20);

                // Alamat Kelurahan
                $sheet->getColumnDimension('L')->setWidth(40);

                $sheet->getColumnDimension('M')->setWidth(15);
                $sheet->getColumnDimension('N')->setWidth(15);

                $sheet->getColumnDimension('O')->setWidth(15);
                $sheet->getColumnDimension('P')->setWidth(15);

                $sheet->getColumnDimension('Q')->setWidth(20);
                $sheet->getColumnDimension('R')->setWidth(20);

                /*
                |--------------------------------------------------------------------------
                | TINGGI BARIS
                |--------------------------------------------------------------------------
                */
                $sheet->getRowDimension(1)->setRowHeight(25);
                $sheet->getRowDimension(2)->setRowHeight(40);
            },
        ];
    }
}