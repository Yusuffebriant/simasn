import { useEffect, useState } from "react";
import { LoaderCircle, AlertTriangle } from "lucide-react";
import { apiFetch } from "../../lib/api";

const STATUS_STYLE = {
    selesai: "bg-green-50 text-[#006A4E]",
    gagal: "bg-red-50 text-red-600",
    diproses: "bg-amber-50 text-amber-600",
};

function StatusBadge({ status }) {
    const style = STATUS_STYLE[status] || "bg-gray-100 text-gray-600";
    return (
        <span className={`px-3 py-1 rounded-full text-xs font-semibold ${style}`}>
            {status || "-"}
        </span>
    );
}

function RiwayatImport() {

    const [batches, setBatches] = useState([]);
    const [meta, setMeta] = useState(null);
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError("");

            try {
                const res = await apiFetch(`/imports?page=${page}`);

                if (!res.ok) {
                    throw new Error("Gagal memuat riwayat import.");
                }

                const data = await res.json();

                if (cancelled) return;

                setBatches(data.data || []);
                setMeta(data.meta || null);

            } catch (err) {
                if (!cancelled) setError(err.message);
            } finally {
                if (!cancelled) setLoading(false);
            }
        }

        load();

        return () => { cancelled = true; };
    }, [page]);

    return (
        <div>

            <h2 className="text-2xl font-bold mb-5">
                Riwayat Import
            </h2>

            {loading && (
                <div className="flex items-center gap-3 text-gray-600 bg-white p-6 rounded-xl shadow">
                    <LoaderCircle className="animate-spin" />
                    Memuat riwayat import...
                </div>
            )}

            {!loading && error && (
                <div className="flex items-center gap-3 text-red-700 bg-red-50 p-6 rounded-xl">
                    <AlertTriangle />
                    {error}
                </div>
            )}

            {!loading && !error && (
                <>
                    <div className="overflow-x-auto bg-white rounded-xl shadow">
                        <table className="border-collapse w-full">
                            <thead>
                                <tr>
                                    <th className="border-b p-3 text-left">File</th>
                                    <th className="border-b p-3 text-left">Periode</th>
                                    <th className="border-b p-3 text-left">Status</th>
                                    <th className="border-b p-3 text-left">Berhasil</th>
                                    <th className="border-b p-3 text-left">Gagal</th>
                                    <th className="border-b p-3 text-left">Diupload Oleh</th>
                                    <th className="border-b p-3 text-left">Tanggal</th>
                                </tr>
                            </thead>

                            <tbody>
                                {batches.length === 0 && (
                                    <tr>
                                        <td className="p-3 text-gray-500" colSpan={7}>
                                            Belum ada riwayat import.
                                        </td>
                                    </tr>
                                )}

                                {batches.map((batch) => (
                                    <tr key={batch.id}>
                                        <td className="border-b p-3">{batch.nama_file}</td>
                                        <td className="border-b p-3">{batch.periode}</td>
                                        <td className="border-b p-3">
                                            <StatusBadge status={batch.status} />
                                        </td>
                                        <td className="border-b p-3">{batch.berhasil ?? "-"}</td>
                                        <td className="border-b p-3">{batch.gagal ?? "-"}</td>
                                        <td className="border-b p-3">{batch.diupload_oleh}</td>
                                        <td className="border-b p-3">{batch.dibuat_pada}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {meta && meta.last_page > 1 && (
                        <div className="flex items-center gap-3 mt-4">
                            <button
                                disabled={page <= 1}
                                onClick={() => setPage((p) => p - 1)}
                                className="px-4 py-2 bg-gray-200 rounded disabled:opacity-40"
                            >
                                Sebelumnya
                            </button>

                            <span className="text-sm text-gray-600">
                                Halaman {meta.current_page} dari {meta.last_page}
                            </span>

                            <button
                                disabled={page >= meta.last_page}
                                onClick={() => setPage((p) => p + 1)}
                                className="px-4 py-2 bg-gray-200 rounded disabled:opacity-40"
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

export default RiwayatImport;
