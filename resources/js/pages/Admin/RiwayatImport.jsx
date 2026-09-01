import { useEffect, useState } from "react";
import { LoaderCircle, AlertTriangle, X, Eye } from "lucide-react";
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

// Panel detail baris yang gagal untuk satu batch import.
// Sumber: GET /imports/{batch_id}/errors (paginated, 50/halaman).
// Hanya bisa diakses oleh yang upload atau super-admin — kalau 403,
// tampilkan pesan "tidak berhak" (lihat dokumentasi bagian 3 & 8).
function ImportErrorsModal({ batch, onClose }) {
    const [rows, setRows] = useState([]);
    const [meta, setMeta] = useState(null);
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [forbidden, setForbidden] = useState(false);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError("");
            setForbidden(false);

            try {
                const res = await apiFetch(`/imports/${batch.id}/errors?page=${page}`);

                if (res.status === 403) {
                    if (!cancelled) setForbidden(true);
                    return;
                }

                if (!res.ok) {
                    throw new Error("Gagal memuat detail error.");
                }

                const data = await res.json();
                if (cancelled) return;

                setRows(data.data || []);
                setMeta(data.meta || null);

            } catch (err) {
                if (!cancelled) setError(err.message);
            } finally {
                if (!cancelled) setLoading(false);
            }
        }

        load();

        return () => { cancelled = true; };
    }, [batch.id, page]);

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[85vh] flex flex-col">

                <div className="flex items-center justify-between p-5 border-b">
                    <div>
                        <h3 className="text-lg font-bold">Detail Baris Gagal</h3>
                        <p className="text-sm text-gray-500">
                            {batch.nama_file} · Periode {batch.periode}
                        </p>
                    </div>
                    <button
                        onClick={onClose}
                        className="p-2 hover:bg-gray-100 rounded-lg"
                    >
                        <X size={20} />
                    </button>
                </div>

                <div className="overflow-y-auto p-5 flex-1">
                    {loading && (
                        <div className="flex items-center gap-3 text-gray-600">
                            <LoaderCircle className="animate-spin" size={18} />
                            Memuat detail...
                        </div>
                    )}

                    {!loading && forbidden && (
                        <div className="flex items-center gap-3 text-red-700 bg-red-50 p-4 rounded-lg text-sm">
                            <AlertTriangle size={18} />
                            Anda tidak berhak melihat detail batch ini. Hanya
                            pengunggah file atau super-admin yang bisa mengakses.
                        </div>
                    )}

                    {!loading && !forbidden && error && (
                        <div className="flex items-center gap-3 text-red-700 bg-red-50 p-4 rounded-lg text-sm">
                            <AlertTriangle size={18} />
                            {error}
                        </div>
                    )}

                    {!loading && !forbidden && !error && (
                        <>
                            {rows.length === 0 ? (
                                <p className="text-gray-500 text-sm">
                                    Tidak ada baris gagal tercatat untuk batch ini.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {rows.map((row, idx) => (
                                        <div
                                            key={idx}
                                            className="border border-red-100 bg-red-50/50 rounded-lg p-4"
                                        >
                                            <div className="flex items-center justify-between mb-2">
                                                <span className="font-semibold text-sm">
                                                    Baris ke-{row.baris_ke}
                                                </span>
                                            </div>
                                            <p className="text-sm text-red-700 mb-2">
                                                {row.pesan}
                                            </p>
                                            {row.data_mentah && (
                                                <div className="text-xs text-gray-600 bg-white rounded p-2 overflow-x-auto">
                                                    {Object.entries(row.data_mentah)
                                                        .filter(([, v]) => v !== null && v !== "")
                                                        .map(([key, value]) => (
                                                            <span key={key} className="inline-block mr-4">
                                                                <span className="font-medium">{key}:</span>{" "}
                                                                {String(value)}
                                                            </span>
                                                        ))}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            )}

                            {meta && meta.last_page > 1 && (
                                <div className="flex items-center gap-3 mt-5">
                                    <button
                                        disabled={page <= 1}
                                        onClick={() => setPage((p) => p - 1)}
                                        className="px-3 py-1.5 bg-gray-200 rounded text-sm disabled:opacity-40"
                                    >
                                        Sebelumnya
                                    </button>
                                    <span className="text-xs text-gray-600">
                                        Halaman {meta.current_page} dari {meta.last_page}
                                    </span>
                                    <button
                                        disabled={page >= meta.last_page}
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
            </div>
        </div>
    );
}

function RiwayatImport() {

    const [batches, setBatches] = useState([]);
    const [meta, setMeta] = useState(null);
    const [page, setPage] = useState(1);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [detailBatch, setDetailBatch] = useState(null);

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
                                    <th className="border-b p-3 text-left"></th>
                                </tr>
                            </thead>

                            <tbody>
                                {batches.length === 0 && (
                                    <tr>
                                        <td className="p-3 text-gray-500" colSpan={8}>
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
                                        <td className="border-b p-3">
                                            {(batch.gagal ?? 0) > 0 && (
                                                <button
                                                    onClick={() => setDetailBatch(batch)}
                                                    className="flex items-center gap-1 text-sm text-[#006A4E] font-semibold hover:underline"
                                                >
                                                    <Eye size={16} />
                                                    Lihat Detail
                                                </button>
                                            )}
                                        </td>
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

            {detailBatch && (
                <ImportErrorsModal
                    batch={detailBatch}
                    onClose={() => setDetailBatch(null)}
                />
            )}

        </div>
    );
}

export default RiwayatImport;