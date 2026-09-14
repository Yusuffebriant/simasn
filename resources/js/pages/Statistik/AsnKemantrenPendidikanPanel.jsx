import { useEffect, useState } from "react";
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
import { PENDIDIKAN_LIST } from "./pendidikanList";

// ASN Kemantren Berdasarkan Tingkat Pendidikan (5.03.014).
// Scope: seluruh ASN aktif (PNS + PPPK, semua jenis_kedudukan) yang
// ber-UNIT salah satu dari 14 Kemantren Kota Yogyakarta — lihat
// statistikAsnKemantrenPendidikan() di StatistikService & endpoint
// GET /statistik/asn-kemantren-pendidikan. Data di sini TIDAK dipecah
// per gender (beda dengan statistikPnsKemantrenPendidikan()), jadi
// kartu rincian pakai StatCard (angka tunggal) — pola sama persis
// seperti DinasSection di SkpdDinasPanel.jsx, bukan MiniStatCard.
const KEMANTREN_LIST = [
    "TEGALREJO", "JETIS", "GONDOKUSUMAN", "DANUREJAN", "GEDONGTENGEN",
    "NGAMPILAN", "WIROBRAJAN", "MANTRIJERON", "KRATON", "GONDOMANAN",
    "PAKUALAMAN", "MERGANGSAN", "UMBULHARJO", "KOTAGEDE",
];

// Warna batang bergantian teal/emas per topik (jenjang pendidikan) —
// konsisten dengan pola "beda topik beda warna" yang dipakai di panel
// lain (mis. SkpdDinasPanel.jsx: grafik Pendidikan teal, grafik
// Golongan emas, grafik Eselon teal lagi).
const BAR_COLORS = ["#0F6E6E", "#D4A017"];

// Judul rapi ("TEGALREJO" -> "Tegalrejo") untuk label kartu & grafik
// Kemantren — sama seperti helper di AsnPerangkatDaerahJenisKelaminPanel.jsx.
function toTitleCase(text) {
    return text
        .toLowerCase()
        .replace(/(^|\s)\S/g, (c) => c.toUpperCase());
}

// Satu blok bagian per jenjang pendidikan: card putih pembungkus dengan
// judul + TotalCard ringkasan jenjang + grid StatCard rincian per
// Kemantren. Pola sama seperti DinasSection di SkpdDinasPanel.jsx.
function KemantrenPendidikanSection({ title, total, children }) {
    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5 mb-5">
            <h3 className="text-[#172033] font-semibold mb-4">{title}</h3>
            <div className="mb-4">
                <TotalCard title={title} total={total} />
            </div>
            <div
                className="grid gap-4"
                style={{ gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))" }}
            >
                {children}
            </div>
        </div>
    );
}

function AsnKemantrenPendidikanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/asn-kemantren-pendidikan");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data ASN Kemantren berdasarkan tingkat pendidikan."
                    );
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data ASN Kemantren berdasarkan tingkat pendidikan."
                    );
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

    // Grafik ringkasan: total ASN Kemantren per jenjang pendidikan,
    // menjumlahkan seluruh 14 Kemantren untuk tiap jenjang — pola sama
    // seperti grafik ringkasan di AsnPendidikanPanel.jsx.
    const ringkasanChartData = data
        ? PENDIDIKAN_LIST.map((p) => ({
              label: p.chartLabel,
              jumlah: data.pendidikan[p.key].total,
          }))
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                ASN Kemantren Berdasarkan Tingkat Pendidikan
            </h2>

            {error && <ErrorBox message={error} />}

            {loading && !data && (
                <div className="mb-6">
                    <SkeletonCard />
                </div>
            )}

            {loading && !data && (
                <ChartCardLoading title="Jumlah ASN Kemantren berdasarkan Tingkat Pendidikan" />
            )}

            {data && (
                <>
                    <div className="mb-5">
                        <TotalCard
                            title="Jumlah ASN Kemantren"
                            total={data.jumlah_asn_kemantren}
                        />
                    </div>

                    <ChartCard title="Jumlah ASN Kemantren berdasarkan Tingkat Pendidikan">
                        <BarChart data={ringkasanChartData}>
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
                            <Bar dataKey="jumlah" name="Jumlah ASN Kemantren" fill="#0F6E6E" radius={[4, 4, 0, 0]} />
                        </BarChart>
                    </ChartCard>

                    <div className="mt-6">
                        {PENDIDIKAN_LIST.map((p, idx) => {
                            const perKemantren = data.pendidikan[p.key].kemantren;

                            const chartData = KEMANTREN_LIST.map((nama) => ({
                                label: toTitleCase(nama),
                                jumlah: perKemantren[nama] || 0,
                            }));

                            return (
                                <div key={p.key} className="mb-6">
                                    <KemantrenPendidikanSection
                                        title={`Jumlah ASN Kemantren berdasarkan Tingkat Pendidikan ${p.label} per Kemantren`}
                                        total={data.pendidikan[p.key].total}
                                    >
                                        {KEMANTREN_LIST.map((nama) => (
                                            <StatCard
                                                key={nama}
                                                title={`Kemantren ${toTitleCase(nama)}`}
                                                value={perKemantren[nama] || 0}
                                            />
                                        ))}
                                    </KemantrenPendidikanSection>

                                    <ChartCard
                                        title={`ASN Tingkat Pendidikan ${p.label} per Kemantren`}
                                    >
                                        <BarChart data={chartData} margin={{ bottom: 30 }}>
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
                                            <Bar
                                                dataKey="jumlah"
                                                name={`Jumlah ASN ${p.chartLabel}`}
                                                fill={BAR_COLORS[idx % BAR_COLORS.length]}
                                                radius={[4, 4, 0, 0]}
                                            />
                                        </BarChart>
                                    </ChartCard>
                                </div>
                            );
                        })}
                    </div>
                </>
            )}
        </div>
    );
}

export default AsnKemantrenPendidikanPanel;