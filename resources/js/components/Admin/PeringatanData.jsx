import { useCallback, useEffect, useState } from "react";
import { apiFetch } from "../../lib/api";
import KurangPerInstansi from "./KurangPerInstansi";

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

const TAMPIL_AWAL = 5;

/** Daftar jabatan belum terdaftar + nama pegawainya (lipat/buka per jabatan). */
function DaftarJabatanBaru({ daftar }) {
    const [terbuka, setTerbuka] = useState({});

    if (!daftar?.length) return null;

    return (
        <ul className="mt-2 space-y-2">
            {daftar.map((j) => {
                const semua = terbuka[j.jabatan];
                const tampil = semua ? j.pegawai : j.pegawai.slice(0, TAMPIL_AWAL);
                const sisa = j.pegawai.length - tampil.length;

                return (
                    <li key={j.jabatan} className="bg-white/70 rounded p-3">
                        <p className="font-semibold">
                            {j.jabatan}
                            <span className="ml-1 font-normal">
                                ({Number(j.jumlah).toLocaleString("id-ID")} pegawai)
                            </span>
                        </p>
                        <p className="mt-0.5 text-xs">
                            {tampil
                                .map((p) => (p.unit ? `${p.nama} (${p.unit})` : p.nama))
                                .join(", ")}
                            {sisa > 0 && (
                                <button
                                    type="button"
                                    className="ml-2 underline"
                                    onClick={() =>
                                        setTerbuka((t) => ({ ...t, [j.jabatan]: true }))
                                    }
                                >
                                    lihat {sisa} lainnya
                                </button>
                            )}
                        </p>
                    </li>
                );
            })}
        </ul>
    );
}

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
                        <KurangPerInstansi daftar={p.rincian_instansi} />
                        <DaftarJabatanBaru daftar={p.rincian_jabatan} />
                        {p.penjelasan && (
                            <p className="mt-2 font-medium">{p.penjelasan}</p>
                        )}
                        {p.rincian_gagal?.length > 0 && (
                            <div className="mt-3">
                                <p className="font-semibold">
                                    Yang perlu diperbaiki di file Excel, lalu import ulang:
                                </p>
                                <ul className="mt-2 space-y-2">
                                    {p.rincian_gagal.map((g) => (
                                        <li key={g.jenis} className="bg-white/70 rounded p-3">
                                            <p className="font-semibold">
                                                Kolom {g.kolom}: {g.judul}
                                                <span className="ml-1 font-normal">
                                                    ({Number(g.jumlah).toLocaleString("id-ID")} baris, {g.persen}%)
                                                </span>
                                            </p>
                                            <p className="mt-0.5">
                                                <span className="font-medium">Cara memperbaiki:</span> {g.saran}
                                            </p>
                                            <p className="mt-0.5 text-xs">
                                                Contoh baris:{" "}
                                                {g.contoh
                                                    .map((c) => (c.nama ? `${c.baris} (${c.nama})` : c.baris))
                                                    .join(", ")}
                                                {g.jumlah > g.contoh.length ? ", ..." : ""}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                        {p.tautan?.href && (
                            <a
                                href={p.tautan.href}
                                className="inline-block mt-2 font-semibold underline"
                            >
                                {p.tautan.label}
                            </a>
                        )}
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