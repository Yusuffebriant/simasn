import { useCallback, useEffect, useState } from "react";
import { apiFetch } from "../../lib/api";

const GAYA = {
    bahaya: {
        kotak: "border-red-300 bg-red-50 text-red-800",
        label: "Perlu tindakan",
    },
    peringatan: {
        kotak: "border-amber-300 bg-amber-50 text-amber-800",
        label: "Periksa",
    },
    info: {
        kotak: "border-blue-200 bg-blue-50 text-blue-800",
        label: "Info",
    },
};

/**
 * Banner kesehatan data di halaman Admin.
 *
 * Membaca GET /peringatan-data, yaitu pemeriksaan kondisi data pegawai
 * SAAT INI (sisa data import lama, kemungkinan data dobel, baris gagal di
 * import terakhir, rekap yang belum dibekukan). Dimuat ulang tiap kali
 * tab Admin berganti, jadi setelah import selesai dan pindah tab, hasilnya
 * ikut diperbarui.
 */
function PeringatanData({ refreshKey }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    const muat = useCallback(async () => {
        setLoading(true);
        setError("");

        try {
            const res = await apiFetch("/peringatan-data");

            if (!res.ok) {
                throw new Error("Gagal memeriksa kondisi data.");
            }

            setData(await res.json());
        } catch (err) {
            setError(err.message || "Gagal memeriksa kondisi data.");
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        muat();
    }, [muat, refreshKey]);

    if (error) {
        return <p className="mb-4 text-xs text-gray-500">{error}</p>;
    }

    if (!data) {
        return loading ? (
            <p className="mb-4 text-xs text-gray-400">Memeriksa kondisi data...</p>
        ) : null;
    }

    const daftar = data.peringatan || [];

    if (data.ok && daftar.length === 0) {
        return (
            <div className="mb-4 flex items-center gap-3 text-xs text-green-700">
                <span>✓ Data konsisten (tidak ada masalah terdeteksi).</span>
                <button
                    type="button"
                    onClick={muat}
                    disabled={loading}
                    className="underline disabled:opacity-50"
                >
                    Periksa ulang
                </button>
            </div>
        );
    }

    return (
        <div className="mb-4 space-y-2" role="alert">
            {daftar.map((p) => {
                const gaya = GAYA[p.tingkat] || GAYA.info;

                return (
                    <div
                        key={p.kode}
                        className={`border rounded p-3 text-sm ${gaya.kotak}`}
                    >
                        <p className="font-semibold">
                            <span className="text-xs uppercase mr-2 opacity-80">
                                {gaya.label}
                            </span>
                            {p.judul}
                            {p.jumlah != null && (
                                <span className="ml-2 font-normal">
                                    ({Number(p.jumlah).toLocaleString("id-ID")})
                                </span>
                            )}
                        </p>
                        <p className="mt-1">{p.pesan}</p>
                    </div>
                );
            })}

            <button
                type="button"
                onClick={muat}
                disabled={loading}
                className="text-xs text-gray-600 underline disabled:opacity-50"
            >
                {loading ? "Memeriksa..." : "Periksa ulang"}
            </button>
        </div>
    );
}

export default PeringatanData;