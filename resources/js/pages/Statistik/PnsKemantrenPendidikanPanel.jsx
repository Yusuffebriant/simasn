import { useEffect, useState } from "react";
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";
import { apiFetch } from "../../lib/api";
import {
    ChartCard,
    ChartCardLoading,
    ErrorBox,
    MiniStatCard,
    SkeletonCard,
    TotalCard,
} from "./components/StatUi";
import { PENDIDIKAN_LIST } from "./pendidikanList";

// PNS Kemantren Berdasarkan Tingkat Pendidikan dan Jenis Kelamin (5.03.015).
// Scope: hanya PNS aktif (status_kepegawaian = 'PNS') yang ber-UNIT salah
// satu dari 14 Kemantren Kota Yogyakarta — lihat
// statistikPnsKemantrenPendidikan() di StatistikService & endpoint
// GET /statistik/pns-kemantren-pendidikan. Beda dengan
// AsnKemantrenPendidikanPanel.jsx (5.03.014): data di sini dipecah lagi
// per gender (nested pendidikan -> gender -> kemantren), jadi kartu
// rincian per Kemantren pakai MiniStatCard (total + rincian laki-laki/
// perempuan) — pola sama seperti PnsPendidikanPanel.jsx, digabung dengan
// struktur per-Kemantren dari AsnKemantrenPendidikanPanel.jsx.
const KEMANTREN_LIST = [
    "TEGALREJO", "JETIS", "GONDOKUSUMAN", "DANUREJAN", "GEDONGTENGEN",
    "NGAMPILAN", "WIROBRAJAN", "MANTRIJERON", "KRATON", "GONDOMANAN",
    "PAKUALAMAN", "MERGANGSAN", "UMBULHARJO", "KOTAGEDE",
];

// Judul rapi ("TEGALREJO" -> "Tegalrejo") untuk label kartu & grafik
// Kemantren — sama seperti helper di AsnKemantrenPendidikanPanel.jsx.
function toTitleCase(text) {
    return text
        .toLowerCase()
        .replace(/(^|\s)\S/g, (c) => c.toUpperCase());
}

// Satu blok bagian per jenjang pendidikan: card putih pembungkus dengan
// judul + TotalCard ringkasan jenjang + grid MiniStatCard rincian per
// Kemantren (total + gender). Pola sama seperti KemantrenPendidikanSection
// di AsnKemantrenPendidikanPanel.jsx, tapi grid-nya pakai MiniStatCard
// karena data di sini punya rincian gender per Kemantren.
function KemantrenPendidikanSection({ title, total, children }) {
    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5 mb-5">
            <h3 className="text-[#172033] font-semibold mb-4">{title}</h3>
            <div className="mb-4">
                <TotalCard title={title} total={total} />
            </div>
            <div
                className="grid gap-4"
                style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
            >
                {children}
            </div>
        </div>
    );
}

function PnsKemantrenPendidikanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pns-kemantren-pendidikan");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data PNS Kemantren berdasarkan tingkat pendidikan."
                    );
                }

                const json = await res.json();
                if (!cancelled) setData(json.data ?? json);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data PNS Kemantren berdasarkan tingkat pendidikan."
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

    // Grafik ringkasan: total PNS Kemantren per jenjang pendidikan
    // (dijumlahkan dari seluruh 14 Kemantren), dipecah laki-laki/
    // perempuan — pola sama seperti grafik ringkasan di
    // PnsPendidikanPanel.jsx.
    const ringkasanChartData = data
        ? PENDIDIKAN_LIST.map((p) => ({
              label: p.chartLabel,
              laki_laki: data.pendidikan[p.key].laki_laki.total,
              perempuan: data.pendidikan[p.key].perempuan.total,
          }))
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                PNS Kemantren Berdasarkan Tingkat Pendidikan dan Jenis Kelamin
            </h2>

            {error && <ErrorBox message={error} />}

            {loading && !data && (
                <div className="mb-6">
                    <SkeletonCard />
                </div>
            )}

            {loading && !data && (
                <ChartCardLoading title="Jumlah PNS Kemantren berdasarkan Tingkat Pendidikan" />
            )}

            {data && (
                <>
                    <div className="mb-5">
                        <TotalCard
                            title="Jumlah PNS Kemantren"
                            total={data.jumlah_pns_kemantren}
                        />
                    </div>

                    <ChartCard title="Jumlah PNS Kemantren berdasarkan Tingkat Pendidikan dan Jenis Kelamin">
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
                            <Legend />
                            <Bar dataKey="laki_laki" name="Laki-laki" fill="#0F6E6E" radius={[4, 4, 0, 0]} />
                            <Bar dataKey="perempuan" name="Perempuan" fill="#D4A017" radius={[4, 4, 0, 0]} />
                        </BarChart>
                    </ChartCard>

                    <div className="mt-6">
                        {PENDIDIKAN_LIST.map((p) => {
                            const jenjang = data.pendidikan[p.key];
                            const perKemantrenL = jenjang.laki_laki.kemantren;
                            const perKemantrenP = jenjang.perempuan.kemantren;

                            const chartData = KEMANTREN_LIST.map((nama) => ({
                                label: toTitleCase(nama),
                                laki_laki: perKemantrenL[nama] || 0,
                                perempuan: perKemantrenP[nama] || 0,
                            }));

                            return (
                                <div key={p.key} className="mb-6">
                                    <KemantrenPendidikanSection
                                        title={`Jumlah PNS Kemantren berdasarkan Tingkat Pendidikan ${p.label} per Kemantren`}
                                        total={jenjang.total}
                                    >
                                        {KEMANTREN_LIST.map((nama) => (
                                            <MiniStatCard
                                                key={nama}
                                                title={`Kemantren ${toTitleCase(nama)}`}
                                                total={
                                                    (perKemantrenL[nama] || 0) +
                                                    (perKemantrenP[nama] || 0)
                                                }
                                                laki_laki={perKemantrenL[nama] || 0}
                                                perempuan={perKemantrenP[nama] || 0}
                                            />
                                        ))}
                                    </KemantrenPendidikanSection>

                                    <ChartCard
                                        title={`PNS Tingkat Pendidikan ${p.label} per Kemantren berdasarkan Jenis Kelamin`}
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
                                            <Legend />
                                            <Bar dataKey="laki_laki" name="Laki-laki" fill="#0F6E6E" radius={[4, 4, 0, 0]} />
                                            <Bar dataKey="perempuan" name="Perempuan" fill="#D4A017" radius={[4, 4, 0, 0]} />
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

export default PnsKemantrenPendidikanPanel;