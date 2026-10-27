import { useEffect, useState } from "react";
import { LoaderCircle, AlertTriangle, X, Eye, Trash2, Users, Copy } from "lucide-react";
import { apiFetch } from "../../lib/api";
import PerubahanPegawai from "../../components/Import/PerubahanPegawai";

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
                                                    {row.data_mentah?.NAMA ? ` – ${row.data_mentah.NAMA}` : ""}
                                                </span>
                                            </div>
                                            <p className="text-sm text-red-700 mb-1">
                                                <span className="font-medium">Penyebab:</span>{" "}
                                                {row.penyebab ?? row.pesan}
                                            </p>
                                            {row.saran && (
                                                <p className="text-sm text-gray-700 mb-2">
                                                    <span className="font-medium">Cara memperbaiki:</span>{" "}
                                                    {row.saran}
                                                </p>
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

// Dialog konfirmasi hapus import.
// Sumber: GET /imports/{batch_id} (ringkasan "akan_dihapus") dan
// DELETE /imports/{batch_id}. Yang dihapus SEMUA data dari import ini:
// riwayat, baris gagal, catatan perubahan, data pegawai hasil import,
// rekap beku periodenya, dan file cadangan. Permanen.
function DeleteBatchModal({ batch, onClose, onDeleted }) {
    const [deleting, setDeleting] = useState(false);
    const [error, setError] = useState("");
    const [ringkasan, setRingkasan] = useState(null);
    const [konfirmasi, setKonfirmasi] = useState("");

    // Import yang masih "diproses" (macet / worker mati) dihapus paksa.
    const paksa = batch.status === "diproses";

    // Muat ringkasan apa saja yang akan terhapus.
    useEffect(() => {
        let batal = false;

        apiFetch(`/imports/${batch.id}`)
            .then((res) => (res.ok ? res.json() : null))
            .then((json) => {
                if (!batal) {
                    setRingkasan(
                        json?.akan_dihapus ?? { jumlah_pegawai: null, jumlah_perubahan: null }
                    );
                }
            })
            .catch(() => {
                if (!batal) setRingkasan({ jumlah_pegawai: null, jumlah_perubahan: null });
            });

        return () => {
            batal = true;
        };
    }, [batch.id]);

    // Kalau ada data pegawai yang ikut terhapus (atau jumlahnya tidak
    // diketahui), wajib ketik HAPUS dulu.
    const jumlahPegawai = ringkasan?.jumlah_pegawai;
    const perluKonfirmasi = jumlahPegawai !== 0;
    const siapHapus =
        ringkasan !== null && (!perluKonfirmasi || konfirmasi.trim().toUpperCase() === "HAPUS");

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
            const res = await apiFetch(
                `/imports/${batch.id}${paksa ? "?paksa=1" : ""}`,
                { method: "DELETE" }
            );

            if (!res.ok) {
                let message = "Gagal menghapus import.";
                if (res.status === 403) {
                    message = "Anda tidak berhak menghapus import ini.";
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
                className="hb-card w-full max-w-md max-h-[90vh] overflow-y-auto rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-200"
            >
                <div className="flex items-start justify-between gap-4">
                    <h3 id="hb-title" className="text-lg font-semibold text-gray-900">
                        Hapus import beserta semua datanya?
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
                    (periode {batch.periode}) akan dihapus permanen beserta:
                </p>

                {ringkasan === null ? (
                    <div className="mt-3 flex items-center gap-2 text-sm text-gray-500">
                        <LoaderCircle className="animate-spin" size={16} />
                        Menghitung data yang terkait...
                    </div>
                ) : (
                    <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-700">
                        <li>
                            <span className="font-semibold">
                                {jumlahPegawai === null
                                    ? "Data pegawai"
                                    : `${jumlahPegawai.toLocaleString("id-ID")} data pegawai`}
                            </span>{" "}
                            hasil import ini (termasuk detail dan log aktivitasnya)
                        </li>
                        <li>Catatan perubahan pegawai dan baris yang gagal</li>
                        <li>Rekap beku periode {batch.periode} (jika tidak ada import lain di periode yang sama)</li>
                        <li>File cadangan dan file upload import ini</li>
                    </ul>
                )}

                <p className="mt-3 text-sm font-medium text-red-700">
                    Tindakan ini tidak bisa dibatalkan.
                </p>

                {paksa && !error && (
                    <div className="mt-4 flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                        <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                        Import ini masih berstatus diproses. Jika worker tidak
                        berjalan atau proses macet, prosesnya dibatalkan dan
                        semuanya dihapus paksa.
                    </div>
                )}

                {ringkasan !== null && perluKonfirmasi && (
                    <div className="mt-4">
                        <label className="block text-sm text-gray-700" htmlFor="hb-konfirmasi">
                            Ketik <span className="font-semibold">HAPUS</span> untuk melanjutkan
                        </label>
                        <input
                            id="hb-konfirmasi"
                            value={konfirmasi}
                            onChange={(e) => setKonfirmasi(e.target.value)}
                            disabled={deleting}
                            autoComplete="off"
                            className="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                        />
                    </div>
                )}

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
                        disabled={deleting || !siapHapus}
                        className="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700 disabled:opacity-50"
                    >
                        {deleting && <LoaderCircle className="animate-spin" size={16} />}
                        {deleting ? "Menghapus..." : "Hapus semua"}
                    </button>
                </div>
            </div>
        </div>
    );
}

// Panel catatan perubahan pegawai untuk satu batch import.
function PerubahanModal({ batch, onClose }) {
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-5xl max-h-[88vh] flex flex-col">
                <div className="flex items-center justify-between p-5 border-b">
                    <p className="text-sm text-gray-500">
                        {batch.nama_file} · Periode {batch.periode}
                    </p>
                    <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-lg">
                        <X size={20} />
                    </button>
                </div>
                <div className="overflow-y-auto p-5 flex-1">
                    <PerubahanPegawai batchId={batch.id} />
                </div>
            </div>
        </div>
    );
}

// Panel daftar lengkap baris ganda (NIP/NIK sama sehingga saling menimpa).
// Sumber: GET /imports/{batch_id}/ganda. Baris "NIK sama, nama berbeda"
// (pegawai hilang) ditampilkan paling atas dengan warna merah.
function GandaModal({ batch, onClose }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    useEffect(() => {
        let batal = false;

        async function load() {
            try {
                const res = await apiFetch(`/imports/${batch.id}/ganda`);

                if (res.status === 403) {
                    throw new Error("Anda tidak berhak melihat data import ini.");
                }
                if (!res.ok) {
                    throw new Error("Gagal memuat daftar baris ganda.");
                }

                const json = await res.json();
                if (!batal) setData(json);
            } catch (err) {
                if (!batal) setError(err.message);
            } finally {
                if (!batal) setLoading(false);
            }
        }

        load();

        return () => { batal = true; };
    }, [batch.id]);

    const daftar = data?.daftar ?? [];

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-5xl max-h-[88vh] flex flex-col">
                <div className="flex items-center justify-between p-5 border-b">
                    <div>
                        <h3 className="text-lg font-bold">Baris Ganda di File</h3>
                        <p className="text-sm text-gray-500">
                            {batch.nama_file} · Periode {batch.periode}
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-lg">
                            <X size={20} />
                        </button>
                    </div>
                </div>

                <div className="overflow-y-auto p-5 flex-1">
                    {loading && (
                        <div className="flex items-center gap-3 text-gray-600">
                            <LoaderCircle className="animate-spin" size={18} />
                            Memuat daftar...
                        </div>
                    )}

                    {!loading && error && (
                        <div className="flex items-center gap-3 text-red-700 bg-red-50 p-4 rounded-lg text-sm">
                            <AlertTriangle size={18} />
                            {error}
                        </div>
                    )}

                    {!loading && !error && data && (
                        <>
                            {!data.tersedia && (
                                <div className="flex items-start gap-3 text-amber-800 bg-amber-50 p-4 rounded-lg text-sm mb-4">
                                    <AlertTriangle size={18} className="mt-0.5 shrink-0" />
                                    <p>
                                        Import ini dibuat sebelum nama pegawai yang tertimpa dicatat,
                                        jadi daftar di bawah belum menyebut siapa yang hilang.
                                        Upload ulang file yang sama untuk mendapatkan daftar lengkap.
                                    </p>
                                </div>
                            )}

                            {data.nik_beda_nama > 0 && (
                                <div className="flex items-start gap-3 text-red-800 bg-red-50 border border-red-200 p-4 rounded-lg text-sm mb-4">
                                    <AlertTriangle size={18} className="mt-0.5 shrink-0" />
                                    <p>
                                        <b>{data.nik_beda_nama} pegawai hilang karena NIK sama dengan nama berbeda.</b>{" "}
                                        Periksa NIK-nya di file Excel, lalu import ulang.
                                    </p>
                                </div>
                            )}

                            {daftar.length === 0 ? (
                                <p className="text-gray-500 text-sm">
                                    Tidak ada baris ganda tercatat untuk import ini.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm border-collapse">
                                        <thead>
                                            <tr className="text-left">
                                                <th className="border-b p-2">Temuan</th>
                                                <th className="border-b p-2">Yang hilang (tertimpa)</th>
                                                <th className="border-b p-2">Yang tersimpan (menimpa)</th>
                                                <th className="border-b p-2">NIK</th>
                                                <th className="border-b p-2">Sebab</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {daftar.map((g, i) => (
                                                <tr
                                                    key={i}
                                                    className={g.nik_nama_beda ? "bg-red-50" : ""}
                                                >
                                                    <td className="border-b p-2 align-top">
                                                        {g.nik_nama_beda ? (
                                                            <span className="font-semibold text-red-700">
                                                                NIK sama, nama berbeda
                                                            </span>
                                                        ) : (
                                                            <span className="text-gray-600">Ganda, nama sama</span>
                                                        )}
                                                    </td>
                                                    <td className="border-b p-2 align-top">
                                                        <div className="font-medium">
                                                            {g.nama_sebelumnya || "(tidak tercatat)"}
                                                        </div>
                                                        <div className="text-xs text-gray-500">
                                                            Baris {g.baris_sebelumnya}
                                                            {g.nip_sebelumnya ? ` · NIP ${g.nip_sebelumnya}` : ""}
                                                        </div>
                                                    </td>
                                                    <td className="border-b p-2 align-top">
                                                        <div className="font-medium">{g.nama}</div>
                                                        <div className="text-xs text-gray-500">
                                                            Baris {g.baris}
                                                            {g.nip ? ` · NIP ${g.nip}` : ""}
                                                        </div>
                                                    </td>
                                                    <td className="border-b p-2 align-top font-mono text-xs">
                                                        {g.nik || "-"}
                                                    </td>
                                                    <td className="border-b p-2 align-top text-xs text-gray-600">
                                                        {g.alasan}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
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
    const [deleteBatch, setDeleteBatch] = useState(null);
    const [perubahanBatch, setPerubahanBatch] = useState(null);
    const [gandaBatch, setGandaBatch] = useState(null);
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
                                            {batch.rekonsiliasi_status === "selisih" && (
                                                <p className="mt-1 text-xs text-amber-600">
                                                    Ada selisih file vs hasil import
                                                </p>
                                            )}
                                            {batch.ganda_nik_beda_nama > 0 && (
                                                <p className="mt-1 text-xs font-semibold text-red-600">
                                                    {batch.ganda_nik_beda_nama} pegawai hilang: NIK sama, nama berbeda
                                                </p>
                                            )}
                                            {batch.rekonsiliasi_status === "cocok" && (
                                                <p className="mt-1 text-xs text-[#006A4E]">
                                                    Data cocok dengan file
                                                </p>
                                            )}
                                            {batch.sinkron_status === "dilewati" && (
                                                <p
                                                    className="mt-1 text-xs text-amber-600"
                                                    title={batch.sinkron_catatan || ""}
                                                >
                                                    Sinkron dilewati
                                                </p>
                                            )}
                                            {batch.sinkron_status === "dinonaktifkan" && (
                                                <p
                                                    className="mt-1 text-xs text-gray-500"
                                                    title={batch.sinkron_catatan || ""}
                                                >
                                                    {batch.sinkron_jumlah_sisa} dinonaktifkan
                                                </p>
                                            )}
                                        </td>
                                        <td className="border-b p-3">{batch.berhasil ?? "-"}</td>
                                        <td className="border-b p-3">{batch.gagal ?? "-"}</td>
                                        <td className="border-b p-3">{batch.diupload_oleh}</td>
                                        <td className="border-b p-3">{batch.dibuat_pada}</td>
                                        <td className="border-b p-3 text-center">
                                            {batch.status === "selesai" && (
                                                <button
                                                    onClick={() => setPerubahanBatch(batch)}
                                                    className="flex items-center gap-1 text-sm text-[#006A4E] font-semibold hover:underline mx-auto mb-1"
                                                >
                                                    <Users size={16} />
                                                    Lihat Perubahan
                                                </button>
                                            )}
                                            {batch.status === "selesai" && batch.ganda_dalam_file > 0 && (
                                                <button
                                                    onClick={() => setGandaBatch(batch)}
                                                    className={`flex items-center gap-1 text-sm font-semibold hover:underline mx-auto mb-1 ${batch.ganda_nik_beda_nama > 0 ? "text-red-600" : "text-[#006A4E]"}`}
                                                >
                                                    <Copy size={16} />
                                                    Lihat Ganda
                                                </button>
                                            )}
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

            {perubahanBatch && (
                <PerubahanModal
                    batch={perubahanBatch}
                    onClose={() => setPerubahanBatch(null)}
                />
            )}

            {gandaBatch && (
                <GandaModal
                    batch={gandaBatch}
                    onClose={() => setGandaBatch(null)}
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