import { useEffect, useMemo, useState } from "react";
import { LoaderCircle, AlertTriangle, Download } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { BULAN_NAMA, formatPeriodeLabel } from "../../lib/periode";
import { hitungDeret } from "../../lib/indeks";
import PerubahanIndividual from "./PerubahanIndividual";
import MutasiInstansi from "./MutasiInstansi";

// Enam dimensi. Urutan & key sama dengan IndeksKumulatifService::DIMENSI di backend.
// Lima pertama hanya menghitung ASN aktif (PNS + PPPK); "status" juga menghitung
// PPPK Paruh Waktu.
const DIMENSI_TABS = [
    { key: "agama", label: "Agama" },
    { key: "pendidikan", label: "Pendidikan" },
    { key: "golongan", label: "Golongan" },
    { key: "jft", label: "JFT" },
    { key: "jfu", label: "JFU" },
    { key: "status", label: "Status Kepegawaian" },
];

const MODE_TABS = [
    { key: "jumlah", label: "Jumlah", hint: () => "Banyaknya pegawai pada setiap bulan." },
    {
        key: "selisih",
        label: "Naik/Turun per Bulan",
        hint: () =>
            "Berapa pegawai bertambah (+) atau berkurang (−) dibanding bulan sebelumnya. Contoh: angka Februari = jumlah Februari − jumlah Januari.",
    },
    {
        key: "kumulatif",
        label: "Naik/Turun sejak Awal",
        hint: (awal) =>
            `Berapa pegawai bertambah (+) atau berkurang (−) dibanding ${awal}, bulan awal pembanding. Angkanya menumpuk dari bulan ke bulan.`,
    },
    {
        key: "indeks",
        label: "Indeks",
        hint: (awal) =>
            `Bulan awal (${awal}) dianggap 100. Angka 110 berarti naik 10% dari ${awal}; 90 berarti turun 10%. Tanda – berarti tidak bisa dihitung (jumlah ${awal} nol atau belum ada data).`,
    },
];

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
    const [tampilan, setTampilan] = useState("rekap"); // rekap | perubahan
    const [awal, setAwal] = useState(""); // "" = bawaan (Januari s.d. bulan terakhir pada tahun)
    const [akhir, setAkhir] = useState("");
    const [periodeOpsi, setPeriodeOpsi] = useState([]);
    const [sertakanPw, setSertakanPw] = useState(false); // PPPK Paruh Waktu ikut dihitung (Agama, Pendidikan)

    useEffect(() => {
        let batal = false;
        apiFetch("/indeks-kumulatif/periode")
            .then((r) => (r.ok ? r.json() : { periode: [] }))
            .then((j) => !batal && setPeriodeOpsi(j.periode ?? []))
            .catch(() => { });
        return () => {
            batal = true;
        };
    }, []);

    // Rentang pilihan dipakai hanya kalau kedua ujungnya terisi dan berurutan.
    const rentangPilihan = awal && akhir && akhir >= awal;
    const qsDasar = rentangPilihan
        ? `awal=${encodeURIComponent(awal)}&akhir=${encodeURIComponent(akhir)}`
        : tahun
            ? `tahun=${encodeURIComponent(tahun)}`
            : "";
    const qsRentang = [qsDasar, sertakanPw ? "paruh_waktu=1" : ""].filter(Boolean).join("&");

    useEffect(() => {
        let batal = false;

        async function muat() {
            setLoading(true);
            setError("");

            try {
                const qs = qsRentang ? `?${qsRentang}` : "";
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
    }, [tahun, qsRentang]);

    async function handleExport() {
        setExporting(true);
        setError("");

        try {
            const res = await apiFetch(`/indeks-kumulatif/export?${qsRentang}`);

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
    // Siapa yang dihitung pada dimensi terpilih (dimensi status ikut menghitung PPPK Paruh Waktu).
    const sebutan = tabel?.cakupan?.sebutan ?? "ASN aktif";
    const rincian = tabel?.cakupan?.rincian ?? "PNS + PPPK";

    // Bulan pertama yang punya data = patokan indeks (biasanya Januari).
    const tanpaData = (b) => b.sumber === "kosong" || b.sumber === "tanpa_snapshot";
    // Dengan rentang pilihan, periode dasar = periode awal (urutan 0).
    const dasar = rentangPilihan ? 0 : null;
    const idxAwal = rentangPilihan ? 0 : bulan.findIndex((b) => !tanpaData(b));
    const namaAwal =
        idxAwal >= 0
            ? BULAN_NAMA[parseInt(bulan[idxAwal].periode.slice(5, 7), 10) - 1].slice(0, 3)
            : "Jan";
    const namaAwalPenuh =
        idxAwal >= 0
            ? BULAN_NAMA[parseInt(bulan[idxAwal].periode.slice(5, 7), 10) - 1]
            : "Januari";
    const labelMode = (m) =>
        m.key === "indeks"
            ? `Indeks (${namaAwal} = 100)`
            : m.key === "kumulatif"
                ? `Naik/Turun sejak ${namaAwal}`
                : m.label;

    const baris = useMemo(
        () =>
            (tabel?.kategori ?? []).map((k) => ({
                nama: k.nama,
                sel: hitungDeret(k.nilai, mode, dasar),
            })),
        [tabel, mode]
    );
    const barisTotal = useMemo(
        () => (tabel ? hitungDeret(tabel.total, mode, dasar) : []),
        [tabel, mode, dasar]
    );

    const rentang =
        bulan.length > 0
            ? `${formatPeriodeLabel(bulan[0].periode)} – ${formatPeriodeLabel(bulan[bulan.length - 1].periode)}`
            : null;
    const bulanKosong = bulan.filter((b) => b.sumber === "kosong");
    const bulanTanpaArsip = bulan.filter((b) => b.sumber === "tanpa_snapshot");
    const lintasTahun = new Set(bulan.map((b) => b.periode.slice(0, 4))).size > 1;
    const labelBulan = (periode) =>
        BULAN_NAMA[parseInt(periode.slice(5, 7), 10) - 1].slice(0, 3) +
        (lintasTahun ? ` ${periode.slice(2, 4)}` : "");
    const opsiTahun = data?.tahun_tersedia?.length ? data.tahun_tersedia : tahun ? [tahun] : [];

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-end justify-between gap-4 mb-5">
                <div>
                    <h2 className="text-2xl font-bold">Indeks Kumulatif</h2>
                    <p className="text-sm text-gray-500">
                        {tampilan === "rekap"
                            ? `Jumlah ASN aktif (PNS + PPPK) dari bulan ke bulan, dikelompokkan menurut agama, pendidikan, golongan, jabatan fungsional, atau status kepegawaian (termasuk PPPK Paruh Waktu)${rentang ? ` (${rentang})` : ""}.`
                            : tampilan === "instansi"
                                ? "Jumlah pegawai tiap instansi per bulan, beserta penjelasan siapa pindah dari dan ke instansi mana."
                                : "Perubahan data tiap pegawai antar bulan: jabatan, pangkat, unit kerja, pendidikan, dan status."}
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    {tampilan === "rekap" && (
                        <div>
                            <label className="block text-xs font-medium text-gray-700 mb-1">Tahun</label>
                            <select
                                value={tahun || ""}
                                onChange={(e) => {
                                    setTahun(e.target.value);
                                    setAwal("");
                                    setAkhir("");
                                }}
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

                    )}

                    {tampilan === "rekap" && (
                        <>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                    Periode awal
                                </label>
                                <select
                                    value={awal}
                                    onChange={(e) => setAwal(e.target.value)}
                                    className="border p-2 rounded text-sm bg-white text-gray-700 min-w-[9rem]"
                                    aria-label="Periode awal"
                                >
                                    <option value="">Bawaan (Januari)</option>
                                    {periodeOpsi.map((p) => (
                                        <option key={p.periode} value={p.periode}>
                                            {formatPeriodeLabel(p.periode)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">
                                    Periode akhir
                                </label>
                                <select
                                    value={akhir}
                                    onChange={(e) => setAkhir(e.target.value)}
                                    className="border p-2 rounded text-sm bg-white text-gray-700 min-w-[9rem]"
                                    aria-label="Periode akhir"
                                >
                                    <option value="">Bawaan (terakhir)</option>
                                    {periodeOpsi.map((p) => (
                                        <option key={p.periode} value={p.periode}>
                                            {formatPeriodeLabel(p.periode)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </>
                    )}

                    {tampilan === "rekap" && (
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
                    )}
                </div>
            </div>

            <div className="flex gap-2 mb-4 border-b">
                {[
                    ["rekap", "Jumlah per Kategori"],
                    ["instansi", "Perpindahan per Instansi"],
                    ["perubahan", "Perubahan Individual"],
                ].map(([key, label]) => (
                    <button
                        key={key}
                        onClick={() => setTampilan(key)}
                        className={`px-4 py-2 text-sm font-semibold border-b-2 -mb-px ${tampilan === key
                            ? "border-[#006A4E] text-[#006A4E]"
                            : "border-transparent text-gray-500 hover:text-gray-700"
                            }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {tampilan === "instansi" && <MutasiInstansi periodeOpsi={periodeOpsi} />}

            {tampilan === "perubahan" && <PerubahanIndividual periodeOpsi={periodeOpsi} />}

            {tampilan === "rekap" && (
                <>
                    <div className="flex flex-wrap items-center gap-2 mb-3">
                        <span className="text-xs font-medium text-gray-600 mr-1">Kelompokkan menurut:</span>
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

                    <label className="flex items-start gap-2 text-xs text-gray-700 mb-3">
                        <input
                            type="checkbox"
                            className="mt-0.5"
                            checked={sertakanPw}
                            onChange={(e) => setSertakanPw(e.target.checked)}
                        />
                        <span>
                            <b>Sertakan PPPK Paruh Waktu</b> (berlaku untuk kelompok Agama dan Pendidikan; kelompok
                            lain tetap PNS + PPPK). Bawaannya tidak dicentang, supaya angkanya sama dengan menu
                            Rekapitulasi.
                        </span>
                    </label>

                    <div className="flex flex-wrap items-center gap-2 mb-4">
                        <span className="text-xs font-medium text-gray-600 mr-1">Tampilkan sebagai:</span>
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
                        <p className="w-full text-xs text-gray-600 mt-1">{modeAktif.hint(namaAwalPenuh)}</p>
                    </div>

                    <div className="text-xs text-gray-600 bg-blue-50 border border-blue-100 rounded-lg p-3 mb-4">
                        Ingin tahu pegawai <b>pindah dari instansi mana ke instansi mana</b>, lengkap dengan
                        total tiap instansi?{" "}
                        <button
                            onClick={() => setTampilan("instansi")}
                            className="font-semibold text-[#006A4E] hover:underline"
                        >
                            Buka tab Perpindahan per Instansi
                        </button>
                        .
                    </div>

                    {error && (
                        <div className="flex items-center gap-3 text-red-700 bg-red-50 p-3 rounded-lg text-sm mb-4">
                            <AlertTriangle size={16} />
                            {error}
                        </div>
                    )}

                    {awal && akhir && akhir < awal && (
                        <div className="flex items-start gap-3 text-amber-800 bg-amber-50 p-3 rounded-lg text-sm mb-4">
                            <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                            <span>Periode akhir sebelum periode awal; rentang pilihan belum dipakai.</span>
                        </div>
                    )}

                    {sertakanPw && data?.bulan_tanpa_paruh_waktu?.length > 0 && (
                        <div className="flex items-start gap-3 text-amber-800 bg-amber-50 p-3 rounded-lg text-sm mb-4">
                            <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                            <span>
                                Data PPPK Paruh Waktu per agama/pendidikan belum ada untuk{" "}
                                {data.bulan_tanpa_paruh_waktu.map((p) => formatPeriodeLabel(p)).join(", ")}. Pada
                                kelompok Agama dan Pendidikan, bulan itu ditulis “–” (bukan 0) supaya angka tidak
                                tercampur antara yang memuat dan yang tidak memuat Paruh Waktu.
                            </span>
                        </div>
                    )}

                    {bulanTanpaArsip.length > 0 && (
                        <div className="flex items-start gap-3 text-amber-800 bg-amber-50 p-3 rounded-lg text-sm mb-4">
                            <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                            <span>
                                Import sudah ada tetapi arsip rekap belum tersedia untuk{" "}
                                {bulanTanpaArsip.map((b) => formatPeriodeLabel(b.periode)).join(", ")}.
                                Bulan tersebut ditulis "–" (tidak tersedia, bukan 0).
                            </span>
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
                                                        : b.sumber === "tanpa_snapshot"
                                                            ? "Import ada, tetapi arsip rekap bulan ini tidak tersedia"
                                                            : formatPeriodeLabel(b.periode)
                                                }
                                            >
                                                {labelBulan(b.periode)}
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
                            Keterangan: cara membaca tabel ini
                        </summary>

                        <div className="mt-3 space-y-3">
                            <p>
                                Setiap angka adalah jumlah {sebutan} ({rincian}) pada bulan itu, diambil
                                dari data yang diupload tiap bulan. Tanda “–” berarti data bulan itu belum
                                ada (bukan nol), dan bulannya dilewati dalam hitungan. Baris{" "}
                                <b>Total</b> adalah jumlah semua baris di atasnya pada bulan yang sama.
                            </p>

                            <ul className="list-disc pl-5 space-y-1">
                                <li>
                                    <b>Jumlah</b>: banyaknya pegawai pada bulan itu.
                                </li>
                                <li>
                                    <b>Naik/Turun per Bulan</b>: jumlah bulan ini dikurangi jumlah bulan
                                    lalu. Hijau (+) berarti bertambah, merah (−) berarti berkurang.
                                </li>
                                <li>
                                    <b>Naik/Turun sejak {namaAwal}</b>: jumlah bulan ini dikurangi jumlah{" "}
                                    {namaAwalPenuh} (bulan awal pembanding). Jadi terlihat total perubahan
                                    sejak awal, bukan hanya dari bulan lalu.
                                </li>
                                <li>
                                    <b>Indeks</b>: jumlah bulan ini dibagi jumlah {namaAwalPenuh}, lalu
                                    dikali 100. {namaAwalPenuh} selalu 100. Di atas 100 berarti lebih
                                    banyak dari awal, di bawah 100 berarti lebih sedikit. Selisihnya dari
                                    100 adalah persennya (110 = naik 10%, 90 = turun 10%).
                                </li>
                            </ul>

                            <div className="overflow-x-auto">
                                <table className="text-xs border bg-white">
                                    <caption className="text-left text-gray-500 pb-1">
                                        Contoh angka (bukan data asli)
                                    </caption>
                                    <thead className="bg-gray-100">
                                        <tr>
                                            <th className="border px-3 py-1 text-left">Bulan</th>
                                            <th className="border px-3 py-1 text-right">Jumlah</th>
                                            <th className="border px-3 py-1 text-right">Naik/turun per bulan</th>
                                            <th className="border px-3 py-1 text-right">Naik/turun sejak awal</th>
                                            <th className="border px-3 py-1 text-right">Indeks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {[
                                            ["Bulan 1 (awal)", "50", "–", "0", "100,0"],
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
                                    Bulan 3: 45 − 55 = −10 (per bulan); 45 − 50 = −5 (sejak awal); 45 ÷ 50 ×
                                    100 = 90,0 (indeks).
                                </p>
                            </div>

                            <p className="font-semibold text-amber-800">
                                Tab ini hanya menghitung jumlah per kelompok. Kalau JFT turun 3 dan JFU naik
                                3, belum tentu ada 3 orang yang pindah jabatan (bisa juga pegawai baru atau
                                pensiun). Untuk melihat siapa yang berpindah jabatan atau pangkat, buka tab
                                “Perubahan Individual”; untuk perpindahan antar instansi, buka tab
                                “Perpindahan per Instansi”.
                            </p>
                        </div>
                    </details>
                </>
            )}
        </div>
    );
}