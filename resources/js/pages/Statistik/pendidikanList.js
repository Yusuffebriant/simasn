// Daftar jenjang pendidikan (SD s.d. S3) dipakai bersama oleh
// AsnPendidikanPanel, PnsPendidikanPanel, dan PppkPendidikanPanel.
// "key" harus sama persis dengan key JSON dari
// statistikAsnPendidikan()/statistikPnsPendidikan()/statistikPppkPendidikan()
// di StatistikService. "label" dipakai untuk judul MiniStatCard,
// "chartLabel" dipakai untuk label singkat di sumbu-X grafik batang.
export const PENDIDIKAN_LIST = [
    { key: "sd", label: "Tamat SD atau sederajat", chartLabel: "SD" },
    { key: "smp", label: "SMP atau sederajat", chartLabel: "SMP" },
    { key: "sma", label: "SMA atau sederajat", chartLabel: "SMA" },
    { key: "diploma_i", label: "Diploma I", chartLabel: "D-I" },
    { key: "diploma_ii", label: "Diploma II", chartLabel: "D-II" },
    { key: "diploma_iii", label: "Diploma III", chartLabel: "D-III" },
    { key: "diploma_iv", label: "Diploma IV", chartLabel: "D-IV" },
    { key: "strata_1", label: "Strata 1", chartLabel: "S1" },
    { key: "strata_2", label: "Strata 2", chartLabel: "S2" },
    { key: "strata_3", label: "Strata 3", chartLabel: "S3" },
];