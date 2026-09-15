import { useEffect, useState } from "react";
import { Download, LoaderCircle } from "lucide-react";
import {
    Bar,
    BarChart,
    CartesianGrid,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";
import { apiFetch } from "../../lib/api";
import {
    ChartCard,
    ChartCardLoading,
    ErrorBox,
    SkeletonCard,
    StatCard,
    TotalCard,
} from "./components/StatUi";

// Ambil nama file dari header Content-Disposition kalau ada, dengan
// fallback ke nama default — pola sama seperti filenameFromResponse() di
// RekapGolonganTable.jsx.
function filenameFromResponse(res, fallback) {
    const disposition = res.headers.get("Content-Disposition") || "";
    const match = disposition.match(/filename="?([^"]+)"?/i);
    return match ? match[1] : fallback;
}

// PPPK Kelurahan, dikelompokkan berdasarkan Kemantren induknya (5.03.020).
// Scope: PPPK aktif (status_kepegawaian = 'PPPK') — lihat
// statistikPppkKelurahan() di StatistikService & endpoint
// GET /statistik/pppk-kelurahan. Struktur & logic PERSIS sama dengan
// statistikPnsKelurahan() (5.03.019) — bedanya hanya filter status
// kepegawaian. Panel ini juga mirror 1:1 dari PnsKelurahanPanel.jsx: data
// TIDAK dipecah per gender (nested cuma kemantren -> kelurahan), jadi
// rincian per Kelurahan pakai StatCard biasa (bukan MiniStatCard). Warna
// grafik tetap teal (#0F6E6E) mengikuti skema warna utama halaman Statistik.
const WARNA_UTAMA = "#0F6E6E";

// Mapping Kemantren -> daftar Kelurahan, urutan & ejaan disamakan persis
// dengan $kemantrenKelurahanMap di statistikPppkKelurahan() (StatistikService)
// supaya urutan kartu & grafik konsisten dengan backend.
const KEMANTREN_KELURAHAN_MAP = {
    TEGALREJO: ["KRICAK", "KARANGWARU", "TEGALREJO", "BENER"],
    JETIS: ["BUMIJO", "COKRODININGRATAN", "GOWONGAN"],
    GONDOKUSUMAN: ["DEMANGAN", "KOTABARU", "KLITREN", "BACIRO", "TERBAN"],
    DANUREJAN: ["SURYATMAJAN", "TEGALPANGGUNG", "BAUSASRAN"],
    GEDONGTENGEN: ["SOSROMENDURAN", "PRINGGOKUSUMAN"],
    NGAMPILAN: ["NGAMPILAN", "NOTOPRAJAN"],
    WIROBRAJAN: ["PAKUNCEN", "WIROBRAJAN", "PATANGPULUHAN"],
    MANTRIJERON: ["GEDONGKIWO", "SURYODININGRATAN", "MANTRIJERON"],
    KRATON: ["PATEHAN", "PANEMBAHAN", "KADIPATEN"],
    GONDOMANAN: ["NGUPASAN", "PRAWIRODIRJAN"],
    PAKUALAMAN: ["PURWOKINANTI", "GUNUNGKETUR"],
    MERGANGSAN: ["KEPARAKAN", "WIROGUNAN", "BRONTOKUSUMAN"],
    UMBULHARJO: [
        "SEMAKI", "MUJAMUJU", "TAHUNAN", "WARUNGBOTO",
        "PANDEYAN", "SOROSUTAN", "GIWANGAN",
    ],
    KOTAGEDE: ["REJOWINANGUN", "PRENGGAN", "PURBAYAN"],
};

const KEMANTREN_LIST = Object.keys(KEMANTREN_KELURAHAN_MAP);

// Judul rapi ("TEGALREJO" -> "Tegalrejo") — sama seperti helper di
// PnsKelurahanPanel.jsx / AsnKemantrenPendidikanPanel.jsx.
function toTitleCase(text) {
    return text
        .toLowerCase()
        .replace(/(^|\s)\S/g, (c) => c.toUpperCase());
}

// Satu blok Kemantren: card putih pembungkus dengan judul "Kemantren ...",
// StatCard ringkasan (variant="dark", disamakan dengan TotalCard), grafik
// batang per Kelurahan, lalu grid rincian StatCard per Kelurahan. Pola
// sama seperti KemantrenKelurahanSection di PnsKelurahanPanel.jsx.
function KemantrenKelurahanSection({ nama, data }) {
    const daftarKelurahan = KEMANTREN_KELURAHAN_MAP[nama];
    const chartData = daftarKelurahan.map((kel) => ({
        label: toTitleCase(kel),
        jumlah: data.kelurahan[kel] || 0,
    }));

    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5 mb-5">
            <h3 className="text-[#172033] font-semibold mb-4">
                Kemantren {toTitleCase(nama)}
            </h3>

            <div className="mb-4">
                <StatCard
                    title={`Jumlah PPPK Kemantren ${toTitleCase(nama)}`}
                    value={data.total}
                    variant="dark"
                />
            </div>

            <div className="mb-4">
                <ChartCard
                    title={`Jumlah PPPK Kelurahan - Kemantren ${toTitleCase(nama)}`}
                    height={260}
                >
                    <BarChart data={chartData}>
                        <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E1E5EA" />
                        <XAxis
                            dataKey="label"
                            interval={0}
                            tick={{ fontSize: 11, fill: "#687386" }}
                            axisLine={{ stroke: "#E1E5EA" }}
                            tickLine={false}
                        />
                        <YAxis allowDecimals={false} tick={{ fontSize: 12, fill: "#687386" }} axisLine={false} tickLine={false} />
                        <Tooltip contentStyle={{ borderRadius: 8, border: "1px solid #E1E5EA" }} />
                        <Bar dataKey="jumlah" name="Jumlah PPPK" fill={WARNA_UTAMA} radius={[4, 4, 0, 0]} />
                    </BarChart>
                </ChartCard>
            </div>

            <div
                className="grid gap-4"
                style={{ gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))" }}
            >
                {daftarKelurahan.map((kel) => (
                    <StatCard
                        key={kel}
                        title={`Kelurahan ${toTitleCase(kel)}`}
                        value={data.kelurahan[kel] || 0}
                    />
                ))}
            </div>
        </div>
    );
}

function PppkKelurahanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [exporting, setExporting] = useState(false);

    async function handleExport() {
        setExporting(true);
        setError(null);

        try {
            const res = await apiFetch("/statistik/pppk-kelurahan/export");

            if (!res.ok) {
                throw new Error("Gagal mengekspor data PPPK Kelurahan.");
            }

            const blob = await res.blob();
            const filename = filenameFromResponse(res, "pppk-kelurahan.xlsx");

            const url = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            setError(err?.message || "Gagal mengekspor data PPPK Kelurahan.");
        } finally {
            setExporting(false);
        }
    }

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pppk-kelurahan");

                if (!res.ok) {
                    throw new Error("Gagal memuat data PPPK Kelurahan.");
                }

                const json = await res.json();
                // StatistikPppkKelurahanController mengembalikan hasil service
                // apa adanya (tanpa pembungkus { success, data }) — sama
                // seperti controller *Kemantren/*Kelurahan lain. Dibaca
                // toleran (json.data ?? json) supaya panel tidak pernah
                // tampil kosong walau controllernya belum/tidak dibungkus
                // { data }.
                if (!cancelled) setData(json.data ?? json);
            } catch (err) {
                if (!cancelled) {
                    setError(err?.message || "Gagal memuat data PPPK Kelurahan.");
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        }

        load();
        return () => {
            cancelled = true;
        };
    }, []);

    // Grafik ringkasan: total PPPK Kelurahan per Kemantren (seluruh
    // Kelurahan di bawahnya dijumlahkan) — pola sama seperti grafik
    // ringkasan di PnsKelurahanPanel.jsx, 1 seri saja karena tidak ada
    // pecahan gender.
    const ringkasanChartData = data
        ? KEMANTREN_LIST.map((nama) => ({
              label: toTitleCase(nama),
              jumlah: data.kemantren[nama]?.total || 0,
          }))
        : [];

    return (
        <div>
            <div className="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h2 className="text-[#172033] font-semibold text-lg">
                    PPPK Kelurahan
                </h2>

                <button
                    onClick={handleExport}
                    disabled={exporting || loading || !data}
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

            {error && <ErrorBox message={error} />}

            {loading && !data && (
                <div className="mb-6">
                    <SkeletonCard />
                </div>
            )}

            {loading && !data && (
                <ChartCardLoading title="Jumlah PPPK Kelurahan per Kemantren" />
            )}

            {data && (
                <>
                    <div className="mb-5">
                        <TotalCard
                            title="Jumlah PPPK Kelurahan"
                            total={data.jumlah_pppk_kelurahan}
                        />
                    </div>

                    <div className="mb-6">
                        <ChartCard title="Jumlah PPPK Kelurahan per Kemantren">
                            <BarChart data={ringkasanChartData} margin={{ bottom: 30 }}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E1E5EA" />
                                <XAxis
                                    dataKey="label"
                                    interval={0}
                                    angle={-35}
                                    textAnchor="end"
                                    height={70}
                                    tick={{ fontSize: 11, fill: "#687386" }}
                                    axisLine={{ stroke: "#E1E5EA" }}
                                    tickLine={false}
                                />
                                <YAxis allowDecimals={false} tick={{ fontSize: 12, fill: "#687386" }} axisLine={false} tickLine={false} />
                                <Tooltip contentStyle={{ borderRadius: 8, border: "1px solid #E1E5EA" }} />
                                <Bar dataKey="jumlah" name="Jumlah PPPK" fill={WARNA_UTAMA} radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ChartCard>
                    </div>

                    <div className="mt-6">
                        {KEMANTREN_LIST.map((nama) => (
                            <KemantrenKelurahanSection
                                key={nama}
                                nama={nama}
                                data={data.kemantren[nama]}
                            />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

export default PppkKelurahanPanel;