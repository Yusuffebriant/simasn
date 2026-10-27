import { useEffect, useState } from "react";
import {
    LoaderCircle,
    AlertTriangle,
    CheckCircle2,
    Copy,
    Play,
    Terminal,
    Info,
} from "lucide-react";
import { apiFetch } from "../../lib/api";
import { formatPeriodeLabel } from "../../lib/periode";

// Menu Perawatan Sistem: menjelaskan perintah (artisan/terminal) yang ada di
// sistem dan menjalankan yang aman langsung dari layar.
// Sumber: GET /perawatan dan POST /perawatan/{kunci}/jalankan (super-admin).

const RISIKO = {
    aman: { label: "Aman", kelas: "bg-green-50 text-[#006A4E]" },
    sedang: { label: "Ubah arsip", kelas: "bg-amber-50 text-amber-700" },
    bahaya: { label: "Hapus permanen", kelas: "bg-red-50 text-red-700" },
};

function KartuStatus({ judul, nilai, catatan, peringatan = false }) {
    return (
        <div className={`rounded-lg border p-4 ${peringatan ? "border-red-200 bg-red-50" : "bg-white"}`}>
            <p className="text-xs text-gray-500">{judul}</p>
            <p className={`text-xl font-bold mt-1 ${peringatan ? "text-red-700" : "text-gray-800"}`}>
                {nilai ?? "-"}
            </p>
            {catatan && <p className="text-xs text-gray-500 mt-1">{catatan}</p>}
        </div>
    );
}

function KodePerintah({ teks }) {
    const [tersalin, setTersalin] = useState(false);

    async function salin() {
        try {
            await navigator.clipboard.writeText(teks);
            setTersalin(true);
            setTimeout(() => setTersalin(false), 1500);
        } catch {
            // Clipboard tidak tersedia: abaikan, teks tetap bisa diblok manual.
        }
    }

    return (
        <div className="flex items-center gap-2 mt-2">
            <code className="flex-1 min-w-0 overflow-x-auto rounded bg-gray-900 px-3 py-2 text-xs text-green-300 whitespace-nowrap">
                {teks}
            </code>
            <button
                type="button"
                onClick={salin}
                className="flex items-center gap-1 rounded border px-2 py-2 text-xs text-gray-600 hover:bg-gray-100"
                title="Salin perintah"
            >
                <Copy size={14} />
                {tersalin ? "Tersalin" : "Salin"}
            </button>
        </div>
    );
}

function PerawatanSistem() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");

    // Perintah yang sedang minta konfirmasi, yang sedang jalan, dan hasil terakhir.
    const [konfirmasi, setKonfirmasi] = useState(null);
    const [ketikan, setKetikan] = useState("");
    const [berjalan, setBerjalan] = useState(null);
    const [hasil, setHasil] = useState({}); // kunci => { ok, pesan, keluaran }

    async function muat() {
        setError("");
        try {
            const res = await apiFetch("/perawatan");
            if (res.status === 403) {
                throw new Error("Menu ini hanya untuk super-admin.");
            }
            if (!res.ok) {
                throw new Error("Gagal memuat menu perawatan.");
            }
            setData(await res.json());
        } catch (e) {
            setError(e.message);
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        muat();
    }, []);

    async function jalankan(p) {
        setBerjalan(p.kunci);
        setHasil((h) => ({ ...h, [p.kunci]: undefined }));

        try {
            const res = await apiFetch(`/perawatan/${p.kunci}/jalankan`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(p.perlu_konfirmasi ? { konfirmasi: ketikan.trim() } : {}),
            });
            const json = await res.json().catch(() => ({}));

            if (!res.ok) {
                throw new Error(json.message || "Perintah gagal dijalankan.");
            }

            setHasil((h) => ({
                ...h,
                [p.kunci]: { ok: true, pesan: json.message, keluaran: json.keluaran },
            }));

            if (json.status) {
                setData((d) => (d ? { ...d, status: { ...d.status, ...json.status } } : d));
            }
        } catch (e) {
            setHasil((h) => ({ ...h, [p.kunci]: { ok: false, pesan: e.message } }));
        } finally {
            setBerjalan(null);
            setKonfirmasi(null);
            setKetikan("");
        }
    }

    if (loading) {
        return (
            <div className="flex items-center gap-3 text-gray-600 bg-white p-6 rounded-xl shadow">
                <LoaderCircle className="animate-spin" />
                Memuat...
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex items-center gap-3 text-red-700 bg-red-50 p-6 rounded-xl">
                <AlertTriangle />
                {error}
            </div>
        );
    }

    const st = data.status;
    const totalSisa = st.sisa ? Object.values(st.sisa).reduce((a, b) => a + b, 0) : null;

    // Kelompokkan perintah menurut kelompoknya, urutan sesuai daftar dari server.
    const kelompok = [];
    data.perintah.forEach((p) => {
        let k = kelompok.find((x) => x.nama === p.kelompok);
        if (!k) {
            k = { nama: p.kelompok, daftar: [] };
            kelompok.push(k);
        }
        k.daftar.push(p);
    });

    const adaImport = st.import_berjalan > 0;

    return (
        <div>
            <h2 className="text-2xl font-bold mb-2">Perawatan Sistem</h2>
            <p className="text-sm text-gray-600 mb-5 max-w-3xl">
                Halaman ini menjelaskan perintah yang ada di sistem (yang biasanya hanya
                dijalankan lewat terminal) dan memungkinkan yang aman dijalankan langsung
                dari sini, misalnya membersihkan cache. Hanya super-admin yang bisa membukanya.
            </p>

            <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
                <KartuStatus
                    judul="Periode data aktif"
                    nilai={st.periode_aktif ? formatPeriodeLabel(st.periode_aktif) : "-"}
                    catatan="Import terakhir yang selesai"
                />
                <KartuStatus
                    judul="Import sedang diproses"
                    nilai={st.import_berjalan}
                    catatan={adaImport ? "Perintah arsip & pembersihan dikunci" : "Tidak ada"}
                    peringatan={adaImport}
                />
                <KartuStatus
                    judul="Antrean menunggu"
                    nilai={st.antrean_menunggu}
                    catatan="Pekerjaan belum dikerjakan worker"
                />
                <KartuStatus
                    judul="Antrean gagal"
                    nilai={st.antrean_gagal}
                    catatan="Lihat dengan queue:failed"
                    peringatan={st.antrean_gagal > 0}
                />
                <KartuStatus
                    judul="Data sisa import"
                    nilai={totalSisa}
                    catatan={
                        st.sisa
                            ? `${st.sisa.pegawai} pegawai · ${st.sisa.snapshot} arsip · ${st.sisa.file_upload} file`
                            : null
                    }
                    peringatan={totalSisa > 0}
                />
            </div>

            {adaImport && (
                <div className="mb-6 flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-300 p-3 text-sm text-amber-900">
                    <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                    <span>
                        Ada import yang sedang diproses. Perintah arsip dan pembersihan
                        ditolak sampai import selesai, supaya datanya tidak bentrok.
                    </span>
                </div>
            )}

            {kelompok.map((k) => (
                <section key={k.nama} className="mb-8">
                    <h3 className="text-lg font-bold mb-3">{k.nama}</h3>

                    <div className="space-y-3">
                        {k.daftar.map((p) => {
                            const r = RISIKO[p.risiko] ?? RISIKO.aman;
                            const hasilIni = hasil[p.kunci];
                            const sedangJalan = berjalan === p.kunci;
                            const minta = konfirmasi === p.kunci;
                            const kosongSisa = p.kunci === "bersihkan-sisa" && totalSisa === 0;
                            const dikunci = adaImport && p.risiko !== "aman" && p.kunci !== "cache-rekap";

                            return (
                                <div key={p.kunci} className="bg-white rounded-xl shadow p-5">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h4 className="font-semibold">{p.judul}</h4>
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${r.kelas}`}>
                                                    {r.label}
                                                </span>
                                            </div>
                                            <p className="text-sm text-gray-600 mt-1">{p.penjelasan}</p>
                                            {p.perintah && <KodePerintah teks={p.perintah} />}
                                        </div>

                                        {!minta && (
                                            <button
                                                type="button"
                                                disabled={sedangJalan || berjalan !== null || dikunci || kosongSisa}
                                                onClick={() => {
                                                    setHasil((h) => ({ ...h, [p.kunci]: undefined }));
                                                    if (p.risiko === "aman") {
                                                        jalankan(p);
                                                    } else {
                                                        setKetikan("");
                                                        setKonfirmasi(p.kunci);
                                                    }
                                                }}
                                                className={`flex items-center gap-2 rounded px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 disabled:cursor-not-allowed ${p.risiko === "bahaya" ? "bg-red-600 hover:bg-red-700" : "bg-[#006A4E] hover:bg-[#005a41]"}`}
                                                title={
                                                    dikunci
                                                        ? "Menunggu import selesai"
                                                        : kosongSisa
                                                            ? "Tidak ada data sisa"
                                                            : ""
                                                }
                                            >
                                                {sedangJalan ? (
                                                    <LoaderCircle className="animate-spin" size={16} />
                                                ) : (
                                                    <Play size={16} />
                                                )}
                                                {sedangJalan ? "Menjalankan..." : "Jalankan"}
                                            </button>
                                        )}
                                    </div>

                                    {minta && (
                                        <div className={`mt-4 rounded-lg border p-4 text-sm ${p.risiko === "bahaya" ? "border-red-200 bg-red-50 text-red-800" : "border-amber-200 bg-amber-50 text-amber-900"}`}>
                                            {p.perlu_konfirmasi ? (
                                                <>
                                                    <p className="font-semibold">
                                                        Ini menghapus permanen dan tidak bisa dibatalkan.
                                                    </p>
                                                    {st.sisa && (
                                                        <p className="mt-1">
                                                            Yang akan dihapus: {st.sisa.pegawai} pegawai tanpa riwayat
                                                            import, {st.sisa.snapshot} arsip rekap tanpa import, dan{" "}
                                                            {st.sisa.file_upload} file upload yatim.
                                                        </p>
                                                    )}
                                                    <p className="mt-2">Ketik <b>HAPUS</b> untuk melanjutkan:</p>
                                                    <input
                                                        value={ketikan}
                                                        onChange={(e) => setKetikan(e.target.value)}
                                                        autoFocus
                                                        className="mt-1 border rounded px-3 py-2 bg-white text-gray-800"
                                                    />
                                                </>
                                            ) : (
                                                <p className="font-semibold">
                                                    Jalankan &ldquo;{p.judul}&rdquo; sekarang? Proses ini bisa memakan waktu
                                                    beberapa menit; jangan tutup halaman.
                                                </p>
                                            )}
                                            <div className="mt-3 flex gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => { setKonfirmasi(null); setKetikan(""); }}
                                                    className="rounded border bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
                                                >
                                                    Batal
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => jalankan(p)}
                                                    disabled={p.perlu_konfirmasi && ketikan.trim().toUpperCase() !== "HAPUS"}
                                                    className={`rounded px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 ${p.risiko === "bahaya" ? "bg-red-600 hover:bg-red-700" : "bg-[#006A4E] hover:bg-[#005a41]"}`}
                                                >
                                                    Ya, jalankan
                                                </button>
                                            </div>
                                        </div>
                                    )}

                                    {hasilIni && (
                                        <div className={`mt-4 rounded-lg p-3 text-sm ${hasilIni.ok ? "bg-green-50 text-green-800" : "bg-red-50 text-red-700"}`}>
                                            <div className="flex items-start gap-2">
                                                {hasilIni.ok ? (
                                                    <CheckCircle2 size={16} className="mt-0.5 shrink-0" />
                                                ) : (
                                                    <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                                                )}
                                                <div className="min-w-0">
                                                    <p className="font-semibold">{hasilIni.pesan}</p>
                                                    {hasilIni.keluaran && (
                                                        <pre className="mt-1 whitespace-pre-wrap break-words text-xs font-mono">
                                                            {hasilIni.keluaran}
                                                        </pre>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </section>
            ))}

            <section className="mb-8">
                <h3 className="text-lg font-bold mb-1 flex items-center gap-2">
                    <Terminal size={18} />
                    Perintah yang hanya lewat terminal
                </h3>
                <p className="text-sm text-gray-600 mb-3 flex items-start gap-2">
                    <Info size={16} className="mt-0.5 shrink-0" />
                    Perintah berikut tidak disediakan tombolnya karena mengubah struktur
                    database, perlu proses yang berjalan terus, atau dijalankan di server.
                    Jalankan dari folder proyek di terminal server.
                </p>

                <div className="space-y-3">
                    {data.hanya_terminal.map((t) => (
                        <div key={t.perintah} className="bg-white rounded-xl shadow p-5">
                            <h4 className="font-semibold">{t.judul}</h4>
                            <p className="text-sm text-gray-600 mt-1">{t.penjelasan}</p>
                            <KodePerintah teks={t.perintah} />
                        </div>
                    ))}
                </div>
            </section>
        </div>
    );
}

export default PerawatanSistem;