<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ambang penurunan jumlah pegawai saat import
    |--------------------------------------------------------------------------
    | Import bersifat GANTI TOTAL: pegawai lama yang tidak ada di file baru
    | dihapus permanen. Kalau jumlah baris valid di file lebih sedikit dari
    | jumlah pegawai sekarang melebihi persen ini, upload ditahan dan admin
    | harus mengonfirmasi dulu (biasanya tandanya file salah/terpotong).
    */
    'ambang_penurunan_persen' => (int) env('IMPORT_AMBANG_PENURUNAN_PERSEN', 20),

];