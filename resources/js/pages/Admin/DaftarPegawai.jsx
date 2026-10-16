import { useEffect, useMemo, useRef, useState } from "react";
import {
    AlertTriangle,
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

// Nama isian untuk chip filter "isian kosong" (sama dengan KelengkapanPegawai di backend).
const LABEL_KOSONG = {
    jenis_kelamin: "Jenis kelamin",
    tanggal_lahir: "Tanggal lahir",
    instansi: "Perangkat daerah",
    agama: "Agama",
    pendidikan: "Pendidikan",
    jabatan: "Jabatan",
    jenis_kedudukan: "Jenis kedudukan",
    golongan: "Pangkat / golongan",
    status_perkawinan: "Status perkawinan",
};

const JENIS_KEDUDUKAN = {
    PELAKSANA: "Pelaksana",
    FUNGSIONAL: "Fungsional",
    STRUKTURAL: "Struktural",
};

function paramUrl(nama) {
    return new URLSearchParams(window.location.search).get(nama) || "";
}

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

function ModalShell({ title, onClose, children, lebar = "max-w-lg" }) {
    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            role="dialog"
            aria-modal="true"
        >
            <div className={`bg-white rounded-xl shadow-xl w-full ${lebar} max-h-[90vh] overflow-y-auto p-6`}>
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

function Field({ label, children, kosong = false }) {
    return (
        <label className="block mb-4">
            <span className="block text-sm font-medium text-gray-700 mb-1">
                {label}
                {kosong && (
                    <span className="ml-2 text-xs font-semibold text-amber-600">
                        &bull; belum diisi
                    </span>
                )}
            </span>
            {children}
        </label>
    );
}

function EditPegawaiModal({
    pegawai,
    golonganList,
    instansiList,
    agamaList,
    pendidikanList,
    formAwal,
    kesalahan,
    onClose,
    onSubmit,
}) {
    const [form, setForm] = useState(formAwal || {
        nama: pegawai.nama || "",
        jenis_kelamin: pegawai.jenis_kelamin || "L",
        status_kepegawaian: pegawai.status_kepegawaian || "PNS",
        golongan_ruang_id: pegawai.golongan_ruang_id ? String(pegawai.golongan_ruang_id) : "",
        jabatan: pegawai.jabatan || "",
        status_perkawinan: pegawai.status_perkawinan || "",
        instansi_id: pegawai.instansi_id ? String(pegawai.instansi_id) : "",
        tanggal_lahir: pegawai.tanggal_lahir || "",
        agama_id: pegawai.agama_id ? String(pegawai.agama_id) : "",
        pendidikan_id: pegawai.pendidikan_id ? String(pegawai.pendidikan_id) : "",
        jenis_kedudukan: pegawai.jenis_kedudukan || "",
    });

    // Isian yang kosong saat modal dibuka, supaya ditandai untuk admin.
    const kosong = new Set((pegawai.kosong || []).map((k) => k.kunci));
    const [error, setError] = useState(kesalahan || "");

    function ubah(nama) {
        return (e) => setForm((f) => ({ ...f, [nama]: e.target.value }));
    }

    // Simpan tidak menunggu server: modal langsung menutup, baris di tabel
    // ditandai "menyimpan", dan hasilnya diterapkan begitu server membalas.
    // Kalau gagal, modal dibuka lagi dengan isian terakhir + pesan kesalahannya.
    function handleSubmit(e) {
        e.preventDefault();

        if (!form.nama.trim()) {
            setError("Nama tidak boleh kosong.");
            return;
        }

        onSubmit(
            pegawai,
            {
                nama: form.nama.trim(),
                jenis_kelamin: form.jenis_kelamin,
                status_kepegawaian: form.status_kepegawaian,
                golongan_ruang_id: form.golongan_ruang_id ? Number(form.golongan_ruang_id) : null,
                jabatan: form.jabatan.trim() || null,
                status_perkawinan: form.status_perkawinan || null,
                jenis_kedudukan: form.jenis_kedudukan || null,
                tanggal_lahir: form.tanggal_lahir || null,
                agama_id: form.agama_id ? Number(form.agama_id) : null,
                pendidikan_id: form.pendidikan_id ? Number(form.pendidikan_id) : null,
                // instansi_id wajib valid bila dikirim, jadi hanya dikirim bila dipilih.
                ...(form.instansi_id ? { instansi_id: Number(form.instansi_id) } : {}),
            },
            form
        );
    }

    const inputClass = "w-full border rounded-lg px-3 py-2 text-sm";

    return (
        <ModalShell title="Edit Data Pegawai" onClose={onClose} lebar="max-w-2xl">
            <form onSubmit={handleSubmit}>
                <Field label="Nama">
                    <input type="text" value={form.nama} onChange={ubah("nama")} className={inputClass} />
                </Field>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Jenis Kelamin" kosong={kosong.has("jenis_kelamin")}>
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

                <Field label="Pangkat / Golongan" kosong={kosong.has("golongan")}>
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

                <Field label="Jabatan" kosong={kosong.has("jabatan")}>
                    <input type="text" value={form.jabatan} onChange={ubah("jabatan")} className={inputClass} />
                </Field>

                <Field label="Perangkat Daerah" kosong={kosong.has("instansi")}>
                    <select value={form.instansi_id} onChange={ubah("instansi_id")} className={inputClass}>
                        <option value="">- (kosong)</option>
                        {instansiList.map((i) => (
                            <option key={i.id} value={i.id}>
                                {i.nama}
                            </option>
                        ))}
                    </select>
                </Field>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Tanggal Lahir" kosong={kosong.has("tanggal_lahir")}>
                        <input
                            type="date"
                            value={form.tanggal_lahir}
                            onChange={ubah("tanggal_lahir")}
                            className={inputClass}
                        />
                    </Field>

                    <Field label="Jenis Kedudukan" kosong={kosong.has("jenis_kedudukan")}>
                        <select value={form.jenis_kedudukan} onChange={ubah("jenis_kedudukan")} className={inputClass}>
                            <option value="">- (kosong)</option>
                            {Object.entries(JENIS_KEDUDUKAN).map(([nilai, label]) => (
                                <option key={nilai} value={nilai}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Agama" kosong={kosong.has("agama")}>
                        <select value={form.agama_id} onChange={ubah("agama_id")} className={inputClass}>
                            <option value="">- (kosong)</option>
                            {agamaList.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.nama}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Pendidikan" kosong={kosong.has("pendidikan")}>
                        <select value={form.pendidikan_id} onChange={ubah("pendidikan_id")} className={inputClass}>
                            <option value="">- (kosong)</option>
                            {pendidikanList.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.jenjang}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>

                <Field label="Status Perkawinan" kosong={kosong.has("status_perkawinan")}>
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
                        className="px-4 py-2 border rounded-lg text-sm"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-[#006A4E] hover:bg-[#005a41]"
                    >
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

// NIP dari import lama tersimpan dengan bungkus rumus Excel: ="1979..."
// Tampilkan tanpa bungkus itu.
function bersihNip(nip) {
    return String(nip || "").replace(/^=?"?/, "").replace(/"$/, "");
}

const LABEL_JENIS_KELAMIN = { L: "Laki-laki", P: "Perempuan" };

/**
 * Sel yang bisa diubah langsung di tabel: klik isinya, ganti, lalu tersimpan
 * otomatis (pilihan: langsung saat dipilih; teks/tanggal: saat Enter atau
 * pindah ke sel lain; Esc membatalkan). Hanya mengirim satu isian ke
 * PUT /pegawai/{id}.
 */
function CellEdit({
    pegawai,
    field,
    awal,
    tampil,
    tipe = "teks",
    opsi = [],
    bolehKosong = true,
    angka = false,
    kosong = false,
    onSaved,
}) {
    const [edit, setEdit] = useState(false);
    const [nilai, setNilai] = useState(awal);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    const dibatalkan = useRef(false);

    function mulai() {
        dibatalkan.current = false;
        setNilai(awal);
        setError("");
        setEdit(true);
    }

    function batal() {
        setEdit(false);
        setError("");
    }

    async function simpan(nilaiBaru) {
        const bersih = typeof nilaiBaru === "string" ? nilaiBaru.trim() : nilaiBaru;

        if (bersih === awal) {
            batal();
            return;
        }
        if (!bolehKosong && bersih === "") {
            setError("Tidak boleh kosong.");
            return;
        }

        setSaving(true);
        setError("");

        try {
            const res = await apiFetch(`/pegawai/${pegawai.id}`, {
                method: "PUT",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    [field]: bersih === "" ? null : angka ? Number(bersih) : bersih,
                }),
            });

            if (!res.ok) {
                throw new Error(await pesanError(res, "Gagal menyimpan."));
            }

            const body = await res.json();
            setEdit(false);
            onSaved(body.data ?? body);
        } catch (err) {
            setError(err.message || "Gagal menyimpan.");
        } finally {
            setSaving(false);
        }
    }

    if (!edit) {
        const adaIsi = tampil !== null && tampil !== undefined && tampil !== "";

        return (
            <button
                type="button"
                onClick={mulai}
                title="Klik untuk mengubah"
                className="text-left w-full rounded px-1 -mx-1 py-0.5 hover:bg-gray-100 cursor-text"
            >
                {adaIsi ? (
                    tampil
                ) : kosong ? (
                    <span className="text-xs font-semibold text-amber-600">+ Isi</span>
                ) : (
                    <span className="text-gray-400">-</span>
                )}
            </button>
        );
    }

    const kelasInput = "w-full border border-[#006A4E] rounded px-2 py-1 text-sm bg-white";
    const grup = [...new Set(opsi.filter((o) => o.grup).map((o) => o.grup))];

    return (
        <div>
            <div className="flex items-center gap-1.5">
                {tipe === "pilihan" ? (
                    <select
                        autoFocus
                        value={nilai}
                        disabled={saving}
                        onChange={(e) => {
                            setNilai(e.target.value);
                            simpan(e.target.value);
                        }}
                        onBlur={() => {
                            if (!saving) batal();
                        }}
                        onKeyDown={(e) => {
                            if (e.key === "Escape") batal();
                        }}
                        className={kelasInput}
                    >
                        {bolehKosong && <option value="">- (kosong)</option>}
                        {opsi
                            .filter((o) => !o.grup)
                            .map((o) => (
                                <option key={o.value} value={String(o.value)}>
                                    {o.label}
                                </option>
                            ))}
                        {grup.map((g) => (
                            <optgroup key={g} label={g}>
                                {opsi
                                    .filter((o) => o.grup === g)
                                    .map((o) => (
                                        <option key={o.value} value={String(o.value)}>
                                            {o.label}
                                        </option>
                                    ))}
                            </optgroup>
                        ))}
                    </select>
                ) : (
                    <input
                        autoFocus
                        type={tipe === "tanggal" ? "date" : "text"}
                        value={nilai}
                        disabled={saving}
                        onChange={(e) => setNilai(e.target.value)}
                        onBlur={() => {
                            if (dibatalkan.current) batal();
                            else if (!saving) simpan(nilai);
                        }}
                        onKeyDown={(e) => {
                            if (e.key === "Enter") e.currentTarget.blur();
                            if (e.key === "Escape") {
                                dibatalkan.current = true;
                                e.currentTarget.blur();
                            }
                        }}
                        className={kelasInput}
                    />
                )}
                {saving && <LoaderCircle className="animate-spin text-gray-400 shrink-0" size={14} />}
            </div>
            {error && <div className="mt-1 text-xs text-red-600">{error}</div>}
        </div>
    );
}

// Satu baris tabel pegawai. Isian yang kosong ditandai "+ Isi" dan semua
// isian data bisa diubah langsung lewat CellEdit.
function BarisPegawai({ p, nomor, opsi, menyimpan = false, onEdit, onHapus, onSaved }) {
    const kosong = new Set((p.kosong || []).map((k) => k.kunci));
    const sama = { pegawai: p, onSaved };

    return (
        <tr
            className={`border-b last:border-0 align-top transition-opacity ${menyimpan ? "opacity-50 pointer-events-none" : ""
                }`}
        >
            <td className="py-3 pr-3 text-gray-500">{nomor}</td>

            <td className="py-3 pr-3 sticky left-0 z-10 bg-white min-w-[220px]">
                <div className="font-semibold flex items-center gap-2">
                    {p.nama}
                    {menyimpan && <LoaderCircle className="animate-spin text-gray-400" size={14} />}
                </div>
                <div className="text-xs text-gray-400">
                    {p.nip ? `NIP ${bersihNip(p.nip)}` : "NIP -"}
                </div>
                {p.kosong?.length > 0 && (
                    <div className="mt-1 flex items-start gap-1 text-xs text-amber-700">
                        <AlertTriangle size={12} className="mt-0.5 shrink-0" />
                        <span>Belum diisi: {p.kosong.map((k) => k.label).join(", ")}</span>
                    </div>
                )}
            </td>

            <td className="py-3 pr-3 min-w-[110px]">
                <CellEdit
                    {...sama}
                    field="jenis_kelamin"
                    awal={p.jenis_kelamin || ""}
                    tampil={LABEL_JENIS_KELAMIN[p.jenis_kelamin]}
                    tipe="pilihan"
                    opsi={opsi.jenisKelamin}
                    bolehKosong={false}
                    kosong={kosong.has("jenis_kelamin")}
                />
            </td>

            <td className="py-3 pr-3 min-w-[130px]">
                <CellEdit
                    {...sama}
                    field="tanggal_lahir"
                    awal={p.tanggal_lahir || ""}
                    tampil={p.tanggal_lahir ? formatTanggal(p.tanggal_lahir) : null}
                    tipe="tanggal"
                    kosong={kosong.has("tanggal_lahir")}
                />
            </td>

            <td className="py-3 pr-3 min-w-[170px]">
                {p.status_kepegawaian === "PPPK_PW" ? (
                    <span className="text-gray-400 italic">Tidak berlaku</span>
                ) : (
                    <CellEdit
                        {...sama}
                        field="golongan_ruang_id"
                        awal={p.golongan_ruang_id ? String(p.golongan_ruang_id) : ""}
                        tampil={p.golongan ? labelPangkat(p.golongan) : null}
                        tipe="pilihan"
                        opsi={opsi.golongan}
                        angka
                        kosong={kosong.has("golongan")}
                    />
                )}
            </td>

            <td className="py-3 pr-3 min-w-[100px]">
                <CellEdit
                    {...sama}
                    field="eselon_id"
                    awal={p.eselon_id ? String(p.eselon_id) : ""}
                    tampil={p.eselon}
                    tipe="pilihan"
                    opsi={opsi.eselon}
                    bolehKosong={false}
                    angka
                />
            </td>

            <td className="py-3 pr-3 min-w-[240px]">
                <CellEdit
                    {...sama}
                    field="jabatan"
                    awal={p.jabatan || ""}
                    tampil={p.jabatan}
                    kosong={kosong.has("jabatan")}
                />
            </td>

            <td className="py-3 pr-3 min-w-[120px]">
                <CellEdit
                    {...sama}
                    field="jenis_kedudukan"
                    awal={p.jenis_kedudukan || ""}
                    tampil={JENIS_KEDUDUKAN[p.jenis_kedudukan]}
                    tipe="pilihan"
                    opsi={opsi.jenisKedudukan}
                    kosong={kosong.has("jenis_kedudukan")}
                />
            </td>

            <td className="py-3 pr-3 min-w-[240px]">
                <CellEdit
                    {...sama}
                    field="instansi_id"
                    awal={p.instansi_id ? String(p.instansi_id) : ""}
                    tampil={
                        <>
                            <div>{p.instansi || p.unit || "-"}</div>
                            {p.sub_unit && p.sub_unit !== (p.instansi || p.unit) && (
                                <div className="text-xs text-gray-400">{p.sub_unit}</div>
                            )}
                        </>
                    }
                    tipe="pilihan"
                    opsi={opsi.instansi}
                    bolehKosong={false}
                    angka
                    kosong={kosong.has("instansi")}
                />
            </td>

            <td className="py-3 pr-3 min-w-[110px]">
                <CellEdit
                    {...sama}
                    field="agama_id"
                    awal={p.agama_id ? String(p.agama_id) : ""}
                    tampil={p.agama}
                    tipe="pilihan"
                    opsi={opsi.agama}
                    angka
                    kosong={kosong.has("agama")}
                />
            </td>

            <td className="py-3 pr-3 min-w-[110px]">
                <CellEdit
                    {...sama}
                    field="pendidikan_id"
                    awal={p.pendidikan_id ? String(p.pendidikan_id) : ""}
                    tampil={p.pendidikan}
                    tipe="pilihan"
                    opsi={opsi.pendidikan}
                    angka
                    kosong={kosong.has("pendidikan")}
                />
            </td>

            <td className="py-3 pr-3">
                <KepegawaianBadge status={p.status_kepegawaian} />
            </td>

            <td className="py-3 pr-3 min-w-[130px]">
                <CellEdit
                    {...sama}
                    field="status_perkawinan"
                    awal={p.status_perkawinan || ""}
                    tampil={p.status_perkawinan ? <PerkawinanBadge status={p.status_perkawinan} /> : null}
                    tipe="pilihan"
                    opsi={opsi.perkawinan}
                    kosong={kosong.has("status_perkawinan")}
                />
            </td>

            <td className="py-3 pr-3 min-w-[130px]">
                <TanggalAkhirCell tanggal={p.tanggal_akhir} jenis={p.jenis_tanggal_akhir} />
            </td>

            <td className="py-3">
                <div className="flex justify-end gap-2">
                    <button
                        type="button"
                        onClick={() => onEdit(p)}
                        title="Edit semua data (termasuk nama & status kepegawaian)"
                        className="p-2 rounded-lg border text-gray-600 hover:bg-gray-50"
                    >
                        <Pencil size={15} />
                    </button>
                    <button
                        type="button"
                        onClick={() => onHapus(p)}
                        title="Hapus"
                        className="p-2 rounded-lg border text-red-600 hover:bg-red-50"
                    >
                        <Trash2 size={15} />
                    </button>
                </div>
            </td>
        </tr>
    );
}

export default function DaftarPegawai() {
    const [search, setSearch] = useState("");
    const [searchAktif, setSearchAktif] = useState("");
    const [statusPerkawinan, setStatusPerkawinan] = useState("");
    const [statusKepegawaian, setStatusKepegawaian] = useState("");
    // Seleksi kelengkapan data. Nilai awal bisa datang dari URL (tautan
    // "Lengkapi" di hasil import): ?kelengkapan=belum&batch=12&kosong=agama
    const [kelengkapan, setKelengkapan] = useState(() => paramUrl("kelengkapan"));
    const [kosongFilter, setKosongFilter] = useState(() => paramUrl("kosong"));
    const [batchFilter, setBatchFilter] = useState(() => paramUrl("batch"));
    const [page, setPage] = useState(1);

    const [rows, setRows] = useState([]);
    const [meta, setMeta] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    const [golonganList, setGolonganList] = useState([]);
    const [instansiList, setInstansiList] = useState([]);
    const [agamaList, setAgamaList] = useState([]);
    const [pendidikanList, setPendidikanList] = useState([]);
    const [eselonList, setEselonList] = useState([]);
    // Bisa datang dari URL (tautan "Lihat pegawai" di daftar perangkat daerah yang kurang).
    const [instansiId, setInstansiId] = useState(() => paramUrl("instansi_id"));
    const [editRow, setEditRow] = useState(null);
    const [hapusRow, setHapusRow] = useState(null);
    const [notice, setNotice] = useState("");
    const [reloadKey, setReloadKey] = useState(0);
    const [menyimpanIds, setMenyimpanIds] = useState([]); // pegawai yang sedang disimpan
    const [editUlang, setEditUlang] = useState(null); // isian + pesan bila simpan gagal

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
                if (kelengkapan) params.set("kelengkapan", kelengkapan);
                if (kosongFilter) params.set("kosong", kosongFilter);
                if (batchFilter) params.set("batch", batchFilter);

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
    }, [page, searchAktif, statusPerkawinan, statusKepegawaian, instansiId, kelengkapan, kosongFilter, batchFilter, reloadKey]);

    // Daftar golongan untuk dropdown di form edit (sekali saja).
    useEffect(() => {
        apiFetch("/referensi/golongan")
            .then((res) => (res.ok ? res.json() : []))
            .then((list) => setGolonganList(Array.isArray(list) ? list : []))
            .catch(() => setGolonganList([]));
    }, []);

    // Daftar agama & pendidikan untuk form edit (sekali saja).
    useEffect(() => {
        apiFetch("/referensi/agama")
            .then((res) => (res.ok ? res.json() : []))
            .then((list) => setAgamaList(Array.isArray(list) ? list : []))
            .catch(() => setAgamaList([]));

        apiFetch("/referensi/pendidikan")
            .then((res) => (res.ok ? res.json() : []))
            .then((list) => setPendidikanList(Array.isArray(list) ? list : []))
            .catch(() => setPendidikanList([]));

        apiFetch("/referensi/eselon")
            .then((res) => (res.ok ? res.json() : []))
            .then((list) => setEselonList(Array.isArray(list) ? list : []))
            .catch(() => setEselonList([]));
    }, []);

    // Pilihan untuk sel yang bisa diubah langsung di tabel.
    const opsi = useMemo(
        () => ({
            jenisKelamin: Object.entries(LABEL_JENIS_KELAMIN).map(([value, label]) => ({ value, label })),
            jenisKedudukan: Object.entries(JENIS_KEDUDUKAN).map(([value, label]) => ({ value, label })),
            perkawinan: Object.entries(STATUS_PERKAWINAN).map(([value, info]) => ({ value, label: info.label })),
            golongan: golonganList
                .filter((g) => g.kode !== "-" && ["PNS", "PPPK"].includes(g.kelompok))
                .map((g) => ({
                    value: g.id,
                    label: PANGKAT_PNS[g.kode] ? `${g.kode} - ${PANGKAT_PNS[g.kode]}` : `Gol. ${g.kode}`,
                    grup: g.kelompok,
                })),
            eselon: eselonList.map((e) => ({ value: e.id, label: e.kode })),
            instansi: instansiList.map((i) => ({ value: i.id, label: i.nama })),
            agama: agamaList.map((a) => ({ value: a.id, label: a.nama })),
            pendidikan: pendidikanList.map((x) => ({ value: x.id, label: x.jenjang })),
        }),
        [golonganList, eselonList, instansiList, agamaList, pendidikanList]
    );

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

    // Setelah disimpan, apakah baris masih cocok dengan filter kelengkapan
    // yang aktif? Kalau tidak (mis. sudah dilengkapi), ia hilang dari daftar.
    function masihCocok(updated) {
        const kosong = updated.kosong || [];
        if (kosongFilter) return kosong.some((k) => k.kunci === kosongFilter);
        if (kelengkapan === "belum") return kosong.length > 0;
        if (kelengkapan === "lengkap") return kosong.length === 0;
        return true;
    }

    // Perbarui baris di daftar setelah disimpan (dari modal atau langsung di
    // tabel). Baris yang tidak lagi cocok dengan filter dihapus dari daftar.
    function terapkanHasil(updated) {
        if (masihCocok(updated)) {
            setRows((prev) => prev.map((r) => (r.id === updated.id ? updated : r)));
            return;
        }

        const sisa = rows.filter((r) => r.id !== updated.id);
        setRows(sisa);
        setMeta((m) => (m ? { ...m, total: Math.max(0, m.total - 1) } : m));

        if (sisa.length === 0) {
            if (page > 1) setPage(page - 1);
            else setReloadKey((k) => k + 1);
        }
    }

    // Simpan dari modal edit: modal langsung menutup, baris ditandai
    // "menyimpan", lalu hasil dari server diterapkan. Bila gagal, modal
    // dibuka lagi dengan isian terakhir supaya tidak perlu mengetik ulang.
    async function simpanEdit(pegawai, payload, form) {
        setEditRow(null);
        setEditUlang(null);
        setMenyimpanIds((ids) => [...ids, pegawai.id]);

        try {
            const res = await apiFetch(`/pegawai/${pegawai.id}`, {
                method: "PUT",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload),
            });

            if (!res.ok) {
                throw new Error(await pesanError(res, "Gagal menyimpan perubahan."));
            }

            const body = await res.json();
            const updated = body.data ?? body;

            terapkanHasil(updated);
            setNotice(`Data ${updated.nama} berhasil diperbarui.`);
        } catch (err) {
            setEditUlang({ form, kesalahan: err.message || "Gagal menyimpan perubahan." });
            setEditRow(pegawai);
        } finally {
            setMenyimpanIds((ids) => ids.filter((id) => id !== pegawai.id));
        }
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
                    instansiList={instansiList}
                    agamaList={agamaList}
                    pendidikanList={pendidikanList}
                    formAwal={editUlang?.form}
                    kesalahan={editUlang?.kesalahan}
                    onClose={() => {
                        setEditRow(null);
                        setEditUlang(null);
                    }}
                    onSubmit={simpanEdit}
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

                <select
                    value={kelengkapan}
                    onChange={ubahFilter(setKelengkapan)}
                    className="border rounded-lg px-3 py-2 text-sm"
                >
                    <option value="">Semua kelengkapan data</option>
                    <option value="belum">Data belum lengkap</option>
                    <option value="lengkap">Data lengkap</option>
                </select>

                {kosongFilter && (
                    <button
                        type="button"
                        onClick={() => {
                            setKosongFilter("");
                            setPage(1);
                        }}
                        className="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-amber-50 text-amber-800 text-sm border border-amber-200"
                    >
                        Isian kosong: {LABEL_KOSONG[kosongFilter] || kosongFilter}
                        <X size={14} />
                    </button>
                )}

                {batchFilter && (
                    <button
                        type="button"
                        onClick={() => {
                            setBatchFilter("");
                            setPage(1);
                        }}
                        className="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-blue-50 text-blue-800 text-sm border border-blue-200"
                    >
                        Dari import #{batchFilter}
                        <X size={14} />
                    </button>
                )}
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

            <p className="mb-2 text-xs text-gray-500">
                Klik isi kolom mana pun (kecuali Nama dan Status Kepegawaian) untuk mengubahnya
                langsung. Tersimpan otomatis. Tombol pensil untuk mengubah semua data sekaligus.
            </p>

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
                <table className="w-full text-sm min-w-[1900px]">
                    <thead>
                        <tr className="text-left text-gray-500 border-b">
                            <th className="py-3 pr-3 w-12">No</th>
                            <th className="py-3 pr-3 sticky left-0 z-10 bg-white">Nama</th>
                            <th className="py-3 pr-3">Jenis Kelamin</th>
                            <th className="py-3 pr-3">Tanggal Lahir</th>
                            <th className="py-3 pr-3">Pangkat / Golongan</th>
                            <th className="py-3 pr-3">Eselon</th>
                            <th className="py-3 pr-3">Jabatan</th>
                            <th className="py-3 pr-3">Jenis Kedudukan</th>
                            <th className="py-3 pr-3">Perangkat Daerah / Unit</th>
                            <th className="py-3 pr-3">Agama</th>
                            <th className="py-3 pr-3">Pendidikan</th>
                            <th className="py-3 pr-3">Status Kepegawaian</th>
                            <th className="py-3 pr-3">Status Perkawinan</th>
                            <th className="py-3 pr-3">Pensiun / Kontrak</th>
                            <th className="py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {loading ? (
                            <tr>
                                <td colSpan={15} className="py-10 text-center text-gray-400">
                                    <LoaderCircle className="inline animate-spin mr-2" size={18} />
                                    Memuat data...
                                </td>
                            </tr>
                        ) : rows.length === 0 ? (
                            <tr>
                                <td colSpan={15} className="py-10 text-center text-gray-400">
                                    Tidak ada data pegawai yang cocok.
                                </td>
                            </tr>
                        ) : (
                            rows.map((p, i) => (
                                <BarisPegawai
                                    key={p.id}
                                    p={p}
                                    nomor={from + i}
                                    opsi={opsi}
                                    menyimpan={menyimpanIds.includes(p.id)}
                                    onEdit={(row) => {
                                        setEditUlang(null);
                                        setEditRow(row);
                                    }}
                                    onHapus={setHapusRow}
                                    onSaved={terapkanHasil}
                                />
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