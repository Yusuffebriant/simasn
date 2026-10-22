// Rumus Indeks Kumulatif (sisi tampilan).
// HARUS sama dengan IndeksKumulatifService::hitungDeret() di backend dan
// dengan isi file Excel. Jika diubah, ubah juga PHP-nya.

/**
 * Hitung deret angka sesuai mode.
 *  - jumlah    : jumlah apa adanya
 *  - selisih   : jumlah bulan ini - bulan sebelumnya yang punya data
 *  - kumulatif : jumlah bulan ini - periode dasar
 *  - indeks    : berantai, periode dasar = 100
 *
 * dasar = urutan (mulai 0) periode dasar; null = bulan pertama yang punya data.
 * Data kosong (null) dilewati dan TIDAK dianggap 0. Dasar kosong atau 0
 * membuat kumulatif/indeks bernilai null (tidak ada pembagian dengan nol).
 */
export function hitungNilaiDeret(nilai, mode, dasar = null) {
    let idxDasar = dasar;
    if (idxDasar === null) {
        const i = nilai.findIndex((v) => v !== null && v !== undefined);
        idxDasar = i >= 0 ? i : null;
    }
    const awal = idxDasar === null ? null : nilai[idxDasar] ?? null;

    let sebelumnya = null;
    let indeksSebelumnya = null;

    return nilai.map((v, i) => {
        if (v === null || v === undefined) return null;

        let hasil = null;
        const sesudahDasar = idxDasar !== null && i >= idxDasar;

        if (mode === "jumlah") hasil = v;
        else if (mode === "selisih") hasil = sebelumnya === null ? null : v - sebelumnya;
        else if (mode === "kumulatif") hasil = awal !== null && sesudahDasar ? v - awal : null;
        else if (mode === "indeks" && awal !== null && awal > 0 && sesudahDasar) {
            let idx;
            if (i === idxDasar) idx = 100;
            else if (sebelumnya > 0 && indeksSebelumnya !== null) idx = indeksSebelumnya * (v / sebelumnya);
            else idx = (v / awal) * 100;

            indeksSebelumnya = idx;
            hasil = idx;
        }

        sebelumnya = v;
        return hasil;
    });
}

export const fmtAngka = (n) => n.toLocaleString("id-ID");
export const fmtSelisih = (n) => (n > 0 ? `+${fmtAngka(n)}` : fmtAngka(n));
export const fmtIndeks = (n) =>
    n.toLocaleString("id-ID", { minimumFractionDigits: 1, maximumFractionDigits: 1 });

/** Nilai angka -> {teks, arah} untuk sel tabel; null tetap null. */
export function hitungDeret(nilai, mode, dasar = null) {
    return hitungNilaiDeret(nilai, mode, dasar).map((h) => {
        if (h === null) return null;
        if (mode === "jumlah") return { teks: fmtAngka(h), arah: 0 };
        if (mode === "indeks") return { teks: fmtIndeks(h), arah: Math.sign(h - 100) };
        return { teks: fmtSelisih(h), arah: Math.sign(h) };
    });
}