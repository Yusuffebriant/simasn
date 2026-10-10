import { useEffect, useState } from "react";
import {
    LoaderCircle,
    Search,
    ChevronLeft,
    ChevronRight,
    Pencil,
    Trash2,
    X,
} from "lucide-react";
import { apiFetch } from "../../lib/api";

// Nama pangkat untuk golongan PNS. Golongan PPPK (IX, X, dst.) tidak
// punya nama pangkat, jadi ditampilkan sebagai "Gol. IX".
const PANGKAT_PNS = {
    "I/a": "Juru Muda",
    "I/b": "Juru Muda Tk. I",
    "I/c": "Juru",
    "I/d": "Juru Tk. I",
    "II/a": "Pengatur Muda",
    "II/b": "Pengatur Muda Tk. I",
    "II/c": "Pengatur",
    "II/d": "Pengatur Tk. I",
    "III/a": "Penata Muda",
    "III/b": "Penata Muda Tk. I",
    "III/c": "Penata",
    "III/d": "Penata Tk. I",
    "IV/a": "Pembina",
    "IV/b": "Pembina Tk. I",
    "IV/c": "Pembina Utama Muda",
    "IV/d": "Pembina Utama Madya",
    "IV/e": "Pembina Utama",
};

const STATUS_PERKAWINAN = {
    KAWIN: { label: "Kawin", style: "bg-green-50 text-[#006A4E]" },
    BELUM_KAWIN: { label: "Belum Kawin", style: "bg-blue-50 text-blue-600" },
    CERAI_HIDUP: { label: "Cerai Hidup", style: "bg-amber-50 text-amber-600" },
    CERAI_MATI: { label: "Cerai Mati", style: "bg-gray-100 text-gray-600" },
    JANDA: { label: "Janda", style: "bg-gray-100 text-gray-600" },
    DUDA: { label: "Duda", style: "bg-gray-100 text-gray-600" },
};

const STATUS_KEPEGAWAIAN = {
    PNS: "PNS",
    PPPK: "PPPK",
    PPPK_PW: "PPPK Paruh Waktu",
    LAINNYA: "Lainnya",
};

const KEPEGAWAIAN_STYLE = {
    PNS: "bg-green-50 text-[#006A4E]",
    PPPK: "bg-blue-50 text-blue-600",
    PPPK_PW: "bg-amber-50 text-amber-600",
    LAINNYA: "bg-gray-100 text-gray-600",
};

function labelPangkat(golongan) {
    if (!golongan) return "-";
    const nama = PANGKAT_PNS[golongan];
    return nama ? `${nama} (${golongan})` : `Gol. ${golongan}`;
}

function parseTanggal(str) {
    const [y, m, d] = str.split("-").map(Number);
    return new Date(y, m - 1, d);
}

function formatTanggal(str) {
    return parseTanggal(str).toLocaleDateString("id-ID", {
        day: "numeric",
        month: "short",
        year: "numeric",
    });
}

// "lewat"  : tanggal sudah terlewati (merah)
// "segera" : jatuh dalam 12 bulan ke depan (kuning)
// null     : masih jauh (tampil biasa)
function statusTanggal(str) {
    const now = new Date();
    const hariIni = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const batas = new Date(now.getFullYear() + 1, now.getMonth(), now.getDate());
    const tanggal = parseTanggal(str);

    if (tanggal < hariIni) return "lewat";
    if (tanggal <= batas) return "segera";
    return null;
}

function TanggalAkhirCell({ tanggal, jenis }) {
    if (!tanggal) {
        return <span className="text-gray-400">-</span>;
    }

    const label =
        jenis === "PENSIUN" ? "Pensiun" : jenis === "KONTRAK" ? "Kontrak berakhir" : "Tanggal akhir";

    const status = statusTanggal(tanggal);
    const gaya =
        status === "lewat"
            ? "font-semibold text-red-600"
            : status === "segera"
                ? "font-semibold text-amber-600"
                : "";
    const tooltip =
        status === "lewat"
            ? "Tanggal ini sudah lewat"
            : status === "segera"
                ? "Kurang dari 12 bulan lagi"
                : undefined;

    return (
        <div className="whitespace-nowrap" title={tooltip}>
            <div className={gaya}>{formatTanggal(tanggal)}</div>
            <div className="text-xs text-gray-400">
                {label}
                {status === "lewat" && " · sudah lewat"}
                {status === "segera" && " · < 12 bulan"}
            </div>
        </div>
    );
}

function PerkawinanBadge({ status }) {
    const info = STATUS_PERKAWINAN[status];
    if (!info) {
        return <span className="text-gray-400">-</span>;
    }
    return (
        <span
            className={`px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap ${info.style}`}
        >
            {info.label}
        </span>
    );
}

function KepegawaianBadge({ status }) {
    if (!STATUS_KEPEGAWAIAN[status]) {
        return <span className="text-gray-400">-</span>;
    }
    return (
        <span
            className={`px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap ${KEPEGAWAIAN_STYLE[status]}`}
        >
            {STATUS_KEPEGAWAIAN[status]}
        </span>
    );
}

function ModalShell({ title, onClose, children }) {
    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            role="dialog"
            aria-modal="true"
        >
            <div className="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-bold">{title}</h3>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-1 rounded hover:bg-gray-100 text-gray-500"
                        aria-label="Tutup"
                    >
                        <X size={18} />
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}

// Ambil pesan error pertama dari respons Laravel (422) bila ada.
async function pesanError(res, fallback) {
    try {
        const body = await res.json();
        if (body.errors) {
            const first = Object.values(body.errors)[0];
            if (first && first[0]) return first[0];
        }
        return body.message || fallback;
    } catch {
        return fallback;
    }
}

function Field({ label, children }) {
    return (
        <label className="block mb-4">
            <span className="block text-sm font-medium text-gray-700 mb-1">{label}</span>
            {children}
        </label>
    );
}

function EditPegawaiModal({ pegawai, golonganList, onClose, onSaved }) {
    const [form, setForm] = useState({
        nama: pegawai.nama || "",
        jenis_kelamin: pegawai.jenis_kelamin || "L",
        status_kepegawaian: pegawai.status_kepegawaian || "PNS",
        golongan_ruang_id: pegawai.golongan_ruang_id ? String(pegawai.golongan_ruang_id) : "",
        jabatan: pegawai.jabatan || "",
        status_perkawinan: pegawai.status_perkawinan || "",
    });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");

    function ubah(nama) {
        return (e) => setForm((f) => ({ ...f, [nama]: e.target.value }));
    }

    async function handleSubmit(e) {
        e.preventDefault();

        if (!form.nama.trim()) {
            setError("Nama tidak boleh kosong.");
            return;
        }

        setSaving(true);
        setError("");

        try {
            const res = await apiFetch(`/pegawai/${pegawai.id}`, {
                method: "PUT",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    nama: form.nama.trim(),
                    jenis_kelamin: form.jenis_kelamin,
                    status_kepegawaian: form.status_kepegawaian,
                    golongan_ruang_id: form.golongan_ruang_id ? Number(form.golongan_ruang_id) : null,
                    jabatan: form.jabatan.trim() || null,
                    status_perkawinan: form.status_perkawinan || null,
                }),
            });

            if (!res.ok) {
                throw new Error(await pesanError(res, "Gagal menyimpan perubahan."));
            }

            const body = await res.json();
            onSaved(body.data);
        } catch (err) {
            setError(err.message || "Gagal menyimpan perubahan.");
            setSaving(false);
        }
    }

    const inputClass = "w-full border rounded-lg px-3 py-2 text-sm";

    return (
        <ModalShell title="Edit Data Pegawai" onClose={onClose}>
            <form onSubmit={handleSubmit}>
                <Field label="Nama">
                    <input type="text" value={form.nama} onChange={ubah("nama")} className={inputClass} />
                </Field>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Jenis Kelamin">
                        <select value={form.jenis_kelamin} onChange={ubah("jenis_kelamin")} className={inputClass}>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </Field>

                    <Field label="Status Kepegawaian">
                        <select value={form.status_kepegawaian} onChange={ubah("status_kepegawaian")} className={inputClass}>
                            {Object.entries(STATUS_KEPEGAWAIAN).map(([nilai, label]) => (
                                <option key={nilai} value={nilai}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>

                <Field label="Pangkat / Golongan">
                    <select value={form.golongan_ruang_id} onChange={ubah("golongan_ruang_id")} className={inputClass}>
                        <option value="">- (kosong)</option>
                        {["PNS", "PPPK"].map((kelompok) => (
                            <optgroup key={kelompok} label={kelompok}>
                                {golonganList
                                    .filter((g) => g.kelompok === kelompok && g.kode !== "-")
                                    .map((g) => (
                                        <option key={g.id} value={g.id}>
                                            {PANGKAT_PNS[g.kode] ? `${g.kode} - ${PANGKAT_PNS[g.kode]}` : `Gol. ${g.kode}`}
                                        </option>
                                    ))}
                            </optgroup>
                        ))}
                    </select>
                </Field>

                <Field label="Jabatan">
                    <input type="text" value={form.jabatan} onChange={ubah("jabatan")} className={inputClass} />
                </Field>

                <Field label="Status Perkawinan">
                    <select value={form.status_perkawinan} onChange={ubah("status_perkawinan")} className={inputClass}>
                        <option value="">Belum terisi</option>
                        {Object.entries(STATUS_PERKAWINAN).map(([nilai, info]) => (
                            <option key={nilai} value={nilai}>
                                {info.label}
                            </option>
                        ))}
                    </select>
                </Field>

                {error && (
                    <div className="mb-4 p-3 rounded-lg bg-red-50 text-red-600 text-sm">{error}</div>
                )}

                <div className="flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={saving}
                        className="px-4 py-2 border rounded-lg text-sm disabled:opacity-50"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        disabled={saving}
                        className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-[#006A4E] hover:bg-[#005a41] disabled:opacity-60"
                    >
                        {saving && <LoaderCircle className="animate-spin" size={16} />}
                        Simpan
                    </button>
                </div>
            </form>
        </ModalShell>
    );
}

function HapusPegawaiModal({ pegawai, onClose, onDeleted }) {
    const [deleting, setDeleting] = useState(false);
    const [error, setError] = useState("");

    async function handleDelete() {
        setDeleting(true);
        setError("");

        try {
            const res = await apiFetch(`/pegawai/${pegawai.id}`, { method: "DELETE" });

            if (!res.ok) {
                throw new Error(await pesanError(res, "Gagal menghapus data pegawai."));
            }

            onDeleted();
        } catch (err) {
            setError(err.message || "Gagal menghapus data pegawai.");
            setDeleting(false);
        }
    }

    return (
        <ModalShell title="Hapus Data Pegawai" onClose={onClose}>
            <p className="text-sm text-gray-600 mb-2">
                Yakin ingin menghapus <span className="font-semibold">{pegawai.nama}</span>?
            </p>
            <p className="text-sm text-gray-500 mb-5">
                Data tidak akan muncul lagi di daftar dan rekapitulasi. Penghapusan ini bersifat
                soft delete, jadi data masih bisa dipulihkan oleh pengelola database.
            </p>

            {error && (
                <div className="mb-4 p-3 rounded-lg bg-red-50 text-red-600 text-sm">{error}</div>
            )}

            <div className="flex justify-end gap-3">
                <button
                    type="button"
                    onClick={onClose}
                    disabled={deleting}
                    className="px-4 py-2 border rounded-lg text-sm disabled:opacity-50"
                >
                    Batal
                </button>
                <button
                    type="button"
                    onClick={handleDelete}
                    disabled={deleting}
                    className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-red-600 hover:bg-red-700 disabled:opacity-60"
                >
                    {deleting && <LoaderCircle className="animate-spin" size={16} />}
                    Ya, hapus
                </button>
            </div>
        </ModalShell>
    );
}

export default function DaftarPegawai() {
    const [search, setSearch] = useState("");
    const [searchAktif, setSearchAktif] = useState("");
    const [statusPerkawinan, setStatusPerkawinan] = useState("");
    const [statusKepegawaian, setStatusKepegawaian] = useState("");
    const [page, setPage] = useState(1);

    const [rows, setRows] = useState([]);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    const [golonganList, setGolonganList] = useState([]);
    const [instansiList, setInstansiList] = useState([]);
    const [instansiId, setInstansiId] = useState("");
    const [editRow, setEditRow] = useState(null);
    const [hapusRow, setHapusRow] = useState(null);
    const [notice, setNotice] = useState("");
    const [reloadKey, setReloadKey] = useState(0);

    // Tunggu user berhenti mengetik sebentar sebelum mencari.
    useEffect(() => {
        const timer = setTimeout(() => {
            setSearchAktif(search.trim());
            setPage(1);
        }, 400);
        return () => clearTimeout(timer);
    }, [search]);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError("");

            try {
                const params = new URLSearchParams({
                    page: String(page),
                    per_page: "20",
                });
                if (searchAktif) params.set("search", searchAktif);
                if (statusPerkawinan) params.set("status_perkawinan", statusPerkawinan);
                if (statusKepegawaian) params.set("status_kepegawaian", statusKepegawaian);
                if (instansiId) params.set("instansi_id", instansiId);

                const res = await apiFetch(`/pegawai?${params.toString()}`);

                if (res.status === 403) {
                    throw new Error("Kamu tidak berhak melihat daftar pegawai.");
                }
                if (!res.ok) {
                    throw new Error("Gagal memuat daftar pegawai.");
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

        return () => {
            cancelled = true;
        };
    }, [page, searchAktif, statusPerkawinan, statusKepegawaian, instansiId, reloadKey]);

    // Daftar golongan untuk dropdown di form edit (sekali saja).
    useEffect(() => {
        apiFetch("/referensi/golongan")
            .then((res) => (res.ok ? res.json() : []))
            .then((list) => setGolonganList(Array.isArray(list) ? list : []))
            .catch(() => setGolonganList([]));
    }, []);

    // Daftar perangkat daerah untuk filter (sekali saja).
    useEffect(() => {
        apiFetch("/referensi/instansi")
            .then((res) => (res.ok ? res.json() : []))
            .then((list) => setInstansiList(Array.isArray(list) ? list : []))
            .catch(() => setInstansiList([]));
    }, []);

    // Pesan sukses hilang sendiri setelah beberapa detik.
    useEffect(() => {
        if (!notice) return;
        const timer = setTimeout(() => setNotice(""), 4000);
        return () => clearTimeout(timer);
    }, [notice]);

    function handleSaved(updated) {
        setRows((prev) => prev.map((r) => (r.id === updated.id ? updated : r)));
        setEditRow(null);
        setNotice(`Data ${updated.nama} berhasil diperbarui.`);
    }

    function handleDeleted() {
        const nama = hapusRow?.nama;
        setHapusRow(null);
        setNotice(`Data ${nama} berhasil dihapus.`);
        // Kalau yang dihapus satu-satunya baris di halaman ini, mundur satu halaman.
        if (rows.length === 1 && page > 1) {
            setPage(page - 1);
        } else {
            setReloadKey((k) => k + 1);
        }
    }

    function ubahFilter(setter) {
        return (e) => {
            setter(e.target.value);
            setPage(1);
        };
    }

    const lastPage = meta?.last_page || 1;
    const from = meta?.from || 0;

    return (
        <div className="bg-white rounded-xl shadow p-6">
            {editRow && (
                <EditPegawaiModal
                    pegawai={editRow}
                    golonganList={golonganList}
                    onClose={() => setEditRow(null)}
                    onSaved={handleSaved}
                />
            )}
            {hapusRow && (
                <HapusPegawaiModal
                    pegawai={hapusRow}
                    onClose={() => setHapusRow(null)}
                    onDeleted={handleDeleted}
                />
            )}

            <div className="flex flex-wrap gap-3 mb-5">
                <div className="relative flex-1 min-w-[220px]">
                    <Search
                        size={16}
                        className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                    />
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari nama atau NIP..."
                        className="w-full border rounded-lg pl-9 pr-3 py-2 text-sm"
                    />
                </div>

                <select
                    value={statusPerkawinan}
                    onChange={ubahFilter(setStatusPerkawinan)}
                    className="border rounded-lg px-3 py-2 text-sm"
                >
                    <option value="">Semua status perkawinan</option>
                    <option value="KAWIN">Kawin</option>
                    <option value="BELUM_KAWIN">Belum Kawin</option>
                    <option value="CERAI_HIDUP">Cerai Hidup</option>
                    <option value="CERAI_MATI">Cerai Mati</option>
                    <option value="JANDA">Janda</option>
                    <option value="DUDA">Duda</option>
                    <option value="KOSONG">Belum terisi</option>
                </select>

                <select
                    value={instansiId}
                    onChange={ubahFilter(setInstansiId)}
                    className="border rounded-lg px-3 py-2 text-sm max-w-[260px]"
                >
                    <option value="">Semua perangkat daerah</option>
                    {instansiList.map((i) => (
                        <option key={i.id} value={i.id}>
                            {i.nama}
                        </option>
                    ))}
                </select>

                <select
                    value={statusKepegawaian}
                    onChange={ubahFilter(setStatusKepegawaian)}
                    className="border rounded-lg px-3 py-2 text-sm"
                >
                    <option value="">Semua status kepegawaian</option>
                    {Object.entries(STATUS_KEPEGAWAIAN).map(([nilai, label]) => (
                        <option key={nilai} value={nilai}>
                            {label}
                        </option>
                    ))}
                </select>
            </div>

            {notice && (
                <div className="mb-4 p-3 rounded-lg bg-green-50 text-[#006A4E] text-sm">
                    {notice}
                </div>
            )}

            {error && (
                <div className="mb-4 p-3 rounded-lg bg-red-50 text-red-600 text-sm">
                    {error}
                </div>
            )}

            <div className="flex flex-wrap items-center gap-x-5 gap-y-1 mb-3 text-xs text-gray-500">
                <span>Warna tanggal pada kolom Pensiun / Kontrak:</span>
                <span className="flex items-center gap-1.5">
                    <span className="w-2.5 h-2.5 rounded-full bg-amber-500" />
                    Kuning: kurang dari 12 bulan lagi
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="w-2.5 h-2.5 rounded-full bg-red-600" />
                    Merah: sudah lewat
                </span>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left text-gray-500 border-b">
                            <th className="py-3 pr-3 w-12">No</th>
                            <th className="py-3 pr-3">Nama</th>
                            <th className="py-3 pr-3">Pangkat / Golongan</th>
                            <th className="py-3 pr-3">Jabatan</th>
                            <th className="py-3 pr-3">Perangkat Daerah / Unit</th>
                            <th className="py-3 pr-3">Status Kepegawaian</th>
                            <th className="py-3 pr-3">Status Perkawinan</th>
                            <th className="py-3 pr-3">Pensiun / Kontrak</th>
                            <th className="py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading ? (
                            <tr>
                                <td colSpan={9} className="py-10 text-center text-gray-400">
                                    <LoaderCircle className="inline animate-spin mr-2" size={18} />
                                    Memuat data...
                                </td>
                            </tr>
                        ) : rows.length === 0 ? (
                            <tr>
                                <td colSpan={9} className="py-10 text-center text-gray-400">
                                    Tidak ada data pegawai yang cocok.
                                </td>
                            </tr>
                        ) : (
                            rows.map((p, i) => (
                                <tr key={p.id} className="border-b last:border-0 align-top">
                                    <td className="py-3 pr-3 text-gray-500">{from + i}</td>
                                    <td className="py-3 pr-3">
                                        <div className="font-semibold">{p.nama}</div>
                                        <div className="text-xs text-gray-400">
                                            {p.nip ? `NIP ${p.nip}` : "NIP -"}
                                        </div>
                                    </td>
                                    <td className="py-3 pr-3">
                                        {p.status_kepegawaian === "PPPK_PW" ? (
                                            <span className="text-gray-400 italic">Tidak berlaku</span>
                                        ) : (
                                            labelPangkat(p.golongan)
                                        )}
                                    </td>
                                    <td className="py-3 pr-3">{p.jabatan || "-"}</td>
                                    <td className="py-3 pr-3">
                                        <div>{p.instansi || p.unit || "-"}</div>
                                        {p.sub_unit && p.sub_unit !== (p.instansi || p.unit) && (
                                            <div className="text-xs text-gray-400">{p.sub_unit}</div>
                                        )}
                                    </td>
                                    <td className="py-3 pr-3">
                                        <KepegawaianBadge status={p.status_kepegawaian} />
                                    </td>
                                    <td className="py-3 pr-3">
                                        <PerkawinanBadge status={p.status_perkawinan} />
                                    </td>
                                    <td className="py-3 pr-3">
                                        <TanggalAkhirCell
                                            tanggal={p.tanggal_akhir}
                                            jenis={p.jenis_tanggal_akhir}
                                        />
                                    </td>
                                    <td className="py-3">
                                        <div className="flex justify-end gap-2">
                                            <button
                                                type="button"
                                                onClick={() => setEditRow(p)}
                                                title="Edit"
                                                className="p-2 rounded-lg border text-gray-600 hover:bg-gray-50"
                                            >
                                                <Pencil size={15} />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setHapusRow(p)}
                                                title="Hapus"
                                                className="p-2 rounded-lg border text-red-600 hover:bg-red-50"
                                            >
                                                <Trash2 size={15} />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            <div className="flex items-center justify-between mt-5 text-sm text-gray-500">
                <span>
                    {meta
                        ? `Halaman ${meta.current_page} dari ${lastPage} · ${meta.total} pegawai`
                        : ""}
                </span>
                <div className="flex gap-2">
                    <button
                        type="button"
                        disabled={page <= 1 || loading}
                        onClick={() => setPage((p) => Math.max(1, p - 1))}
                        className="flex items-center gap-1 px-3 py-2 border rounded-lg disabled:opacity-40"
                    >
                        <ChevronLeft size={16} /> Sebelumnya
                    </button>
                    <button
                        type="button"
                        disabled={page >= lastPage || loading}
                        onClick={() => setPage((p) => p + 1)}
                        className="flex items-center gap-1 px-3 py-2 border rounded-lg disabled:opacity-40"
                    >
                        Berikutnya <ChevronRight size={16} />
                    </button>
                </div>
            </div>
        </div>
    );
}