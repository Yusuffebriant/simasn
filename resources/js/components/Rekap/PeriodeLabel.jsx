import { useEffect, useState } from "react";
import { apiFetch } from "../../lib/api";
import { formatPeriodeLabel } from "../../lib/periode";

/**
 * Menampilkan periode data yang SEDANG berlaku di rekapitulasi — bukan
 * pilihan bebas.
 *
 * Tabel pegawai hanya menyimpan kondisi TERKINI (tiap import menimpa data
 * berdasarkan NIP), bukan riwayat per bulan. Jadi rekap tidak pernah bisa
 * menampilkan "periode Agustus" secara terpisah dari "periode Mei" kalau
 * belum ada import baru sesudahnya — dulu dropdown periode di sini
 * membiarkan pengguna memilih bulan, padahal hasilnya selalu sama saja
 * (data terkini), jadi terkesan seperti bug.
 *
 * Sekarang periode di sini otomatis mengikuti periode yang dipilih saat
 * import terakhir kali berhasil diproses (lihat PreviewExcel di alur
 * import) — tidak bisa diubah dari halaman ini.
 */
function PeriodeLabel({ onLoaded }) {
    const [periode, setPeriode] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError("");

            try {
                const res = await apiFetch("/periode/aktif");

                if (!res.ok) {
                    throw new Error("Gagal memuat periode aktif.");
                }

                const data = await res.json();

                if (!cancelled) {
                    setPeriode(data.periode || null);

                    if (data.periode) {
                        onLoaded?.(data.periode);
                    }
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err.message || "Gagal memuat periode aktif.");
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

    return (
        <div>
            <div className="border p-2 rounded text-sm bg-gray-50 text-gray-700 min-w-[10rem]">
                {loading
                    ? "Memuat periode..."
                    : periode
                    ? formatPeriodeLabel(periode)
                    : "Belum ada data"}
            </div>

            {error && (
                <p className="text-xs text-red-600 mt-1">{error}</p>
            )}
        </div>
    );
}

export default PeriodeLabel;