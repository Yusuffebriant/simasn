import { useEffect, useState } from "react";
import { LoaderCircle, AlertTriangle, Info, Search } from "lucide-react";
import { apiFetch } from "../../lib/api";
import ExportExcelButton from "../../pages/Statistik/components/ExportExcelButton";

// Catatan perubahan pegawai dibanding import sebelumnya:
// ringkasan per jenis (pensiun, naik pangkat, pegawai baru, ...) + daftar nama.
// Sumber: GET /imports/{batch}/perubahan  dan  /imports/{batch}/perubahan/export
function bulanTeks(periode) {
    if (!periode || !/^\d{4}-\d{2}$/.test(periode)) return "";
    const [tahun, bulan] = periode.split("-").map(Number);
    return new Date(tahun, bulan - 1, 1).toLocaleDateString("id-ID", {
        month: "long",
        year: "numeric",
    });
}

function PerubahanPegawai({ batchId }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [jenis, setJenis] = useState("");
    const [cari, setCari] = useState("");
    const [q, setQ] = useState("");
    const [page, setPage] = useState(1);

    // Pencarian nama: tunda sebentar supaya tidak request di tiap ketukan.
    useEffect(() => {
        const t = setTimeout(() => {
            setQ(cari.trim());
            setPage(1);
        }, 400);
        return () => clearTimeout(t);
    }, [cari]);

    useEffect(() => {
        if (!batchId) return;
        let batal = false;

        async function muat() {
            setLoading(true);
            setError("");
            try {
                const params = new URLSearchParams({ page });
                if (jenis) params.set("jenis", jenis);
                if (q) params.set("q", q);

                const res = await apiFetch(`/imports/${batchId}/perubahan?${params}`);
                if (!res.ok) throw new Error("Gagal memuat catatan perubahan pegawai.");
                const json = await res.json();
                if (!batal) setData(json);
            } catch (err) {
                if (!batal) setError(err.message);
            } finally {
                if (!batal) setLoading(false);
            }
        }

        muat();
        return () => { batal = true; };
    }, [batchId, jenis, q, page]);

    if (error) {
        return (
            <div className="flex items-center gap-3 text-red-700 bg-red-50 p-4 rounded-lg text-sm">
                <AlertTriangle size={18} />
                {error}
            </div>
        );
    }

    if (!data) {
        return (
            <div className="flex items-center gap-3 text-gray-600 text-sm">
                <LoaderCircle className="animate-spin" size={18} />
                Memuat catatan perubahan...
            </div>
        );
    }

    if (data.status !== "selesai") {
        const pesan = {
            tanpa_pembanding:
                "Ini import pertama (belum ada data sebelumnya), jadi tidak ada yang bisa dibandingkan. Perubahan mulai tercatat dari import berikutnya.",
            gagal:
                "Catatan perubahan gagal dibuat untuk import ini. Data pegawai tetap tersimpan normal.",
        }[data.status] ||
            "Import ini dilakukan sebelum fitur catatan perubahan ada, jadi tidak ada catatannya.";

        return (
            <div className="flex items-start gap-3 p-4 rounded-lg bg-gray-50 text-gray-700 text-sm">
                <Info size={18} className="mt-0.5 shrink-0" />
                {pesan}
            </div>
        );
    }

    const rows = data.daftar?.data ?? [];
    const lastPage = data.daftar?.last_page ?? 1;
    const aktif = data.ringkasan.filter((r) => r.jumlah > 0);

    return (
        <div>
            <div className="flex flex-wrap items-start justify-between gap-3 mb-3">
                <div>
                    <h3 className="text-lg font-bold">Perubahan Pegawai</h3>
                    <p className="text-sm text-gray-500">
                        {bulanTeks(data.pembanding?.periode)
                            ? `Data ${bulanTeks(data.pembanding.periode)} \u2192 ${bulanTeks(data.periode)}`
                            : `Data ${bulanTeks(data.periode) || "-"}`}
                        {" "}&middot; {data.total.toLocaleString("id-ID")} perubahan tercatat
                    </p>
                </div>
                <ExportExcelButton
                    path={`/imports/${batchId}/perubahan/export`}
                    filename={`perubahan-pegawai-${data.periode || batchId}.xlsx`}
                    errorMessage="Gagal mengunduh daftar perubahan."
                    onError={(m) => m && setError(m)}
                    disabled={data.total === 0}
                />
            </div>

            {data.total === 0 ? (
                <p className="text-sm text-gray-600 p-4 bg-gray-50 rounded-lg">
                    Tidak ada perubahan: data pegawai sama dengan import sebelumnya.
                </p>
            ) : (
                <>
                    <div className="flex flex-wrap gap-2 mb-4">
                        <button
                            onClick={() => { setJenis(""); setPage(1); }}
                            className={`px-3 py-1 rounded-full border text-xs font-semibold ${jenis === "" ? "bg-[#006A4E] text-white border-[#006A4E]" : "bg-white hover:bg-gray-100"}`}
                        >
                            Semua: {data.total.toLocaleString("id-ID")}
                        </button>
                        {aktif.map((r) => (
                            <button
                                key={r.jenis}
                                onClick={() => { setJenis(r.jenis); setPage(1); }}
                                className={`px-3 py-1 rounded-full border text-xs font-semibold ${jenis === r.jenis ? "bg-[#006A4E] text-white border-[#006A4E]" : "bg-white hover:bg-gray-100"}`}
                            >
                                {r.label}: {r.jumlah.toLocaleString("id-ID")}
                            </button>
                        ))}
                    </div>

                    <div className="relative mb-3 max-w-sm">
                        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input
                            value={cari}
                            onChange={(e) => setCari(e.target.value)}
                            placeholder="Cari nama atau NIP"
                            className="w-full pl-9 pr-3 py-2 border rounded-lg text-sm"
                        />
                    </div>

                    <div className={`overflow-x-auto border rounded-lg ${loading ? "opacity-60" : ""}`}>
                        <table className="w-full text-sm border-collapse">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="p-2 text-left">Jenis</th>
                                    <th className="p-2 text-left">Nama</th>
                                    <th className="p-2 text-left">NIP</th>
                                    <th className="p-2 text-left">Unit</th>
                                    <th className="p-2 text-left">Dari</th>
                                    <th className="p-2 text-left">Menjadi</th>
                                    <th className="p-2 text-left">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="p-3 text-gray-500">
                                            Tidak ada data yang cocok.
                                        </td>
                                    </tr>
                                )}
                                {rows.map((r, i) => (
                                    <tr key={`${r.nip}-${r.jenis}-${i}`} className="border-t align-top">
                                        <td className="p-2 whitespace-nowrap font-semibold">{r.jenis_label}</td>
                                        <td className="p-2">{r.nama}</td>
                                        <td className="p-2 whitespace-nowrap">{r.nip || "-"}</td>
                                        <td className="p-2">{r.unit || "-"}</td>
                                        <td className="p-2">{r.dari || "-"}</td>
                                        <td className="p-2">{r.ke || "-"}</td>
                                        <td className="p-2 text-gray-600">{r.keterangan || ""}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {lastPage > 1 && (
                        <div className="flex items-center gap-3 mt-3">
                            <button
                                disabled={page <= 1}
                                onClick={() => setPage((p) => p - 1)}
                                className="px-3 py-1.5 bg-gray-200 rounded text-sm disabled:opacity-40"
                            >
                                Sebelumnya
                            </button>
                            <span className="text-xs text-gray-600">
                                Halaman {page} dari {lastPage}
                            </span>
                            <button
                                disabled={page >= lastPage}
                                onClick={() => setPage((p) => p + 1)}
                                className="px-3 py-1.5 bg-gray-200 rounded text-sm disabled:opacity-40"
                            >
                                Berikutnya
                            </button>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

export default PerubahanPegawai;