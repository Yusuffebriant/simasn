import { useEffect, useMemo, useState } from "react";
import { LoaderCircle, AlertTriangle, Download, X, Search } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { formatPeriodeLabel } from "../../lib/periode";

const KATEGORI = {
    jabatan: "Jabatan",
    pangkat_golongan: "Pangkat/golongan",
    unit_kerja: "Unit kerja",
    pendidikan: "Pendidikan",
    status_kepegawaian: "Status kepegawaian",
    identitas: "Identitas",
    data_pribadi: "Data pribadi",
    keberadaan: "Keberadaan di snapshot",
};

const STATUS_PERIODE = {
    tersedia: ["Snapshot tersedia", "text-green-700 bg-green-50"],
    snapshot_individual_belum_ada: ["Snapshot individual belum ada", "text-amber-800 bg-amber-50"],
    tidak_ada_data: ["Tidak ada data", "text-gray-500 bg-gray-100"],
};

const fmt = (n) => (n === null || n === undefined ? "–" : Number(n).toLocaleString("id-ID"));

function Kartu({ judul, nilai, catatan, warna = "text-gray-800" }) {
    return (
        <div className="border rounded-lg p-3 bg-white">
            <div className="text-xs text-gray-500">{judul}</div>
            <div className={`text-2xl font-bold tabular-nums ${warna}`}>{fmt(nilai)}</div>
            {catatan && <div className="text-[11px] text-gray-400 mt-0.5">{catatan}</div>}
        </div>
    );
}

function BlokRingkasan({ judul, deskripsi, data }) {
    if (!data) return null;
    return (
        <div className="mb-4">
            <div className="font-semibold text-sm text-gray-800">{judul}</div>
            <div className="text-xs text-gray-500 mb-2">{deskripsi}</div>
            <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                <Kartu judul="Pegawai unik berubah" nilai={data.pegawai_unik_berubah} />
                <Kartu judul="Kejadian perubahan" nilai={data.kejadian_perubahan} catatan="per atribut" />
                <Kartu judul="Atribut berubah" nilai={data.atribut_berubah} catatan="jenis atribut" />
                <Kartu judul="Perlu verifikasi" nilai={data.perlu_verifikasi} warna="text-amber-700" catatan={`${fmt(data.pegawai_perlu_verifikasi)} pegawai`} />
                <Kartu judul="Tidak ditemukan" nilai={data.tidak_ditemukan} catatan="belum tentu keluar" />
                <Kartu judul="Muncul di snapshot" nilai={data.muncul} />
            </div>
            {data.perpindahan_jabatan?.length > 0 && (
                <div className="mt-2 text-xs text-gray-700">
                    <b>Perpindahan kategori jabatan (terkonfirmasi, dari data individual):</b>{" "}
                    {data.perpindahan_jabatan.map((p) => `${p.dari} → ${p.ke}: ${fmt(p.jumlah)} pegawai`).join("; ")}
                </div>
            )}
        </div>
    );
}

function TabelDeret({ judul, baris }) {
    return (
        <div className="mb-4">
            <div className="font-semibold text-sm mb-1">{judul}</div>
            <div className="overflow-x-auto border rounded-lg">
                <table className="w-full text-xs">
                    <thead className="bg-gray-50 text-gray-600">
                        <tr>
                            <th className="px-3 py-2 text-left">Periode</th>
                            <th className="px-3 py-2 text-left">Dibanding</th>
                            <th className="px-3 py-2 text-right">Pegawai unik</th>
                            <th className="px-3 py-2 text-right">Kejadian</th>
                            <th className="px-3 py-2 text-right">Atribut</th>
                            <th className="px-3 py-2 text-right">Verifikasi</th>
                            <th className="px-3 py-2 text-left">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {baris.map((b) =>
                            b.status === "tersedia" ? (
                                <tr key={b.periode}>
                                    <td className="px-3 py-1.5">{formatPeriodeLabel(b.periode)}</td>
                                    <td className="px-3 py-1.5">{formatPeriodeLabel(b.dibandingkan_dengan)}</td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">{fmt(b.pegawai_unik_berubah)}</td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">{fmt(b.kejadian_perubahan)}</td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">{fmt(b.atribut_berubah)}</td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">{fmt(b.perlu_verifikasi)}</td>
                                    <td className="px-3 py-1.5 text-amber-700">
                                        {b.bulan_terlewati > 0 ? `${b.bulan_terlewati} bulan di antaranya tanpa snapshot` : ""}
                                    </td>
                                </tr>
                            ) : (
                                <tr key={b.periode} className="text-gray-400">
                                    <td className="px-3 py-1.5">{formatPeriodeLabel(b.periode)}</td>
                                    <td className="px-3 py-1.5" colSpan={6}>
                                        {b.status === "dasar"
                                            ? "Periode dasar"
                                            : "Perbandingan tidak tersedia (bukan berarti tidak ada perubahan)"}
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
            ? [n.kategori_jabatan, n.golongan, n.unit || n.instansi, n.status_kepegawaian].filter(Boolean).join(" · ")
            : "";

    return (
        <div className="fixed inset-0 z-50 bg-black/40 flex justify-end" onClick={onTutup}>
            <div className="bg-white w-full max-w-xl h-full overflow-y-auto p-5" onClick={(e) => e.stopPropagation()}>
                <div className="flex items-start justify-between mb-3">
                    <div>
                        <h3 className="text-lg font-bold">{data?.nama ?? "Riwayat pegawai"}</h3>
                        <div className="text-xs text-gray-500">NIP/Kunci: {data?.nip ?? kunci}</div>
                    </div>
                    <button onClick={onTutup} aria-label="Tutup"><X size={18} /></button>
                </div>

                {error && <div className="text-red-700 bg-red-50 p-3 rounded text-sm">{error}</div>}
                {!data && !error && <LoaderCircle className="animate-spin mx-auto my-8" size={20} />}

                {data && (
                    <>
                        <div className="font-semibold text-sm mb-1">Kondisi per periode</div>
                        <ol className="border-l-2 border-gray-200 ml-2 mb-4">
                            {data.garis_waktu.map((g) => (
                                <li key={g.periode} className="pl-3 pb-2 text-xs">
                                    <b>{formatPeriodeLabel(g.periode)}</b>:{" "}
                                    {g.status === "ada" ? (
                                        ringkas(g.nilai)
                                    ) : g.status === "tidak_ditemukan" ? (
                                        <span className="text-amber-700">Tidak ditemukan di snapshot periode ini</span>
                                    ) : (
                                        <span className="text-gray-400">Snapshot periode ini tidak tersedia</span>
                                    )}
                                </li>
                            ))}
                        </ol>

                        <div className="font-semibold text-sm mb-1">Perubahan awal → akhir (kondisi)</div>
                        {data.kondisi.length === 0 ? (
                            <p className="text-xs text-gray-500 mb-4">Tidak ada perbedaan kondisi antara periode awal dan akhir.</p>
                        ) : (
                            <ul className="text-xs mb-4 space-y-1">
                                {data.kondisi.map((k) => (
                                    <li key={k.atribut}>
                                        <b>{k.atribut_label}</b>: {k.nilai_sebelum ?? "(kosong)"} → {k.nilai_sesudah ?? "(kosong)"}
                                        {k.tanggal_efektif && <span className="text-gray-500"> (efektif {k.tanggal_efektif})</span>}
                                    </li>
                                ))}
                            </ul>
                        )}

                        <div className="font-semibold text-sm mb-1">Kejadian tercatat di antara periode</div>
                        {data.kejadian.length === 0 ? (
                            <p className="text-xs text-gray-500">Tidak ada kejadian perubahan tercatat.</p>
                        ) : (
                            <ul className="text-xs space-y-1.5">
                                {data.kejadian.map((k) => (
                                    <li key={k.id} className="border rounded p-2">
                                        <div className="text-gray-500">
                                            {formatPeriodeLabel(k.periode_dari)} → {formatPeriodeLabel(k.periode_ke)}
                                            {k.tanggal_efektif && ` · efektif ${k.tanggal_efektif}`}
                                        </div>
                                        <div><b>{k.atribut_label}</b>: {k.nilai_sebelum ?? "(kosong)"} → {k.nilai_sesudah ?? "(kosong)"}</div>
                                        {k.perlu_verifikasi && (
                                            <div className="text-amber-700">Perlu verifikasi: {k.alasan_verifikasi}</div>
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

export default function PerubahanIndividual({ periodeOpsi }) {
    const periodeAda = useMemo(() => periodeOpsi.filter((p) => p.snapshot_individual).map((p) => p.periode), [periodeOpsi]);

    const [awal, setAwal] = useState("");
    const [akhir, setAkhir] = useState("");
    const [mode, setMode] = useState("kondisi");
    const [kategori, setKategori] = useState("");
    const [unit, setUnit] = useState("");
    const [verifikasi, setVerifikasi] = useState(false);
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

    // Bawaan: snapshot individual paling awal dan paling akhir yang ada.
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
        return p;
    }, [awal, akhir, kategori, unit, verifikasi, qTerapan]);

    useEffect(() => setHalaman(1), [awal, akhir, mode, kategori, unit, verifikasi, qTerapan]);

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

                const [r, d, l] = await Promise.all([
                    baca(`/indeks-kumulatif/perubahan/ringkasan?${filterQs}`),
                    baca(`/indeks-kumulatif/perubahan/deret?${filterQs}`),
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

    const opsiPeriode = periodeOpsi.map((p) => (
        <option key={p.periode} value={p.periode}>
            {formatPeriodeLabel(p.periode)}
            {p.snapshot_individual ? "" : " (tanpa snapshot individual)"}
        </option>
    ));
    const tampilBelumSiap = periodeAda.length < 2;

    return (
        <div>
            <div className="text-xs text-gray-600 bg-gray-50 border rounded-lg p-3 mb-4">
                Perubahan dideteksi per pegawai dari snapshot data tiap periode (dicocokkan lewat NIP, bukan nama).
                Angka di sini adalah hitungan pegawai/atribut individual, bukan selisih jumlah agregat di tab Rekap.
                Periode yang belum punya snapshot individual tidak dikarang riwayatnya.
            </div>

            {tampilBelumSiap && (
                <div className="flex items-start gap-3 text-amber-800 bg-amber-50 p-3 rounded-lg text-sm mb-4">
                    <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                    <span>
                        Baru {periodeAda.length} periode yang memiliki snapshot individual. Perbandingan butuh minimal dua
                        periode; snapshot dibuat otomatis setiap import selesai (periode aktif saat ini dapat dibuat dengan
                        perintah <code>php artisan pegawai:snapshot</code>).
                    </span>
                </div>
            )}

            <div className="flex flex-wrap items-end gap-3 mb-4">
                <label className="text-xs font-medium text-gray-700">
                    Periode awal
                    <select value={awal} onChange={(e) => setAwal(e.target.value)} className="block border p-2 rounded text-sm bg-white min-w-[10rem]">
                        <option value="">Pilih…</option>
                        {opsiPeriode}
                    </select>
                </label>
                <label className="text-xs font-medium text-gray-700">
                    Periode akhir
                    <select value={akhir} onChange={(e) => setAkhir(e.target.value)} className="block border p-2 rounded text-sm bg-white min-w-[10rem]">
                        <option value="">Pilih…</option>
                        {opsiPeriode}
                    </select>
                </label>
                <label className="text-xs font-medium text-gray-700">
                    Jenis perbandingan
                    <select value={mode} onChange={(e) => setMode(e.target.value)} className="block border p-2 rounded text-sm bg-white">
                        <option value="kondisi">Kondisi awal vs akhir</option>
                        <option value="kejadian">Semua kejadian di antara periode</option>
                    </select>
                </label>
                <label className="text-xs font-medium text-gray-700">
                    Kategori
                    <select value={kategori} onChange={(e) => setKategori(e.target.value)} className="block border p-2 rounded text-sm bg-white">
                        <option value="">Semua</option>
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
                        NIP / nama
                        <input value={q} onChange={(e) => setQ(e.target.value)} className="block border p-2 rounded text-sm w-44" placeholder="Cari…" />
                    </label>
                    <button type="submit" className="border p-2 rounded" aria-label="Cari"><Search size={16} /></button>
                </form>
                <label className="flex items-center gap-1 text-xs text-gray-700 pb-2">
                    <input type="checkbox" checked={verifikasi} onChange={(e) => setVerifikasi(e.target.checked)} />
                    Hanya perlu verifikasi
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

            {awal && akhir && akhir <= awal && (
                <div className="text-amber-800 bg-amber-50 p-3 rounded-lg text-sm mb-4">Periode akhir harus setelah periode awal.</div>
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
                        <div className="font-semibold text-sm mb-1">Status data</div>
                        <div className="flex flex-wrap gap-2">
                            {ringkasan.status_periode.map((s) => {
                                const [label, kelas] = STATUS_PERIODE[s.status];
                                return (
                                    <div key={s.periode} className={`text-[11px] rounded px-2 py-1 ${kelas}`} title={s.keterangan}>
                                        <b>{formatPeriodeLabel(s.periode)}</b>: {label}
                                        {s.status === "tersedia" && (
                                            <> · {fmt(s.jumlah_pegawai)} pegawai · {fmt(s.jumlah_perlu_verifikasi)} perlu verifikasi
                                                {s.jumlah_koreksi > 0 ? ` · ${s.jumlah_koreksi}× dikoreksi` : ""}</>
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
                            <BlokRingkasan
                                judul="Kondisi awal vs akhir"
                                deskripsi="Membandingkan dua snapshot. Atribut yang berubah lalu kembali ke nilai awal tidak dihitung."
                                data={ringkasan.kondisi}
                            />
                            <BlokRingkasan
                                judul="Semua kejadian di antara periode"
                                deskripsi="Seluruh perubahan pada tiap pasangan periode berurutan; perubahan yang kembali ke nilai awal tetap dihitung."
                                data={ringkasan.kejadian}
                            />
                            {ringkasan.bulan_tanpa_snapshot?.length > 0 && (
                                <p className="text-xs text-amber-700 mb-3">
                                    Bulan tanpa snapshot individual di rentang ini:{" "}
                                    {ringkasan.bulan_tanpa_snapshot.map(formatPeriodeLabel).join(", ")}. Waktu perubahan
                                    pada rentang tersebut tidak dapat dipastikan.
                                </p>
                            )}
                        </>
                    )}
                </>
            )}

            {deret && !loading && ringkasan?.dapat_dibandingkan && (
                <>
                    <TabelDeret judul="Perbandingan berurutan (bulan ke bulan)" baris={deret.berurutan} />
                    <TabelDeret judul={`Perbandingan kumulatif dari ${formatPeriodeLabel(deret.awal)}`} baris={deret.kumulatif} />
                </>
            )}

            {daftar && !loading && daftar.dapat_dibandingkan && (
                <div>
                    <div className="font-semibold text-sm mb-1">
                        {mode === "kondisi" ? "Perubahan kondisi awal → akhir" : "Kejadian perubahan"} ({fmt(daftar.total)})
                    </div>
                    <div className="overflow-x-auto border rounded-lg">
                        <table className="w-full text-xs">
                            <thead className="bg-gray-50 text-gray-600">
                                <tr>
                                    {["NIP", "Nama", "Unit kerja", "Kategori", "Atribut", "Sebelum", "Sesudah", "Periode", "Tgl efektif", "Sumber", "Status"].map((h) => (
                                        <th key={h} className="px-2 py-2 text-left font-semibold whitespace-nowrap">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {daftar.data.length === 0 && (
                                    <tr><td colSpan={11} className="px-3 py-6 text-center text-gray-500">Tidak ada perubahan pada filter ini.</td></tr>
                                )}
                                {daftar.data.map((r, i) => (
                                    <tr key={`${r.kunci}-${r.atribut}-${r.periode_dari}-${i}`} className="hover:bg-gray-50">
                                        <td className="px-2 py-1.5 whitespace-nowrap">{r.nip ?? "–"}</td>
                                        <td className="px-2 py-1.5">
                                            <button className="text-[#006A4E] underline text-left" onClick={() => setDetail(r.kunci)}>{r.nama}</button>
                                        </td>
                                        <td className="px-2 py-1.5">{r.unit ?? "–"}</td>
                                        <td className="px-2 py-1.5">{r.kategori_label}</td>
                                        <td className="px-2 py-1.5">{r.atribut_label}</td>
                                        <td className="px-2 py-1.5">{r.nilai_sebelum ?? "(kosong)"}</td>
                                        <td className="px-2 py-1.5">{r.nilai_sesudah ?? "(kosong)"}</td>
                                        <td className="px-2 py-1.5 whitespace-nowrap">{r.periode_dari} → {r.periode_ke}</td>
                                        <td className="px-2 py-1.5 whitespace-nowrap">{r.tanggal_efektif ?? "–"}</td>
                                        <td className="px-2 py-1.5">{r.sumber ?? "–"}</td>
                                        <td className="px-2 py-1.5">
                                            {r.perlu_verifikasi ? (
                                                <span className="text-amber-700" title={r.alasan_verifikasi ?? ""}>Perlu verifikasi</span>
                                            ) : (
                                                <span className="text-green-700">Terdeteksi</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
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