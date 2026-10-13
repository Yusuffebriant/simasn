import { useEffect, useState } from "react";
import { apiFetch } from "../../lib/api";
import { formatPeriodeLabel } from "../../lib/periode";

/**
 * Pilihan periode untuk rekapitulasi.
 *
 * Tabel pegawai hanya menyimpan kondisi TERKINI (tiap import menimpa data
 * berdasarkan NIP). Supaya rekap periode lama tidak hilang, setiap import
 * yang selesai membekukan hasil rekapnya sebagai snapshot
 * (lihat RekapSnapshotService di backend).
 *
 * Daftar yang bisa dipilih di sini:
 * - periode AKTIF (import terakhir yang berhasil) -> data terkini, dihitung langsung;
 * - periode LAMA yang punya snapshot -> data beku saat periode itu diimport.
 *
 * Default-nya periode aktif. Kalau belum ada periode lama, tampilannya
 * tetap berupa label biasa seperti sebelumnya.
 */
function PeriodeLabel({ onLoaded }) {
    const [aktif, setAktif] = useState(null);
    const [opsi, setOpsi] = useState([]);
    const [terpilih, setTerpilih] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError("");

            try {
                const res = await apiFetch("/periode/riwayat");

                if (!res.ok) {
                    throw new Error("Gagal memuat periode.");
                }

                const data = await res.json();
                const periodeAktif = data.aktif || null;

                const semua = new Set(
                    (data.periode || []).map((p) => p.periode)
                );
                if (periodeAktif) {
                    semua.add(periodeAktif);
                }

                // Terbaru di atas (format YYYY-MM bisa diurutkan sebagai teks).
                const urut = [...semua].sort().reverse();

                if (!cancelled) {
                    setAktif(periodeAktif);
                    setOpsi(urut);
                    setTerpilih(periodeAktif);

                    if (periodeAktif) {
                        onLoaded?.(periodeAktif);
                    }
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err.message || "Gagal memuat periode.");
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        }

        load();

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function handleChange(e) {
        const nilai = e.target.value;
        setTerpilih(nilai);
        onLoaded?.(nilai);
    }

    const sedangMelihatArsip = terpilih && aktif && terpilih !== aktif;

    return (
        <div>
            {loading ? (
                <div className="border p-2 rounded text-sm bg-gray-50 text-gray-700 min-w-[10rem]">
                    Memuat periode...
                </div>
            ) : opsi.length > 1 ? (
                <select
                    value={terpilih || ""}
                    onChange={handleChange}
                    className="border p-2 rounded text-sm bg-white text-gray-700 min-w-[10rem]"
                    aria-label="Pilih periode rekapitulasi"
                >
                    {opsi.map((p) => (
                        <option key={p} value={p}>
                            {formatPeriodeLabel(p)}
                            {p === aktif ? " (aktif)" : ""}
                        </option>
                    ))}
                </select>
            ) : (
                <div className="border p-2 rounded text-sm bg-gray-50 text-gray-700 min-w-[10rem]">
                    {aktif ? formatPeriodeLabel(aktif) : "Belum ada data"}
                </div>
            )}

            {sedangMelihatArsip && (
                <p className="text-xs text-amber-700 mt-1 max-w-[16rem]">
                    Menampilkan arsip periode ini. Tombol Export Excel masih
                    memakai data terkini.
                </p>
            )}

            {error && <p className="text-xs text-red-600 mt-1">{error}</p>}
        </div>
    );
}

export default PeriodeLabel;