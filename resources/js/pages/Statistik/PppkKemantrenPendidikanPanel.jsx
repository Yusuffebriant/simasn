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

// PPPK Kemantren Berdasarkan Tingkat Pendidikan (5.03.016).
// Scope: PPPK aktif (status_kepegawaian = 'PPPK') yang ber-UNIT salah satu
// dari 14 Kemantren Kota Yogyakarta — lihat statistikPppkKemantrenPendidikan()
// di StatistikService & endpoint GET /statistik/pppk-kemantren-pendidikan.
//
// Beda dengan AsnKemantrenPendidikanPanel.jsx (5.03.014): datanya nested 3
// level (pendidikan -> gender -> kemantren). Rincian per Kemantren memakai
// 1 MiniStatCard gabungan (total laki-laki + perempuan, dengan chip ♂/♀) —
// bukan 2 kartu StatCard terpisah per gender — supaya konsisten dan lebih
// ringkas seperti PnsKemantrenPendidikanPanel.jsx. Grafik batang recharts
// tetap teal (#0F6E6E) untuk laki-laki + emas (#D4A017) untuk perempuan.
const KEMANTREN_LIST = [
    "TEGALREJO", "JETIS", "GONDOKUSUMAN", "DANUREJAN", "GEDONGTENGEN",
    "NGAMPILAN", "WIROBRAJAN", "MANTRIJERON", "KRATON", "GONDOMANAN",
    "PAKUALAMAN", "MERGANGSAN", "UMBULHARJO", "KOTAGEDE",
];

const WARNA_LAKI = "#0F6E6E";
const WARNA_PEREMPUAN = "#D4A017";

// Judul rapi ("TEGALREJO" -> "Tegalrejo") untuk label kartu & grafik
// Kemantren — sama seperti helper di AsnKemantrenPendidikanPanel.jsx.
function toTitleCase(text) {
    return text
        .toLowerCase()
        .replace(/(^|\s)\S/g, (c) => c.toUpperCase());
}

// Card putih pembungkus satu jenjang pendidikan: judul + TotalCard total
// jenjang (laki-laki + perempuan) + grid MiniStatCard rincian per Kemantren
// (1 card per Kemantren, gender laki-laki/perempuan digabung sebagai chip
// ♂/♀ di dalamnya). Pola sama persis dengan KemantrenPendidikanSection di
// PnsKemantrenPendidikanPanel.jsx supaya kedua panel konsisten.
function PendidikanSection({ title, total, children }) {
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

function PppkKemantrenPendidikanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pppk-kemantren-pendidikan");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data PPPK Kemantren berdasarkan tingkat pendidikan."
                    );
                }

                const json = await res.json();
                // StatistikPppkKemantrenPendidikanController mengembalikan hasil
                // service apa adanya (tanpa pembungkus { success, data }) — beda
                // dengan controller ASN Kemantren. Dibaca toleran supaya panel
                // tetap jalan kalau nanti controller-nya diseragamkan.
                if (!cancelled) setData(json.data ?? json);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data PPPK Kemantren berdasarkan tingkat pendidikan."
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

    // Grafik ringkasan: total PPPK Kemantren per jenjang pendidikan, dipecah
    // laki-laki vs perempuan (seluruh 14 Kemantren dijumlahkan).
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
                PPPK Kemantren Berdasarkan Tingkat Pendidikan
            </h2>

            {error && <ErrorBox message={error} />}

            {loading && !data && (
                <div className="mb-6">
                    <SkeletonCard />
                </div>
            )}

            {loading && !data && (
                <ChartCardLoading title="Jumlah PPPK Kemantren berdasarkan Tingkat Pendidikan" />
            )}

            {data && (
                <>
                    <div className="mb-5">
                        <TotalCard
                            title="Jumlah PPPK Kemantren"
                            total={data.jumlah_pppk_kemantren}
                        />
                    </div>

                    <ChartCard title="Jumlah PPPK Kemantren berdasarkan Tingkat Pendidikan">
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
                            <Bar dataKey="laki_laki" name="Laki-laki" fill={WARNA_LAKI} radius={[4, 4, 0, 0]} />
                            <Bar dataKey="perempuan" name="Perempuan" fill={WARNA_PEREMPUAN} radius={[4, 4, 0, 0]} />
                        </BarChart>
                    </ChartCard>

                    {/* Ringkasan tiap jenjang dalam satu baris kartu: total
                        jenjang + chip rincian gender, sama seperti grid
                        MiniStatCard di PppkPendidikanPanel.jsx. */}
                    <div
                        className="grid gap-4 mt-6"
                        style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
                    >
                        {PENDIDIKAN_LIST.map((p) => (
                            <MiniStatCard
                                key={p.key}
                                title={`Jumlah PPPK Kemantren Tingkat Pendidikan ${p.label}`}
                                total={data.pendidikan[p.key].total}
                                laki_laki={data.pendidikan[p.key].laki_laki.total}
                                perempuan={data.pendidikan[p.key].perempuan.total}
                            />
                        ))}
                    </div>

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
                                    <PendidikanSection
                                        title={`Jumlah PPPK Kemantren berdasarkan Tingkat Pendidikan ${p.label} per Kemantren`}
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
                                    </PendidikanSection>

                                    <ChartCard
                                        title={`PPPK Tingkat Pendidikan ${p.label} per Kemantren berdasarkan Jenis Kelamin`}
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
                                            <Bar
                                                dataKey="laki_laki"
                                                name={`Laki-laki ${p.chartLabel}`}
                                                fill={WARNA_LAKI}
                                                radius={[4, 4, 0, 0]}
                                            />
                                            <Bar
                                                dataKey="perempuan"
                                                name={`Perempuan ${p.chartLabel}`}
                                                fill={WARNA_PEREMPUAN}
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

export default PppkKemantrenPendidikanPanel;