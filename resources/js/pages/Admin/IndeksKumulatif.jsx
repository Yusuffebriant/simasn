import { useEffect, useMemo, useState } from "react";
import { LoaderCircle, AlertTriangle, Download } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { BULAN_NAMA, formatPeriodeLabel } from "../../lib/periode";

// Hanya lima dimensi yang dibutuhkan. Urutan & key sama dengan
// IndeksKumulatifService::DIMENSI di backend.
const DIMENSI_TABS = [
    { key: "agama", label: "Agama" },
    { key: "pendidikan", label: "Pendidikan" },
    { key: "golongan", label: "Golongan" },
    { key: "jft", label: "JFT" },
    { key: "jfu", label: "JFU" },
];

const MODE_TABS = [
    { key: "jumlah", label: "Jumlah", hint: "Jumlah pegawai per bulan." },
    {
        key: "selisih",
        label: "Selisih per Bulan",
        hint: "Naik/turun dibanding bulan sebelumnya (mis. Februari dibanding Januari).",
    },
    {
        key: "kumulatif",
        label: "Kumulatif",
        hint: "Naik/turun dibanding bulan pertama (Januari) sampai bulan tersebut.",
    },
    {
        key: "indeks",
        label: "Indeks",
        hint: "Dihitung berantai: indeks bulan lalu × (jumlah bulan ini ÷ jumlah bulan lalu). Bulan pertama = 100.",
    },
];

const fmtAngka = (n) => n.toLocaleString("id-ID");
const fmtSelisih = (n) => (n > 0 ? `+${fmtAngka(n)}` : fmtAngka(n));
const fmtIndeks = (n) =>
    n.toLocaleString("id-ID", { minimumFractionDigits: 1, maximumFractionDigits: 1 });

/**
 * Ubah deret jumlah per bulan menjadi nilai tampilan sesuai mode.
 * Bulan tanpa data (null) dilewati: "selisih per bulan" membandingkan dengan
 * bulan terdekat sebelumnya yang punya data, bukan dengan nol.
 */
function hitungDeret(nilai, mode) {
    const awal = nilai.find((v) => v !== null);
    let sebelumnya = null;
    let indeksSebelumnya = null;

    return nilai.map((v) => {
        if (v === null) return null;

        let hasil = null;
        if (mode === "jumlah") hasil = { teks: fmtAngka(v), arah: 0 };
        else if (mode === "selisih") {
            if (sebelumnya !== null) {
                const d = v - sebelumnya;
                hasil = { teks: fmtSelisih(d), arah: Math.sign(d) };
            }
        } else if (mode === "kumulatif") {
            const d = v - awal;
            hasil = { teks: fmtSelisih(d), arah: Math.sign(d) };
        } else if (mode === "indeks" && awal > 0) {
            // Rumus berjalan: indeks bulan ini = indeks bulan lalu x (jumlah
            // bulan ini / jumlah bulan lalu). Bulan pertama = 100. Kalau
            // jumlah bulan lalu 0 (tidak bisa dibagi), dihitung langsung
            // terhadap bulan pertama; hasilnya tetap sama secara matematis.
            let idx;
            if (sebelumnya === null) idx = 100;
            else if (sebelumnya > 0 && indeksSebelumnya !== null) {
                idx = indeksSebelumnya * (v / sebelumnya);
            } else idx = (v / awal) * 100;

            indeksSebelumnya = idx;
            hasil = { teks: fmtIndeks(idx), arah: Math.sign(idx - 100) };
        }

        sebelumnya = v;
        return hasil;
    });
}

function Sel({ nilai, tebal }) {
    if (!nilai) {
        return <td className="px-3 py-2 text-right text-gray-300">–</td>;
    }

    const warna =
        nilai.arah > 0 ? "text-green-700" : nilai.arah < 0 ? "text-red-600" : "text-gray-800";

    return (
        <td className={`px-3 py-2 text-right tabular-nums ${warna} ${tebal ? "font-bold" : ""}`}>
            {nilai.teks}
        </td>
    );
}

export default function IndeksKumulatif() {
    const [tahun, setTahun] = useState(null);
    const [data, setData] = useState(null);
    const [dimensi, setDimensi] = useState("agama");
    const [mode, setMode] = useState("jumlah");
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState("");
    const [exporting, setExporting] = useState(false);

    useEffect(() => {
        let batal = false;

        async function muat() {
            setLoading(true);
            setError("");

            try {
                const qs = tahun ? `?tahun=${encodeURIComponent(tahun)}` : "";
                const res = await apiFetch(`/indeks-kumulatif${qs}`);

                if (!res.ok) {
                    throw new Error("Gagal memuat indeks kumulatif.");
                }

                const json = await res.json();
                if (!batal) {
                    setData(json);
                    if (!tahun) setTahun(json.tahun);
                }
            } catch (err) {
                if (!batal) {
                    setError(err.message || "Gagal memuat indeks kumulatif.");
                    setData(null);
                }
            } finally {
                if (!batal) setLoading(false);
            }
        }

        muat();

        return () => {
            batal = true;
        };
    }, [tahun]);

    async function handleExport() {
        setExporting(true);
        setError("");

        try {
            const res = await apiFetch(
                `/indeks-kumulatif/export?tahun=${encodeURIComponent(tahun)}`
            );

            if (!res.ok) {
                let pesan = "Gagal mengekspor indeks kumulatif.";
                try {
                    pesan = (await res.json()).message || pesan;
                } catch {
                    // respons bukan JSON, pakai pesan bawaan
                }
                throw new Error(pesan);
            }

            const blob = await res.blob();
            const disposition = res.headers.get("Content-Disposition") || "";
            const cocok = disposition.match(/filename="?([^"]+)"?/i);
            const namaFile = cocok ? cocok[1] : `indeks-kumulatif-${tahun}.xlsx`;

            const url = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = namaFile;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            setError(err.message || "Gagal mengekspor indeks kumulatif.");
        } finally {
            setExporting(false);
        }
    }

    const bulan = data?.bulan ?? [];
    const tabel = data?.dimensi?.[dimensi];
    const modeAktif = MODE_TABS.find((m) => m.key === mode);

    // Bulan pertama yang punya data = patokan indeks (biasanya Januari).
    const idxAwal = bulan.findIndex((b) => b.sumber !== "kosong");
    const namaAwal =
        idxAwal >= 0
            ? BULAN_NAMA[parseInt(bulan[idxAwal].periode.slice(5, 7), 10) - 1].slice(0, 3)
            : "Jan";
    const namaAwalPenuh =
        idxAwal >= 0
            ? BULAN_NAMA[parseInt(bulan[idxAwal].periode.slice(5, 7), 10) - 1]
            : "Januari";
    const labelMode = (m) => (m.key === "indeks" ? `Indeks (${namaAwal} = 100)` : m.label);

    const baris = useMemo(
        () =>
            (tabel?.kategori ?? []).map((k) => ({
                nama: k.nama,
                sel: hitungDeret(k.nilai, mode),
            })),
        [tabel, mode]
    );
    const barisTotal = useMemo(
        () => (tabel ? hitungDeret(tabel.total, mode) : []),
        [tabel, mode]
    );

    const rentang =
        bulan.length > 0
            ? `${BULAN_NAMA[0]} – ${formatPeriodeLabel(bulan[bulan.length - 1].periode)}`
            : null;
    const bulanKosong = bulan.filter((b) => b.sumber === "kosong");
    const opsiTahun = data?.tahun_tersedia?.length ? data.tahun_tersedia : tahun ? [tahun] : [];

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-end justify-between gap-4 mb-5">
                <div>
                    <h2 className="text-2xl font-bold">Indeks Kumulatif</h2>
                    <p className="text-sm text-gray-500">
                        Perbandingan jumlah ASN aktif dari Januari sampai bulan terakhir yang
                        sudah diupload
                        {rentang ? ` (${rentang})` : ""}.
                    </p>
                </div>

                <div className="flex items-end gap-3">
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">Tahun</label>
                        <select
                            value={tahun || ""}
                            onChange={(e) => setTahun(e.target.value)}
                            className="border p-2 rounded text-sm bg-white text-gray-700 min-w-[8rem]"
                            aria-label="Pilih tahun"
                        >
                            {opsiTahun.map((t) => (
                                <option key={t} value={t}>
                                    {t}
                                </option>
                            ))}
                        </select>
                    </div>

                    <button
                        onClick={handleExport}
                        disabled={exporting || loading || bulan.length === 0}
                        className="flex items-center gap-2 bg-[#006A4E] text-white px-4 py-2 rounded text-sm font-semibold disabled:opacity-60 disabled:cursor-not-allowed hover:bg-[#005a41]"
                    >
                        {exporting ? (
                            <LoaderCircle className="animate-spin" size={16} />
                        ) : (
                            <Download size={16} />
                        )}
                        Export Excel
                    </button>
                </div>
            </div>

            <div className="flex flex-wrap gap-2 mb-3">
                {DIMENSI_TABS.map((d) => (
                    <button
                        key={d.key}
                        onClick={() => setDimensi(d.key)}
                        className={`px-4 py-1.5 rounded-full text-sm font-semibold border ${dimensi === d.key
                            ? "bg-[#006A4E] border-[#006A4E] text-white"
                            : "bg-white border-gray-300 text-gray-600 hover:bg-gray-50"
                            }`}
                    >
                        {d.label}
                    </button>
                ))}
            </div>

            <div className="flex flex-wrap items-center gap-2 mb-4">
                {MODE_TABS.map((m) => (
                    <button
                        key={m.key}
                        onClick={() => setMode(m.key)}
                        className={`px-3 py-1 rounded text-xs font-semibold border ${mode === m.key
                            ? "bg-[#E7F1FB] border-[#006A4E] text-[#006A4E]"
                            : "bg-white border-gray-300 text-gray-500 hover:bg-gray-50"
                            }`}
                    >
                        {labelMode(m)}
                    </button>
                ))}
                <span className="text-xs text-gray-500 ml-1">{modeAktif.hint}</span>
            </div>

            {error && (
                <div className="flex items-center gap-3 text-red-700 bg-red-50 p-3 rounded-lg text-sm mb-4">
                    <AlertTriangle size={16} />
                    {error}
                </div>
            )}

            {bulanKosong.length > 0 && (
                <div className="flex items-start gap-3 text-amber-800 bg-amber-50 p-3 rounded-lg text-sm mb-4">
                    <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                    <span>
                        Belum ada data untuk{" "}
                        {bulanKosong.map((b) => formatPeriodeLabel(b.periode)).join(", ")}
                        . Bulan tersebut dilewati dalam perhitungan selisih.
                    </span>
                </div>
            )}

            {loading ? (
                <div className="flex items-center justify-center gap-2 text-gray-500 py-12 text-sm">
                    <LoaderCircle className="animate-spin" size={18} />
                    Memuat data...
                </div>
            ) : bulan.length === 0 ? (
                <p className="text-sm text-gray-500 py-8 text-center">
                    Belum ada data yang diupload pada tahun {tahun || "ini"}.
                </p>
            ) : (
                <div className="overflow-x-auto border rounded-lg">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-gray-600">
                            <tr>
                                <th className="sticky left-0 bg-gray-50 px-3 py-2 text-left font-semibold min-w-[11rem]">
                                    {tabel?.label ?? "Kategori"}
                                </th>
                                {bulan.map((b) => (
                                    <th
                                        key={b.periode}
                                        className="px-3 py-2 text-right font-semibold whitespace-nowrap"
                                        title={
                                            b.sumber === "kosong"
                                                ? "Belum ada data bulan ini"
                                                : formatPeriodeLabel(b.periode)
                                        }
                                    >
                                        {BULAN_NAMA[parseInt(b.periode.slice(5, 7), 10) - 1].slice(0, 3)}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {baris.map((r) => (
                                <tr key={r.nama} className="hover:bg-gray-50">
                                    <td className="sticky left-0 bg-white px-3 py-2 font-medium text-gray-700">
                                        {r.nama}
                                    </td>
                                    {r.sel.map((n, i) => (
                                        <Sel key={bulan[i].periode} nilai={n} />
                                    ))}
                                </tr>
                            ))}
                            <tr className="bg-gray-50">
                                <td className="sticky left-0 bg-gray-50 px-3 py-2 font-bold">
                                    Total
                                </td>
                                {barisTotal.map((n, i) => (
                                    <Sel key={bulan[i].periode} nilai={n} tebal />
                                ))}
                            </tr>
                        </tbody>
                    </table>
                </div>
            )}

            <details open className="mt-5 border rounded-lg bg-gray-50 p-4 text-sm text-gray-700">
                <summary className="font-semibold cursor-pointer">
                    Keterangan: cara menghitung indeks kumulatif
                </summary>

                <div className="mt-3 space-y-3">
                    <p>
                        Angka diambil dari arsip rekap tiap bulan (ASN aktif: PNS + PPPK), dari
                        Januari sampai bulan terakhir yang sudah diupload. Bulan yang belum
                        diupload ditulis "–" dan dilewati dalam perhitungan.
                    </p>

                    <ul className="list-disc pl-5 space-y-1">
                        <li>
                            <b>Jumlah</b>: jumlah ASN aktif pada bulan tersebut.
                        </li>
                        <li>
                            <b>Selisih per Bulan</b>: jumlah bulan ini − jumlah bulan lalu.
                        </li>
                        <li>
                            <b>Kumulatif</b>: jumlah bulan ini − jumlah {namaAwalPenuh} (bulan
                            pertama).
                        </li>
                        <li>
                            <b>Indeks</b>: indeks bulan lalu × (jumlah bulan ini ÷ jumlah bulan
                            lalu). {namaAwalPenuh} = 100. Hasilnya sama dengan jumlah bulan ini ÷
                            jumlah {namaAwalPenuh} × 100.
                        </li>
                    </ul>

                    <p>
                        Indeks di atas 100 berarti jumlah naik dibanding {namaAwalPenuh}, di bawah
                        100 berarti turun. Selisihnya dari 100 adalah persen perubahan (110 = naik
                        10%, 90 = turun 10%).
                    </p>

                    <div className="overflow-x-auto">
                        <table className="text-xs border bg-white">
                            <caption className="text-left text-gray-500 pb-1">
                                Contoh angka (bukan data asli)
                            </caption>
                            <thead className="bg-gray-100">
                                <tr>
                                    <th className="border px-3 py-1 text-left">Bulan</th>
                                    <th className="border px-3 py-1 text-right">Jumlah</th>
                                    <th className="border px-3 py-1 text-right">Selisih per bulan</th>
                                    <th className="border px-3 py-1 text-right">Kumulatif</th>
                                    <th className="border px-3 py-1 text-right">Indeks</th>
                                </tr>
                            </thead>
                            <tbody>
                                {[
                                    ["Bulan 1", "50", "–", "0", "100,0"],
                                    ["Bulan 2", "55", "+5", "+5", "110,0"],
                                    ["Bulan 3", "45", "−10", "−5", "90,0"],
                                    ["Bulan 4", "60", "+15", "+10", "120,0"],
                                ].map((baris) => (
                                    <tr key={baris[0]}>
                                        {baris.map((sel, i) => (
                                            <td
                                                key={i}
                                                className={`border px-3 py-1 ${i === 0 ? "" : "text-right tabular-nums"}`}
                                            >
                                                {sel}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <p className="text-xs text-gray-500 mt-1">
                            Bulan 3: indeks = 110 × (45 ÷ 55) = 90,0. Bulan 4: indeks = 90 × (60 ÷
                            45) = 120,0.
                        </p>
                    </div>
                </div>
            </details>
        </div>
    );
}