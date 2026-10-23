import { useEffect, useMemo, useRef, useState } from "react";
import { LoaderCircle, AlertTriangle, Download, X, Search, ArrowRight } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { formatPeriodeLabel } from "../../lib/periode";

// Nama kelompok data yang berubah, dalam bahasa sehari-hari.
const KATEGORI = {
    jabatan: "Jabatan",
    pangkat_golongan: "Pangkat/golongan",
    unit_kerja: "Unit kerja",
    pendidikan: "Pendidikan",
    status_kepegawaian: "Status kepegawaian (PNS/PPPK/PPPK Paruh Waktu)",
    identitas: "Nama",
    data_pribadi: "Data pribadi",
    keberadaan: "Ada atau tidaknya di data",
};

// Kode status di data (PPPK_PW) -> nama untuk tampilan.
const labelStatus = (s) => (s === "PPPK_PW" ? "PPPK Paruh Waktu" : s);

const STATUS_PERIODE = {
    tersedia: ["Lengkap", "text-green-700 bg-green-50"],
    snapshot_individual_belum_ada: ["Data pegawai bulan ini belum diarsipkan", "text-amber-800 bg-amber-50"],
    tidak_ada_data: ["Tidak ada data", "text-gray-500 bg-gray-100"],
};

// Pilihan jenis perbandingan, dengan penjelasan yang mudah dipahami.
const MODE = {
    kondisi: {
        nama: "Hasil akhir saja",
        isi: "Membandingkan data setiap pegawai di bulan awal dengan di bulan akhir. Kalau berubah lalu kembali seperti semula, tidak dihitung.",
        satuan: "pegawai",
    },
    kejadian: {
        nama: "Semua perubahan tiap bulan",
        isi: "Mencatat setiap perubahan dari bulan ke bulan, termasuk yang kemudian kembali seperti semula.",
        satuan: "kejadian",
    },
};

const ARAH = {
    naik: { kelas: "bg-green-100 text-green-800", simbol: "▲" },
    turun: { kelas: "bg-red-100 text-red-700", simbol: "▼" },
    lain: { kelas: "bg-gray-100 text-gray-600", simbol: "●" },
};

const fmt = (n) => (n === null || n === undefined ? "–" : Number(n).toLocaleString("id-ID"));
const fmtSelisih = (n) => (n > 0 ? `+${fmt(n)}` : fmt(n));

function Kartu({ judul, nilai, catatan, warna = "text-gray-800" }) {
    return (
        <div className="border rounded-lg p-3 bg-white">
            <div className="text-xs text-gray-500">{judul}</div>
            <div className={`text-2xl font-bold tabular-nums ${warna}`}>{fmt(nilai)}</div>
            {catatan && <div className="text-[11px] text-gray-400 mt-0.5">{catatan}</div>}
        </div>
    );
}

function Judul({ children, keterangan }) {
    return (
        <div className="mb-2">
            <h4 className="font-semibold text-sm text-gray-800">{children}</h4>
            {keterangan && <p className="text-xs text-gray-500">{keterangan}</p>}
        </div>
    );
}

function BadgeArah({ arah, label }) {
    const a = ARAH[arah] ?? ARAH.lain;
    return (
        <span className={`inline-flex items-center gap-1 rounded px-2 py-0.5 text-[11px] font-semibold ${a.kelas}`}>
            <span aria-hidden="true">{a.simbol}</span> {label}
        </span>
    );
}

// ---------------------------------------------------------------------
// Ringkasan angka
// ---------------------------------------------------------------------
function BlokRingkasan({ data, mode }) {
    if (!data) return null;
    const m = MODE[mode];

    return (
        <div className="mb-5">
            <Judul keterangan={m.isi}>Ringkasan: {m.nama.toLowerCase()}</Judul>
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <Kartu judul="Pegawai yang datanya berubah" nilai={data.pegawai_unik_berubah} catatan="orangnya dihitung sekali" />
                <Kartu judul="Jumlah perubahan data" nilai={data.kejadian_perubahan} catatan="satu pegawai bisa lebih dari satu" />
                <Kartu judul="Naik status" nilai={data.naik_status} warna="text-green-700" catatan="mis. PPPK Paruh Waktu ke PPPK" />
                <Kartu judul="Turun status" nilai={data.turun_status} warna="text-red-600" catatan="mis. PNS ke PPPK" />
                <Kartu judul="Perlu dicek" nilai={data.perlu_verifikasi} warna="text-amber-700" catatan={`${fmt(data.pegawai_perlu_verifikasi)} pegawai`} />
                <Kartu judul="Baru muncul di data" nilai={data.muncul} catatan="belum ada di bulan awal" />
            </div>
            <p className="text-[11px] text-gray-500 mt-2">
                Tidak ada lagi di data bulan akhir: <b>{fmt(data.tidak_ditemukan)}</b> pegawai. Ini belum tentu berarti keluar
                (bisa pensiun, pindah, atau NIP diperbaiki).
            </p>
            {data.perpindahan_jabatan?.length > 0 && (
                <p className="mt-2 text-xs text-gray-700">
                    <b>Pindah jenis jabatan:</b>{" "}
                    {data.perpindahan_jabatan.map((p) => `${p.dari} → ${p.ke}: ${fmt(p.jumlah)} pegawai`).join("; ")}
                </p>
            )}
        </div>
    );
}

// ---------------------------------------------------------------------
// Kenaikan / penurunan status (PPPK Paruh Waktu, PPPK, PNS)
// ---------------------------------------------------------------------
function BlokStatus({ data, komposisi, mode, awal, akhir, aktif, onLihat, onBersih }) {
    if (!data) return null;
    const satuan = MODE[mode].satuan;
    const daftar = data.perpindahan_status ?? [];

    return (
        <div className="mb-5 border rounded-lg p-4 bg-white">
            <Judul keterangan="Berpindahnya status pegawai antar bulan. Urutan tingkatnya: PPPK Paruh Waktu → PPPK → PNS. Pindah ke tingkat yang lebih tinggi disebut naik status.">
                Perubahan status kepegawaian
            </Judul>

            {komposisi && (
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                    {komposisi.akhir.map((k, i) => {
                        const jumlahAwal = komposisi.awal[i]?.jumlah ?? 0;
                        const selisih = k.jumlah - jumlahAwal;
                        return (
                            <div key={k.kode} className="border rounded-lg p-3">
                                <div className="text-xs text-gray-500">{k.label} (aktif)</div>
                                <div className="text-sm text-gray-700 tabular-nums">
                                    {fmt(jumlahAwal)} <ArrowRight size={12} className="inline" /> <b className="text-lg">{fmt(k.jumlah)}</b>
                                </div>
                                <div className={`text-[11px] ${selisih > 0 ? "text-green-700" : selisih < 0 ? "text-red-600" : "text-gray-400"}`}>
                                    {selisih === 0 ? "tetap" : `${fmtSelisih(selisih)} dibanding ${formatPeriodeLabel(awal)}`}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            {daftar.length === 0 ? (
                <p className="text-xs text-gray-500">
                    Tidak ada pegawai yang berpindah status antara {formatPeriodeLabel(awal)} dan {formatPeriodeLabel(akhir)}.
                </p>
            ) : (
                <div className="overflow-x-auto border rounded-lg">
                    <table className="w-full text-xs">
                        <thead className="bg-gray-50 text-gray-600">
                            <tr>
                                <th className="px-3 py-2 text-left">Jenis</th>
                                <th className="px-3 py-2 text-left">Dari</th>
                                <th className="px-3 py-2 text-left">Menjadi</th>
                                <th className="px-3 py-2 text-right">Jumlah {satuan}</th>
                                <th className="px-3 py-2 text-left">Nama-nama</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {daftar.map((p) => {
                                const dipilih = aktif && aktif.dari === p.dari_kode && aktif.ke === p.ke_kode;
                                return (
                                    <tr key={`${p.dari_kode}-${p.ke_kode}`} className={dipilih ? "bg-green-50" : ""}>
                                        <td className="px-3 py-1.5"><BadgeArah arah={p.arah} label={p.arah_label} /></td>
                                        <td className="px-3 py-1.5">{p.dari ?? "(kosong)"}</td>
                                        <td className="px-3 py-1.5 font-semibold">{p.ke ?? "(kosong)"}</td>
                                        <td className="px-3 py-1.5 text-right tabular-nums font-semibold">{fmt(p.jumlah)}</td>
                                        <td className="px-3 py-1.5">
                                            {dipilih ? (
                                                <button className="text-gray-600 underline" onClick={onBersih}>Tampilkan semua lagi</button>
                                            ) : (
                                                <button className="text-[#006A4E] underline" onClick={() => onLihat(p)}>Lihat nama</button>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

// ---------------------------------------------------------------------
// Rekap tiap bulan
// ---------------------------------------------------------------------
function TabelDeret({ judul, keterangan, baris }) {
    return (
        <div className="mb-5">
            <Judul keterangan={keterangan}>{judul}</Judul>
            <div className="overflow-x-auto border rounded-lg">
                <table className="w-full text-xs">
                    <thead className="bg-gray-50 text-gray-600">
                        <tr>
                            <th className="px-3 py-2 text-left">Bulan</th>
                            <th className="px-3 py-2 text-left">Dibanding</th>
                            <th className="px-3 py-2 text-right">Pegawai berubah</th>
                            <th className="px-3 py-2 text-right">Jumlah perubahan</th>
                            <th className="px-3 py-2 text-right">Naik status</th>
                            <th className="px-3 py-2 text-right">Turun status</th>
                            <th className="px-3 py-2 text-right">Perlu dicek</th>
                            <th className="px-3 py-2 text-left">Catatan</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {baris.map((b) =>
                            b.status === "tersedia" ? (
                                <tr key={b.periode}>
                                    <td className="px-3 py-1.5 font-medium">{formatPeriodeLabel(b.periode)}</td>
                                    <td className="px-3 py-1.5">{formatPeriodeLabel(b.dibandingkan_dengan)}</td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">{fmt(b.pegawai_unik_berubah)}</td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">{fmt(b.kejadian_perubahan)}</td>
                                    <td className="px-3 py-1.5 text-right tabular-nums text-green-700">{fmt(b.naik_status)}</td>
                                    <td className="px-3 py-1.5 text-right tabular-nums text-red-600">{fmt(b.turun_status)}</td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">{fmt(b.perlu_verifikasi)}</td>
                                    <td className="px-3 py-1.5 text-amber-700">
                                        {b.bulan_terlewati > 0 ? `${b.bulan_terlewati} bulan di antaranya tidak ada data` : ""}
                                    </td>
                                </tr>
                            ) : (
                                <tr key={b.periode} className="text-gray-400">
                                    <td className="px-3 py-1.5">{formatPeriodeLabel(b.periode)}</td>
                                    <td className="px-3 py-1.5" colSpan={7}>
                                        {b.status === "dasar"
                                            ? "Bulan awal (patokan pembanding)"
                                            : "Tidak bisa dibandingkan karena data bulan ini tidak ada (bukan berarti tidak ada perubahan)"}
                                    </td>
                                </tr>
                            )
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------
// Riwayat satu pegawai
// ---------------------------------------------------------------------
function DetailPegawai({ kunci, awal, akhir, onTutup }) {
    const [data, setData] = useState(null);
    const [error, setError] = useState("");

    useEffect(() => {
        let batal = false;
        (async () => {
            try {
                const qs = new URLSearchParams({ kunci, awal, akhir });
                const res = await apiFetch(`/indeks-kumulatif/perubahan/pegawai?${qs}`);
                const json = await res.json();
                if (!res.ok) throw new Error(json.message || "Gagal memuat riwayat pegawai.");
                if (!batal) setData(json);
            } catch (e) {
                if (!batal) setError(e.message);
            }
        })();
        return () => {
            batal = true;
        };
    }, [kunci, awal, akhir]);

    const ringkas = (n) =>
        n
            ? [n.kategori_jabatan, n.golongan, n.unit || n.instansi, labelStatus(n.status_kepegawaian)].filter(Boolean).join(" · ")
            : "";

    return (
        <div className="fixed inset-0 z-50 bg-black/40 flex justify-end" onClick={onTutup}>
            <div className="bg-white w-full max-w-xl h-full overflow-y-auto p-5" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between mb-3">
                    <div>
                        <h3 className="text-lg font-bold">{data?.nama ?? "Riwayat pegawai"}</h3>
                        <div className="text-xs text-gray-500">NIP: {data?.nip ?? kunci}</div>
                    </div>
                    <button onClick={onTutup} aria-label="Tutup"><X size={18} /></button>
                </div>

                {error && <div className="text-red-700 bg-red-50 p-3 rounded text-sm">{error}</div>}
                {!data && !error && <LoaderCircle className="animate-spin mx-auto my-8" size={20} />}

                {data && (
                    <>
                        <Judul keterangan="Keadaan pegawai ini pada setiap bulan.">Riwayat tiap bulan</Judul>
                        <ol className="border-l-2 border-gray-200 ml-2 mb-4">
                            {data.garis_waktu.map((g) => (
                                <li key={g.periode} className="pl-3 pb-2 text-xs">
                                    <b>{formatPeriodeLabel(g.periode)}</b>:{" "}
                                    {g.status === "ada" ? (
                                        ringkas(g.nilai)
                                    ) : g.status === "tidak_ditemukan" ? (
                                        <span className="text-amber-700">Tidak ada di data bulan ini</span>
                                    ) : (
                                        <span className="text-gray-400">Data bulan ini tidak tersedia</span>
                                    )}
                                </li>
                            ))}
                        </ol>

                        <Judul keterangan={`Dibanding ${formatPeriodeLabel(awal)} dengan ${formatPeriodeLabel(akhir)}.`}>
                            Apa saja yang berbeda
                        </Judul>
                        {data.kondisi.length === 0 ? (
                            <p className="text-xs text-gray-500 mb-4">Tidak ada perbedaan antara bulan awal dan bulan akhir.</p>
                        ) : (
                            <ul className="text-xs mb-4 space-y-1">
                                {data.kondisi.map((k) => (
                                    <li key={k.atribut}>
                                        <b>{k.atribut_label}</b>: {k.nilai_sebelum ?? "(kosong)"} → {k.nilai_sesudah ?? "(kosong)"}
                                        {k.tanggal_efektif && <span className="text-gray-500"> (berlaku sejak {k.tanggal_efektif})</span>}
                                    </li>
                                ))}
                            </ul>
                        )}

                        <Judul keterangan="Perubahan yang terjadi dari satu bulan ke bulan berikutnya.">Catatan perubahan</Judul>
                        {data.kejadian.length === 0 ? (
                            <p className="text-xs text-gray-500">Tidak ada perubahan tercatat.</p>
                        ) : (
                            <ul className="text-xs space-y-1.5">
                                {data.kejadian.map((k) => (
                                    <li key={k.id} className="border rounded p-2">
                                        <div className="text-gray-500">
                                            {formatPeriodeLabel(k.periode_dari)} → {formatPeriodeLabel(k.periode_ke)}
                                            {k.tanggal_efektif && ` · berlaku sejak ${k.tanggal_efektif}`}
                                        </div>
                                        <div><b>{k.atribut_label}</b>: {k.nilai_sebelum ?? "(kosong)"} → {k.nilai_sesudah ?? "(kosong)"}</div>
                                        {k.perlu_verifikasi && (
                                            <div className="text-amber-700">Perlu dicek: {k.alasan_verifikasi}</div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}

// Baris untuk pegawai yang hilang/muncul ditulis dengan kalimat biasa.
function apaYangBerubah(r) {
    if (r.jenis === "tidak_ditemukan") return "Tidak ada lagi di data bulan akhir";
    if (r.jenis === "muncul") return "Baru muncul di data";
    return r.atribut_label;
}

// ---------------------------------------------------------------------
// Halaman
// ---------------------------------------------------------------------
export default function PerubahanIndividual({ periodeOpsi }) {
    const periodeAda = useMemo(() => periodeOpsi.filter((p) => p.snapshot_individual).map((p) => p.periode), [periodeOpsi]);

    const [awal, setAwal] = useState("");
    const [akhir, setAkhir] = useState("");
    const [mode, setMode] = useState("kondisi");
    const [kategori, setKategori] = useState("");
    const [unit, setUnit] = useState("");
    const [verifikasi, setVerifikasi] = useState(false);
    const [filterStatus, setFilterStatus] = useState(null); // { dari, ke, teks }
    const [q, setQ] = useState("");
    const [qTerapan, setQTerapan] = useState("");
    const [halaman, setHalaman] = useState(1);

    const [ringkasan, setRingkasan] = useState(null);
    const [deret, setDeret] = useState(null);
    const [daftar, setDaftar] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const [exporting, setExporting] = useState(false);
    const [detail, setDetail] = useState(null);
    const daftarRef = useRef(null);

    // Bawaan: data pegawai paling awal dan paling akhir yang tersedia.
    useEffect(() => {
        if (!awal && !akhir && periodeAda.length >= 2) {
            setAwal(periodeAda[0]);
            setAkhir(periodeAda[periodeAda.length - 1]);
        }
    }, [periodeAda, awal, akhir]);

    const siap = awal && akhir && akhir > awal;

    const filterQs = useMemo(() => {
        const p = new URLSearchParams({ awal, akhir });
        if (kategori) p.set("kategori", kategori);
        if (unit) p.set("unit", unit);
        if (verifikasi) p.set("verifikasi", "1");
        if (qTerapan) p.set("q", qTerapan);
        if (filterStatus) {
            p.set("atribut", "status_kepegawaian");
            if (filterStatus.dari) p.set("dari", filterStatus.dari);
            if (filterStatus.ke) p.set("ke", filterStatus.ke);
        }
        return p;
    }, [awal, akhir, kategori, unit, verifikasi, qTerapan, filterStatus]);

    useEffect(() => setHalaman(1), [awal, akhir, mode, kategori, unit, verifikasi, qTerapan, filterStatus]);

    useEffect(() => {
        if (!siap) {
            setRingkasan(null);
            setDeret(null);
            setDaftar(null);
            return;
        }
        let batal = false;
        (async () => {
            setLoading(true);
            setError("");
            try {
                const baca = async (path) => {
                    const res = await apiFetch(path);
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok) throw new Error(json.message || Object.values(json.errors ?? {})[0]?.[0] || "Gagal memuat data.");
                    return json;
                };
                const lis = new URLSearchParams(filterQs);
                lis.set("mode", mode);
                lis.set("page", String(halaman));
                lis.set("per_page", "50");

                // Ringkasan dan rekap bulanan tidak ikut disaring status, supaya
                // tabel perubahan status tetap utuh saat satu baris dipilih.
                const dasar = new URLSearchParams(filterQs);
                ["atribut", "dari", "ke"].forEach((k) => dasar.delete(k));

                const [r, d, l] = await Promise.all([
                    baca(`/indeks-kumulatif/perubahan/ringkasan?${dasar}`),
                    baca(`/indeks-kumulatif/perubahan/deret?${dasar}`),
                    baca(`/indeks-kumulatif/perubahan?${lis}`),
                ]);
                if (!batal) {
                    setRingkasan(r);
                    setDeret(d);
                    setDaftar(l);
                }
            } catch (e) {
                if (!batal) setError(e.message);
            } finally {
                if (!batal) setLoading(false);
            }
        })();
        return () => {
            batal = true;
        };
    }, [siap, filterQs, mode, halaman]);

    async function handleExport() {
        setExporting(true);
        setError("");
        try {
            const qs = new URLSearchParams(filterQs);
            qs.set("mode", mode);
            const res = await apiFetch(`/indeks-kumulatif/perubahan/export?${qs}`);
            if (!res.ok) {
                const j = await res.json().catch(() => ({}));
                throw new Error(j.message || "Gagal mengekspor perubahan individual.");
            }
            const blob = await res.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = `perubahan-individual-${awal}-sd-${akhir}.xlsx`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        } catch (e) {
            setError(e.message);
        } finally {
            setExporting(false);
        }
    }

    function lihatNama(p) {
        setFilterStatus({ dari: p.dari_kode, ke: p.ke_kode, teks: `${p.dari ?? "(kosong)"} → ${p.ke ?? "(kosong)"}` });
        setTimeout(() => daftarRef.current?.scrollIntoView({ behavior: "smooth", block: "start" }), 150);
    }

    const opsiPeriode = periodeOpsi.map((p) => (
        <option key={p.periode} value={p.periode}>
            {formatPeriodeLabel(p.periode)}
            {p.snapshot_individual ? "" : " (data pegawai belum diarsipkan)"}
        </option>
    ));
    const tampilBelumSiap = periodeAda.length < 2;
    const dataMode = ringkasan?.[mode];

    return (
        <div>
            <details open className="text-xs text-gray-700 bg-gray-50 border rounded-lg p-3 mb-4">
                <summary className="font-semibold cursor-pointer">Cara membaca halaman ini</summary>
                <div className="mt-2 space-y-2">
                    <p>
                        Halaman ini menjawab: <b>siapa saja yang datanya berubah</b> antara dua bulan yang Anda pilih,
                        dan <b>apa yang berubah</b>: jabatan, pangkat, unit kerja, pendidikan, atau status
                        (PNS, PPPK, PPPK Paruh Waktu). Pegawai dicocokkan lewat NIP, bukan nama.
                    </p>
                    <ul className="list-disc pl-5 space-y-1">
                        <li>
                            <b>Naik status</b> berarti pegawai pindah ke tingkat lebih tinggi, misalnya PPPK Paruh Waktu → PPPK
                            atau PPPK → PNS. <b>Turun status</b> adalah kebalikannya.
                        </li>
                        <li>
                            <b>Perlu dicek</b> berarti sistem ragu: misalnya data sebelumnya kosong lalu terisi (bisa jadi hanya
                            dilengkapi, bukan perubahan sungguhan), atau ada bulan yang tidak punya data di antaranya.
                        </li>
                        <li>
                            Angka di sini menghitung <b>orang per orang</b>. Ini berbeda dengan tab “Jumlah per Kategori”
                            yang hanya menghitung total tiap bulan.
                        </li>
                    </ul>
                    <p className="text-gray-500">
                        Contoh: Budi berstatus PPPK Paruh Waktu di Januari dan PPPK di Maret. Maka pada perbandingan
                        Januari ke Maret, Budi dihitung satu kali sebagai <b>naik status</b> (PPPK Paruh Waktu → PPPK).
                    </p>
                </div>
            </details>

            {tampilBelumSiap && (
                <div className="flex items-start gap-3 text-amber-800 bg-amber-50 p-3 rounded-lg text-sm mb-4">
                    <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                    <span>
                        Baru {periodeAda.length} bulan yang data pegawainya sudah diarsipkan. Perbandingan butuh minimal dua
                        bulan. Arsip dibuat otomatis setiap import selesai (untuk bulan berjalan bisa dibuat dengan perintah{" "}
                        <code>php artisan pegawai:snapshot</code>).
                    </span>
                </div>
            )}

            <div className="flex flex-wrap items-end gap-3 mb-3">
                <label className="text-xs font-medium text-gray-700">
                    Dari bulan
                    <select value={awal} onChange={(e) => setAwal(e.target.value)} className="block border p-2 rounded text-sm bg-white min-w-[10rem]">
                        <option value="">Pilih…</option>
                        {opsiPeriode}
                    </select>
                </label>
                <label className="text-xs font-medium text-gray-700">
                    Sampai bulan
                    <select value={akhir} onChange={(e) => setAkhir(e.target.value)} className="block border p-2 rounded text-sm bg-white min-w-[10rem]">
                        <option value="">Pilih…</option>
                        {opsiPeriode}
                    </select>
                </label>
                <label className="text-xs font-medium text-gray-700">
                    Yang berubah
                    <select value={kategori} onChange={(e) => setKategori(e.target.value)} className="block border p-2 rounded text-sm bg-white max-w-[16rem]">
                        <option value="">Semua jenis data</option>
                        {Object.entries(KATEGORI).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                </label>
                <label className="text-xs font-medium text-gray-700">
                    Unit kerja
                    <select value={unit} onChange={(e) => setUnit(e.target.value)} className="block border p-2 rounded text-sm bg-white max-w-[14rem]">
                        <option value="">Semua</option>
                        {(ringkasan?.opsi_unit ?? []).map((u) => (
                            <option key={u} value={u}>{u}</option>
                        ))}
                    </select>
                </label>
                <form
                    className="flex items-end gap-1"
                    onSubmit={(e) => {
                        e.preventDefault();
                        setQTerapan(q.trim());
                    }}
                >
                    <label className="text-xs font-medium text-gray-700">
                        Cari NIP / nama
                        <input value={q} onChange={(e) => setQ(e.target.value)} className="block border p-2 rounded text-sm w-44" placeholder="Ketik lalu Enter" />
                    </label>
                    <button type="submit" className="border p-2 rounded" aria-label="Cari"><Search size={16} /></button>
                </form>
                <label className="flex items-center gap-1 text-xs text-gray-700 pb-2">
                    <input type="checkbox" checked={verifikasi} onChange={(e) => setVerifikasi(e.target.checked)} />
                    Hanya yang perlu dicek
                </label>
                <button
                    onClick={handleExport}
                    disabled={!siap || exporting || !ringkasan?.dapat_dibandingkan}
                    className="flex items-center gap-2 bg-[#006A4E] text-white px-4 py-2 rounded text-sm font-semibold disabled:opacity-60 disabled:cursor-not-allowed hover:bg-[#005a41]"
                >
                    {exporting ? <LoaderCircle className="animate-spin" size={16} /> : <Download size={16} />}
                    Export Excel
                </button>
            </div>

            <div className="flex flex-wrap gap-2 mb-4">
                {Object.entries(MODE).map(([k, m]) => (
                    <button
                        key={k}
                        onClick={() => setMode(k)}
                        className={`text-left rounded-lg border px-3 py-2 max-w-sm ${mode === k ? "border-[#006A4E] bg-[#E7F1FB]" : "bg-white hover:bg-gray-50"}`}
                    >
                        <div className={`text-sm font-semibold ${mode === k ? "text-[#006A4E]" : "text-gray-700"}`}>{m.nama}</div>
                        <div className="text-[11px] text-gray-500">{m.isi}</div>
                    </button>
                ))}
            </div>

            {awal && akhir && akhir <= awal && (
                <div className="text-amber-800 bg-amber-50 p-3 rounded-lg text-sm mb-4">Bulan “sampai” harus setelah bulan “dari”.</div>
            )}
            {error && (
                <div className="flex items-center gap-3 text-red-700 bg-red-50 p-3 rounded-lg text-sm mb-4">
                    <AlertTriangle size={16} /> {error}
                </div>
            )}
            {loading && (
                <div className="flex items-center justify-center gap-2 text-gray-500 py-8 text-sm">
                    <LoaderCircle className="animate-spin" size={18} /> Memuat data...
                </div>
            )}

            {ringkasan && !loading && (
                <>
                    <div className="mb-4">
                        <Judul keterangan="Bulan yang datanya belum lengkap tidak bisa dibandingkan, dan tidak dikarang.">
                            Data yang tersedia
                        </Judul>
                        <div className="flex flex-wrap gap-2">
                            {ringkasan.status_periode.map((s) => {
                                const [label, kelas] = STATUS_PERIODE[s.status];
                                return (
                                    <div key={s.periode} className={`text-[11px] rounded px-2 py-1 ${kelas}`} title={s.keterangan}>
                                        <b>{formatPeriodeLabel(s.periode)}</b>: {label}
                                        {s.status === "tersedia" && (
                                            <> · {fmt(s.jumlah_pegawai)} pegawai · {fmt(s.jumlah_perlu_verifikasi)} perlu dicek
                                                {s.jumlah_koreksi > 0 ? ` · diperbarui ${s.jumlah_koreksi}×` : ""}</>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {ringkasan.pesan && (
                        <div className="flex items-start gap-3 text-amber-800 bg-amber-50 p-3 rounded-lg text-sm mb-4">
                            <AlertTriangle size={16} className="mt-0.5 shrink-0" /> {ringkasan.pesan}
                        </div>
                    )}

                    {ringkasan.dapat_dibandingkan && (
                        <>
                            <BlokRingkasan data={dataMode} mode={mode} />
                            <BlokStatus
                                data={dataMode}
                                komposisi={ringkasan.komposisi_status}
                                mode={mode}
                                awal={awal}
                                akhir={akhir}
                                aktif={filterStatus}
                                onLihat={lihatNama}
                                onBersih={() => setFilterStatus(null)}
                            />
                            {ringkasan.bulan_tanpa_snapshot?.length > 0 && (
                                <p className="text-xs text-amber-700 mb-3">
                                    Bulan yang tidak punya data di rentang ini:{" "}
                                    {ringkasan.bulan_tanpa_snapshot.map(formatPeriodeLabel).join(", ")}. Kapan persisnya perubahan
                                    terjadi pada rentang tersebut tidak bisa dipastikan.
                                </p>
                            )}
                        </>
                    )}
                </>
            )}

            {deret && !loading && ringkasan?.dapat_dibandingkan && (
                <>
                    <TabelDeret
                        judul="Rekap tiap bulan: dibanding bulan sebelumnya"
                        keterangan="Berapa pegawai yang berubah di setiap bulan, dihitung dari bulan tepat sebelumnya."
                        baris={deret.berurutan}
                    />
                    <TabelDeret
                        judul={`Rekap menumpuk: dibanding ${formatPeriodeLabel(deret.awal)}`}
                        keterangan={`Setiap baris membandingkan bulan itu dengan ${formatPeriodeLabel(deret.awal)} (bulan awal), jadi angkanya mencerminkan seluruh perubahan sejak awal.`}
                        baris={deret.kumulatif}
                    />
                </>
            )}

            {daftar && !loading && daftar.dapat_dibandingkan && (
                <div ref={daftarRef}>
                    <Judul keterangan="Klik nama pegawai untuk melihat riwayatnya bulan demi bulan.">
                        Daftar nama ({fmt(daftar.total)})
                    </Judul>

                    {filterStatus && (
                        <div className="inline-flex items-center gap-2 bg-green-50 border border-green-200 text-green-800 rounded px-3 py-1 text-xs mb-2">
                            Hanya yang berpindah status: <b>{filterStatus.teks}</b>
                            <button onClick={() => setFilterStatus(null)} aria-label="Hapus saringan status"><X size={14} /></button>
                        </div>
                    )}

                    <div className="overflow-x-auto border rounded-lg">
                        <table className="w-full text-xs">
                            <thead className="bg-gray-50 text-gray-600">
                                <tr>
                                    {["NIP", "Nama", "Unit kerja", "Yang berubah", "Sebelumnya", "Sesudahnya", "Bulan", "Berlaku sejak", "Keterangan"].map((h) => (
                                        <th key={h} className="px-2 py-2 text-left font-semibold whitespace-nowrap">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {daftar.data.length === 0 && (
                                    <tr><td colSpan={9} className="px-3 py-6 text-center text-gray-500">Tidak ada perubahan dengan saringan ini.</td></tr>
                                )}
                                {daftar.data.map((r, i) => {
                                    const hilangMuncul = r.jenis === "tidak_ditemukan" || r.jenis === "muncul";
                                    return (
                                        <tr key={`${r.kunci}-${r.atribut}-${r.periode_dari}-${i}`} className="hover:bg-gray-50">
                                            <td className="px-2 py-1.5 whitespace-nowrap">{r.nip ?? "–"}</td>
                                            <td className="px-2 py-1.5">
                                                <button className="text-[#006A4E] underline text-left" onClick={() => setDetail(r.kunci)}>{r.nama}</button>
                                            </td>
                                            <td className="px-2 py-1.5">{r.unit ?? "–"}</td>
                                            <td className="px-2 py-1.5">{apaYangBerubah(r)}</td>
                                            <td className="px-2 py-1.5">{hilangMuncul ? "–" : r.nilai_sebelum ?? "(kosong)"}</td>
                                            <td className="px-2 py-1.5">{hilangMuncul ? "–" : r.nilai_sesudah ?? "(kosong)"}</td>
                                            <td className="px-2 py-1.5 whitespace-nowrap" title={r.sumber ?? ""}>
                                                {formatPeriodeLabel(r.periode_dari)} → {formatPeriodeLabel(r.periode_ke)}
                                            </td>
                                            <td className="px-2 py-1.5 whitespace-nowrap">{r.tanggal_efektif ?? "–"}</td>
                                            <td className="px-2 py-1.5">
                                                {r.perlu_verifikasi ? (
                                                    <span className="text-amber-700">
                                                        <b>Perlu dicek</b>
                                                        {r.alasan_verifikasi && <span className="block text-[11px]">{r.alasan_verifikasi}</span>}
                                                    </span>
                                                ) : (
                                                    <span className="text-green-700">Terdeteksi</span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center justify-between mt-2 text-xs text-gray-600">
                        <span>Halaman {daftar.current_page} dari {daftar.last_page}</span>
                        <div className="flex gap-2">
                            <button disabled={halaman <= 1} onClick={() => setHalaman(halaman - 1)} className="border px-3 py-1 rounded disabled:opacity-50">Sebelumnya</button>
                            <button disabled={halaman >= daftar.last_page} onClick={() => setHalaman(halaman + 1)} className="border px-3 py-1 rounded disabled:opacity-50">Berikutnya</button>
                        </div>
                    </div>
                </div>
            )}

            {detail && <DetailPegawai kunci={detail} awal={awal} akhir={akhir} onTutup={() => setDetail(null)} />}
        </div>
    );
}