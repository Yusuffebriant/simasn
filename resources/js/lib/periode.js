// Periode dalam format "YYYY-MM", sesuai format yang dipakai backend
// (query param `periode` & Cache key di RekapController/RekapService).
// Default-nya bulan berjalan, tapi pengguna tetap bisa memilih periode lain
// secara manual lewat kalender bulan (input type="month").

/**
 * Periode berjalan (bulan ini), dipakai sebagai nilai default saat halaman
 * rekap/export pertama kali dibuka.
 */
export function getCurrentPeriode() {
    const now = new Date();
    const bulan = String(now.getMonth() + 1).padStart(2, "0");
    return `${now.getFullYear()}-${bulan}`;
}

const BULAN_NAMA = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];

/**
 * Ubah "YYYY-MM" menjadi label yang enak dibaca, mis. "Agustus 2026".
 */
export function formatPeriodeLabel(periode) {
    if (!periode) return "-";

    const [tahun, bln] = periode.split("-");
    const idx = parseInt(bln, 10) - 1;
    return BULAN_NAMA[idx] ? `${BULAN_NAMA[idx]} ${tahun}` : periode;
}