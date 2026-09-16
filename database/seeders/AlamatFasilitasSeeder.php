<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AlamatFasilitasSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $data = [
            ['nama' => 'UPT PUSKESMAS DANUREJAN 1', 'alamat' => 'Jalan Bausasran DN3 No.819, Bausasran, Kec. Danurejan, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55211'],
            ['nama' => 'UPT PUSKESMAS DANUREJAN 2', 'alamat' => 'Jl. Krasak Timur No.34, Bausasran, Kec. Danurejan, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55211'],
            ['nama' => 'UPT PUSKESMAS GEDONGTENGEN', 'alamat' => 'Jl. Pringgokusuman No.30, Pringgokusuman, Gedong Tengen, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55272'],
            ['nama' => 'UPT PUSKESMAS GONDOKUSUMAN 1', 'alamat' => '694J+648, Jl. Tunjung No.1, Baciro, Kec. Gondokusuman, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55225'],
            ['nama' => 'UPT PUSKESMAS GONDOKUSUMAN 2', 'alamat' => 'Jl. DR. Sardjito No.22, Terban, Kec. Gondokusuman, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55223'],
            ['nama' => 'UPT PUSKESMAS GONDOMANAN', 'alamat' => 'Jalan Ledok No.9, Prawirodirjan, Kec. Gondomanan, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55121'],
            ['nama' => 'UPT PUSKESMAS JETIS', 'alamat' => 'Jl. Pangeran Diponegoro No.91, Bumijo, Kec. Jetis, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55231'],
            ['nama' => 'UPT PUSKESMAS KOTAGEDE 1', 'alamat' => 'Jl. Kemasan No.12, Prenggan, Kec. Kotagede, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55173'],
            ['nama' => 'UPT PUSKESMAS KOTAGEDE 2', 'alamat' => 'Jl. Ki Penjawi No.4, Rejowinangun, Kec. Kotagede, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55172'],
            ['nama' => 'UPT PUSKESMAS KRATON', 'alamat' => 'Jalan Musikanan KT II No.457, Panembahan, Kecamatan Kraton, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55131'],
            ['nama' => 'UPT PUSKESMAS MANTRIJERON', 'alamat' => 'Jl. DI Panjaitan No.82, Suryodiningratan, Kec. Mantrijeron, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55141'],
            ['nama' => 'UPT PUSKESMAS MERGANGSAN', 'alamat' => 'Gg. Brojopermono, Wirogunan, Kec. Mergangsan, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55151'],
            ['nama' => 'UPT PUSKESMAS NGAMPILAN', 'alamat' => 'JL. Munir Serangan Blok.NG.II No.215, Notoprajan, Ngampilan, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55262'],
            ['nama' => 'UPT PUSKESMAS PAKUALAMAN', 'alamat' => 'Jl. Jayeng Prawiran No.13, Purwokinanti, Pakualaman, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55166'],
            ['nama' => 'UPT PUSKESMAS TEGALREJO', 'alamat' => 'Jl. Magelang No.180 Km.2, Karangwaru, Kec. Tegalrejo, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55242'],
            ['nama' => 'UPT PUSKESMAS UMBULHARJO 1', 'alamat' => 'Jl. Veteran No.43, Muja Muju, Kec. Umbulharjo, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55165'],
            ['nama' => 'UPT PUSKESMAS UMBULHARJO 2', 'alamat' => 'Jl. Hibrida No.194, Muja Muju, Kec. Umbulharjo, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55165'],
            ['nama' => 'UPT PUSKESMAS WIROBRAJAN', 'alamat' => '58QX+P6V, Jl. Dorodasih, Patangpuluhan, Wirobrajan, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55251'],
            ['nama' => 'RUMAH SAKIT UMUM DAERAH KOTA YOGYAKARTA', 'alamat' => 'Jl. Ki Ageng Pemanahan No.1-6, Sorosutan, Kec. Umbulharjo, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55162'],
['nama' => 'RUMAH SAKIT PRATAMA', 'alamat' => 'Karanganyar, Jl. Kolonel Sugiyono No.98, Brontokusuman, Kec. Mergangsan, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55153'],
        ];

        foreach ($data as $row) {
            $row['jenis'] = 'nakes';
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            DB::table('alamat_fasilitas')->updateOrInsert(
                ['nama' => $row['nama']],
                $row
            );
        }
    }
}