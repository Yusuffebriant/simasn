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

function StrukturalPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pejabat-struktural");

                if (!res.ok) {
                    throw new Error("Gagal memuat data pejabat struktural.");
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(err?.message || "Gagal memuat data pejabat struktural.");
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
        ? [
              { label: "Eselon II", laki_laki: data.eselon_ii.laki_laki, perempuan: data.eselon_ii.perempuan },
              { label: "Eselon III", laki_laki: data.eselon_iii.laki_laki, perempuan: data.eselon_iii.perempuan },
              { label: "Eselon IV", laki_laki: data.eselon_iv.laki_laki, perempuan: data.eselon_iv.perempuan },
          ]
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                Pejabat Struktural
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
                <ChartCardLoading title="Perbandingan Eselon berdasarkan Gender" />
            )}

            {data && (
                <>
                    <div
                        className="grid gap-4 mb-5"
                        style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
                    >
                        <TotalCard title="Jumlah Pejabat Struktural" total={data.jumlah_pejabat_struktural} />
                        <MiniStatCard title="Eselon II" {...data.eselon_ii} />
                        <MiniStatCard title="Eselon III" {...data.eselon_iii} />
                        <MiniStatCard title="Eselon IV" {...data.eselon_iv} />
                    </div>

                    <ChartCard title="Perbandingan Eselon berdasarkan Gender">
                        <BarChart data={chartData}>
                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E1E5EA" />
                            <XAxis dataKey="label" tick={{ fontSize: 12, fill: "#687386" }} axisLine={{ stroke: "#E1E5EA" }} tickLine={false} />
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

export default StrukturalPanel;