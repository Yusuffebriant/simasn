import { useEffect, useState } from "react";
import { LoaderCircle, AlertTriangle, X, Eye, Trash2 } from "lucide-react";
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

// Dialog konfirmasi hapus riwayat import.
// Sumber: DELETE /imports/{batch_id}. Yang dihapus hanya catatan riwayat
// (dan detail baris gagalnya) — data pegawai & angka rekap tidak berubah.
function DeleteBatchModal({ batch, onClose, onDeleted }) {
    const [deleting, setDeleting] = useState(false);
    const [error, setError] = useState("");

    // Esc = batal (kecuali sedang proses hapus)
    useEffect(() => {
        function onKey(e) {
            if (e.key === "Escape" && !deleting) onClose();
        }
        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, [deleting, onClose]);

    async function handleDelete() {
        setDeleting(true);
        setError("");

        try {
            const res = await apiFetch(`/imports/${batch.id}`, { method: "DELETE" });

            if (!res.ok) {
                let message = "Gagal menghapus riwayat import.";
                if (res.status === 403) {
                    message = "Anda tidak berhak menghapus riwayat import ini.";
                } else {
                    try {
                        const data = await res.json();
                        if (data?.message) message = data.message;
                    } catch {
                        // abaikan, pakai pesan default
                    }
                }
                throw new Error(message);
            }

            onDeleted();
        } catch (err) {
            setError(err.message);
            setDeleting(false);
        }
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4"
            onMouseDown={(e) => {
                if (e.target === e.currentTarget && !deleting) onClose();
            }}
        >
            <style>{`
                @media (prefers-reduced-motion: no-preference) {
                    .hb-card { animation: hb-in .16s ease-out both; }
                }
                @keyframes hb-in {
                    from { opacity: 0; transform: translateY(8px) scale(.98) }
                    to   { opacity: 1; transform: none }
                }
            `}</style>

            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="hb-title"
                className="hb-card w-full max-w-md rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200"
            >
                <div className="flex items-start justify-between gap-4">
                    <h3 id="hb-title" className="text-lg font-semibold text-gray-900">
                        Hapus riwayat import?
                    </h3>
                    <button
                        onClick={onClose}
                        disabled={deleting}
                        aria-label="Tutup"
                        className="-mr-2 -mt-1 rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 disabled:opacity-40"
                    >
                        <X size={18} />
                    </button>
                </div>

                <p className="mt-2 text-sm leading-relaxed text-gray-600">
                    <span className="font-medium text-gray-900">{batch.nama_file}</span>{" "}
                    (periode {batch.periode}) akan dihapus dari riwayat. Data pegawai
                    tidak terpengaruh.
                </p>

                {error && (
                    <div
                        role="alert"
                        className="mt-4 flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                    >
                        <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                        {error}
                    </div>
                )}

                <div className="mt-6 flex justify-end gap-3">
                    <button
                        autoFocus
                        onClick={onClose}
                        disabled={deleting}
                        className="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 disabled:opacity-50"
                    >
                        Batal
                    </button>
                    <button
                        onClick={handleDelete}
                        disabled={deleting}
                        className="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700 disabled:opacity-70"
                    >
                        {deleting && <LoaderCircle className="animate-spin" size={16} />}
                        {deleting ? "Menghapus..." : "Hapus"}
                    </button>
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
    const [deleteBatch, setDeleteBatch] = useState(null);
    const [reloadKey, setReloadKey] = useState(0);

    // Dipanggil setelah hapus berhasil: muat ulang daftar, dan kalau halaman
    // ini jadi kosong (menghapus baris terakhir di halaman), mundur satu halaman.
    function handleDeleted() {
        setDeleteBatch(null);
        if (batches.length === 1 && page > 1) {
            setPage((p) => p - 1);
        } else {
            setReloadKey((k) => k + 1);
        }
    }

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
    }, [page, reloadKey]);

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
                                    <th className="border-b p-3 text-center">Keterangan</th>
                                    <th className="border-b p-3 text-center">Aksi</th>
                                </tr>
                            </thead>

                            <tbody>
                                {batches.length === 0 && (
                                    <tr>
                                        <td className="p-3 text-gray-500" colSpan={9}>
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
                                        <td className="border-b p-3 text-center">
                                            {(batch.gagal ?? 0) > 0 ? (
                                                <button
                                                    onClick={() => setDetailBatch(batch)}
                                                    className="flex items-center gap-1 text-sm text-[#006A4E] font-semibold hover:underline mx-auto"
                                                >
                                                    <Eye size={16} />
                                                    Lihat Detail
                                                </button>
                                            ) : (
                                                <span className="text-gray-400">-</span>
                                            )}
                                        </td>
                                        <td className="border-b p-3 text-center">
                                            <button
                                                onClick={() => setDeleteBatch(batch)}
                                                className="flex items-center gap-1 text-sm text-red-600 font-semibold hover:underline mx-auto"
                                            >
                                                <Trash2 size={16} />
                                                Hapus
                                            </button>
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

            {deleteBatch && (
                <DeleteBatchModal
                    batch={deleteBatch}
                    onClose={() => setDeleteBatch(null)}
                    onDeleted={handleDeleted}
                />
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