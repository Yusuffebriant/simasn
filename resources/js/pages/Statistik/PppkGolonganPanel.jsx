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

// Daftar golongan PPPK (I-XI) — beda dari golongan PNS (I-IV), lihat
// docblock statistikPppkGolongan() di StatistikService.
const GOLONGAN_LIST = [
    "I", "II", "III", "IV", "V", "VI", "VII", "VIII", "IX", "X", "XI",
];

function PppkGolonganPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pppk-golongan");

                if (!res.ok) {
                    throw new Error("Gagal memuat data PPPK berdasarkan golongan.");
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message || "Gagal memuat data PPPK berdasarkan golongan."
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

    const chartData = data
        ? GOLONGAN_LIST.map((g) => ({
              label: g,
              laki_laki: data.golongan[g].laki_laki,
              perempuan: data.golongan[g].perempuan,
          }))
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                PPPK Berdasarkan Golongan
            </h2>

            {error && <ErrorBox message={error} />}

            {loading && !data && (
                <div
                    className="grid gap-4 mb-6"
                    style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
                >
                    <SkeletonCard />
                    <SkeletonCard />
                    <SkeletonCard />
                    <SkeletonCard />
                </div>
            )}

            {loading && !data && (
                <ChartCardLoading title="Perbandingan Golongan berdasarkan Gender" />
            )}

            {data && (
                <>
                    <div
                        className="grid gap-4 mb-5"
                        style={{ gridTemplateColumns: "repeat(4, minmax(200px, 1fr))" }}
                    >
                        <div style={{ gridColumn: "1 / -1" }}>
                            <TotalCard title="Jumlah PPPK" total={data.jumlah_pppk} />
                        </div>

                        {GOLONGAN_LIST.map((g) => (
                            <MiniStatCard
                                key={g}
                                title={`Jumlah PPPK Golongan ${g}`}
                                total={data.golongan[g].total}
                                laki_laki={data.golongan[g].laki_laki}
                                perempuan={data.golongan[g].perempuan}
                            />
                        ))}
                    </div>

                    <ChartCard title="Perbandingan Golongan berdasarkan Gender">
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
                            <Legend />
                            <Bar dataKey="laki_laki" name="Laki-laki" fill="#0F6E6E" radius={[4, 4, 0, 0]} />
                            <Bar dataKey="perempuan" name="Perempuan" fill="#D4A017" radius={[4, 4, 0, 0]} />
                        </BarChart>
                    </ChartCard>
                </>
            )}
        </div>
    );
}

export default PppkGolonganPanel;