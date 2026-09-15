<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Seeder alamat Kemantren & Kelurahan Kota Yogyakarta.
 *
 * Data digenerate otomatis dari file "Tabel_Kecamatan.xlsx" (parsing
 * merged-cell aware, bukan diketik manual) sehingga bebas typo transkripsi.
 *
 * CATATAN: beberapa alamat sengaja tertulis terpotong (mis. NGUPASAN,
 * KRATON) karena memang begitu adanya di file sumber Excel (kena
 * word-wrap saat data dientry manual oleh tim BKPSDM). Silakan lengkapi
 * manual lewat UPDATE ke tabel alamat_wilayah kalau sudah punya data
 * yang lebih lengkap.
 */
class AlamatWilayahSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $data = [
            [
                'jenis' => 'kemantren',
                'kemantren' => 'MANTRIJERON',
                'kelurahan' => null,
                'alamat' => 'Jl. DI Panjaitan No.84, Suryodiningratan, Kec. Mantrijeron, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55141',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'MANTRIJERON',
                'kelurahan' => 'GEDONGKIWO',
                'alamat' => 'Gedongkiwo, Mantrijeron, Yogyakarta City, Special',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'MANTRIJERON',
                'kelurahan' => 'SURYODININGRATAN',
                'alamat' => 'Suryodiningratan MJ II/859 Kelurahan Suryodiningratan, Kecamatan Mantrijeron,Kota',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'MANTRIJERON',
                'kelurahan' => 'MANTRIJERON',
                'alamat' => 'Jl. Mantrijeron No.49B, Mantrijeron, Kec. Mantrijeron, Kota',
            ],
            [
                'jenis' => 'kemantren',
                'kemantren' => 'KRATON',
                'kelurahan' => null,
                'alamat' => 'Jalan Rotowijayan No 6 Yogyakarta',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'KRATON',
                'kelurahan' => 'PATEHAN',
                'alamat' => 'Jl. Nagan Lor No.17, Patehan, Kecamatan Kraton, Kota Yogyakarta,',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'KRATON',
                'kelurahan' => 'PANEMBAHAN',
                'alamat' => 'Jl. Langenastran Lor No.17, Panembahan, Kecamatan Kraton, Kota',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'KRATON',
                'kelurahan' => 'KADIPATEN',
                'alamat' => 'Komp. Ndalem Mangkubumen, Jl. Polowijan No.372, Kadipaten,',
            ],
            [
                'jenis' => 'kemantren',
                'kemantren' => 'MERGANGSAN',
                'kelurahan' => null,
                'alamat' => 'Jl. Sisingamangaraja No.55, Brontokusuman, Kec. Mergangsan, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55153',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'MERGANGSAN',
                'kelurahan' => 'BRONTOKUSUMAN',
                'alamat' => 'Jl. Prawirotaman IV No. 800, Brontokusuman, Kec.',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'MERGANGSAN',
                'kelurahan' => 'WIROGUNAN',
                'alamat' => 'Gang Brojopermana Jalan Taman Siswa Blok MG 2 No.1168,',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'MERGANGSAN',
                'kelurahan' => 'KEPARAKAN',
                'alamat' => 'Jalan Kol. Sugiyono Blok MG 1 No.1279, Keparakan, Kec.',
            ],
            [
                'jenis' => 'kemantren',
                'kemantren' => 'UMBULHARJO',
                'kelurahan' => null,
                'alamat' => 'Jl. Glagahsari No.99, Warungboto, Kec. Umbulharjo, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55164',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'UMBULHARJO',
                'kelurahan' => 'GIWANGAN',
                'alamat' => 'Jl. Pemukti Blok UH 7 No.700, Giwangan, Kec.',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'UMBULHARJO',
                'kelurahan' => 'SOROSUTAN',
                'alamat' => 'Jl. Gurami No.236, Sorosutan, Kec.',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'UMBULHARJO',
                'kelurahan' => 'PANDEYAN',
                'alamat' => '59QP+5GW, Pandeyan, Kec. Umbulharjo, Kota',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'UMBULHARJO',
                'kelurahan' => 'WARUNGBOTO',
                'alamat' => 'Warungboto UH IV No. 878, RT 31 / RW 08, Warungboto, Kec.',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'UMBULHARJO',
                'kelurahan' => 'TAHUNAN',
                'alamat' => 'Jl. Tuntungan, Tempel Wirogunan UH III/924,',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'UMBULHARJO',
                'kelurahan' => 'MUJA-MUJU',
                'alamat' => 'Jl. Balirejo No.31, Muja Muju, Kec. Umbulharjo,',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'UMBULHARJO',
                'kelurahan' => 'SEMAKI',
                'alamat' => 'JL SEMAKI GEDE UH 1 No.274, Semaki, Kec.',
            ],
            [
                'jenis' => 'kemantren',
                'kemantren' => 'KOTAGEDE',
                'kelurahan' => null,
                'alamat' => 'Jl. Nyi Wiji Adhisoro No.39, Prenggan, Kec. Kotagede, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55172',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'KOTAGEDE',
                'kelurahan' => 'PRENGGAN',
                'alamat' => 'Jl. Nyi Pembayun No. 40, Yogyakarta, 55172, Prenggan, Kotagede, Kota',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'KOTAGEDE',
                'kelurahan' => 'PURBAYAN',
                'alamat' => 'JL PURBAYAN KG 3 No.1190, Purbayan, Kec.',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'KOTAGEDE',
                'kelurahan' => 'REJOWINANGUN',
                'alamat' => 'Jl. Nyi Adi Sari No.754, Rejowinangun, Kec.',
            ],
            [
                'jenis' => 'kemantren',
                'kemantren' => 'GONDOKUSUMAN',
                'kelurahan' => null,
                'alamat' => 'Kecamatan Gondokusuman, Jalan Munggur No.32 Yogyakarta',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'GONDOKUSUMAN',
                'kelurahan' => 'BACIRO',
                'alamat' => 'Mawar II No.32, Baciro, Kec. Gondokusuman, Kota',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'GONDOKUSUMAN',
                'kelurahan' => 'DEMANGAN',
                'alamat' => 'Jl. Munggur Jl. Srikandi, Demangan, Kec. Gondokusuman, Kota',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'GONDOKUSUMAN',
                'kelurahan' => 'KLITREN',
                'alamat' => 'Jl. Mangga No.11, Klitren, Kec. Gondokusuman, Kota',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'GONDOKUSUMAN',
                'kelurahan' => 'KOTABARU',
                'alamat' => 'Jl. Juwadi No. 29, Kotabaru, RT:0_/RW:02, Kelurahan Kotabaru,',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'GONDOKUSUMAN',
                'kelurahan' => 'TERBAN',
                'alamat' => '274, Gg. Bimo Gg. Puntodewo No.V, Terban, Kec. Gondokusuman, Kota',
            ],
            [
                'jenis' => 'kemantren',
                'kemantren' => 'DANUREJAN',
                'kelurahan' => null,
                'alamat' => 'Jl. Hayam Wuruk No.28, Bausasran, Kec. Danurejan, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55212',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'DANUREJAN',
                'kelurahan' => 'SURYATMAJAN',
                'alamat' => 'Jalan Mataram No. 68, Yogyakarta, DI Yogyakarta 55213, Indonesia',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'DANUREJAN',
                'kelurahan' => 'TEGALPANGGUNG',
                'alamat' => 'Lempuyangan (Kel. Tegalpanggung), Yogyakarta, DI Yogyakarta, Indonesia',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'DANUREJAN',
                'kelurahan' => 'BAUSASRAN',
                'alamat' => 'Bausasran, Yogyakarta, DI Yogyakarta, Indonesia',
            ],
            [
                'jenis' => 'kemantren',
                'kemantren' => 'PAKUALAMAN',
                'kelurahan' => null,
                'alamat' => 'Jl. Suryopranoto No 35 Gunungketur Pakualaman Kota Yogyakarta Kode Pos 55111',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'PAKUALAMAN',
                'kelurahan' => 'PURWOKINANTI',
                'alamat' => 'Jl. Harjowinatan No.19, Purwokinanti, Pakualaman, Kota',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'PAKUALAMAN',
                'kelurahan' => 'GUNUNGKETUR',
                'alamat' => 'Jl. Jayaningprangan No.10, Gunungketur,',
            ],
            [
                'jenis' => 'kemantren',
                'kemantren' => 'GONDOMANAN',
                'kelurahan' => null,
                'alamat' => 'Jl. Ibu Ruswo No.3A, Prawirodirjan, Kec. Gondomanan, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55131',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'GONDOMANAN',
                'kelurahan' => 'PRAWIRODIRJAN',
                'alamat' => 'Jl. Ireda No.39, RT.53/RW.11, Prawirodirjan, Kec.',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'GONDOMANAN',
                'kelurahan' => 'NGUPASAN',
                'alamat' => 'Jl. Mayor Suryotomo No. 575 (Samping Timur Hotel',
            ],
            [
                'jenis' => 'kemantren',
                'kemantren' => 'NGAMPILAN',
                'kelurahan' => null,
                'alamat' => 'Jl. KH Wahid Hasyim No.12, Notoprajan, Ngampilan, Kota Yogyakarta, Daerah Istimewa',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'NGAMPILAN',
                'kelurahan' => 'NOTOPRAJAN',
                'alamat' => 'Jalan KH Wakhid Hasyim No.4, Notoprajan, Ngampilan, Kota',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'NGAMPILAN',
                'kelurahan' => 'NGAMPILAN',
                'alamat' => 'Jl. KH Wahid Hasyim No.12, Notoprajan,',
            ],
            [
                'jenis' => 'kemantren',
                'kemantren' => 'WIROBRAJAN',
                'kelurahan' => null,
                'alamat' => 'Jl. Dorodasih No.16, Patangpuluhan, Wirobrajan, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55251',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'WIROBRAJAN',
                'kelurahan' => 'PATANGPULUHAN',
                'alamat' => 'Jl. Lokananta No.3, Patangpuluhan, Wirobrajan, Kota',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'WIROBRAJAN',
                'kelurahan' => 'WIROBRAJAN',
                'alamat' => 'Jl. Kresno No.1, Wirobrajan, Kota Yogyakarta, Daerah',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'WIROBRAJAN',
                'kelurahan' => 'PAKUNCEN',
                'alamat' => 'Pakuncen, Wirobrajan, Yogyakarta City, Special',
            ],
            [
                'jenis' => 'kemantren',
                'kemantren' => 'GEDONGTENGEN',
                'kelurahan' => null,
                'alamat' => 'Jl. Jlagran Lor No.52, Pringgokusuman, Gedong Tengen, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55272',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'GEDONGTENGEN',
                'kelurahan' => 'PRINGGOKUSUMAN',
                'alamat' => 'Jalan Letjen Suprapto No.84, Pringgokusuman, Gedong Tengen,',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'GEDONGTENGEN',
                'kelurahan' => 'SOSROMENDURAN',
                'alamat' => 'Jl. Sosrowijayan no.21, Yogyakarta, DI Yogyakarta',
            ],
            [
                'jenis' => 'kemantren',
                'kemantren' => 'JETIS',
                'kelurahan' => null,
                'alamat' => 'Jl. Pangeran Diponegoro No.91, 001, Bumijo, Kec. Jetis, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55231',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'JETIS',
                'kelurahan' => 'BUMIJO',
                'alamat' => 'Jl. Tentara Zeni Pelajar No.8, Bumijo, Kec. Jetis, Kota Yogyakarta,',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'JETIS',
                'kelurahan' => 'GOWONGAN',
                'alamat' => 'Jl. Gowongan Lor No.12 B, RW.001, Gowongan, Kec. Jetis, Kota Yogyakarta, Daerah',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'JETIS',
                'kelurahan' => 'COKRODININGRATAN',
                'alamat' => 'Jl. Cokrodiningratan JT II No. 129, Cokrodiningratan, Kec.',
            ],
            [
                'jenis' => 'kemantren',
                'kemantren' => 'TEGALREJO',
                'kelurahan' => null,
                'alamat' => 'Jl. Tompeyan III No.219, Tegalrejo, Kota Yogyakarta, 55244',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'TEGALREJO',
                'kelurahan' => 'KRICAK',
                'alamat' => 'Jalan Jatimulyo, Tr I No.666, Kricak, Kec. Tegalrejo, Kota',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'TEGALREJO',
                'kelurahan' => 'KARANGWARU',
                'alamat' => 'Karangwaru, Yogyakarta, Yogyakarta City, Special',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'TEGALREJO',
                'kelurahan' => 'TEGALREJO',
                'alamat' => 'Jl. Wiratama No.48, Tegalrejo, Kec. Tegalrejo, Kota Yogyakarta, Daerah Istimewa Yogyakarta 55244',
            ],
            [
                'jenis' => 'kelurahan',
                'kemantren' => 'TEGALREJO',
                'kelurahan' => 'BENER',
                'alamat' => 'Jalan Bener No. 48, Yogyakarta, DI Yogyakarta, Indonesia',
            ],
        ];

        foreach ($data as $row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            DB::table('alamat_wilayah')->updateOrInsert(
                ['jenis' => $row['jenis'], 'kemantren' => $row['kemantren'], 'kelurahan' => $row['kelurahan']],
                $row
            );
        }
    }
}