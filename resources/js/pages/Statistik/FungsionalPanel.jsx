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
} from "./components/StatUi";

function FungsionalPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pejabat-fungsional");

                if (!res.ok) {
                    throw new Error("Gagal memuat data pejabat fungsional.");
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(err?.message || "Gagal memuat data pejabat fungsional.");
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

    const genderChartData = data
        ? [
              {
                  label: "Fungsional Umum (JFU)",
                  laki_laki: data.fungsional_umum.laki_laki,
                  perempuan: data.fungsional_umum.perempuan,
              },
              {
                  label: "Fungsional Tertentu (JFT)",
                  laki_laki: data.fungsional_tertentu.laki_laki,
                  perempuan: data.fungsional_tertentu.perempuan,
              },
          ]
        : [];

    // Total per rumpun dihitung dari data yang sudah ada di API
    // (fungsional_tertentu_laki_laki / fungsional_tertentu_perempuan).
    const rumpun = data
        ? ["dosen", "guru", "medis", "teknis", "auditor", "p2upd"].reduce((acc, key) => {
              const laki_laki = data.fungsional_tertentu_laki_laki?.[key] || 0;
              const perempuan = data.fungsional_tertentu_perempuan?.[key] || 0;
              acc[key] = { laki_laki, perempuan, total: laki_laki + perempuan };
              return acc;
          }, {})
        : {};

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                Pejabat Fungsional
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
                    <SkeletonCard />
                    <SkeletonCard />
                    <SkeletonCard />
                    <SkeletonCard />
                </div>
            )}

            {loading && !data && (
                <ChartCardLoading title="Perbandingan Pejabat Fungsional berdasarkan Gender" />
            )}

            {data && (
                <>
                    <div className="flex flex-col gap-4 mb-5">
                        <div
                            className="grid gap-4"
                            style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
                        >
                            <MiniStatCard title="Fungsional Umum (JFU)" {...data.fungsional_umum} variant="dark" />
                            <MiniStatCard title="Fungsional Tertentu (JFT)" {...data.fungsional_tertentu} variant="dark" />
                        </div>

                        <div
                            className="grid gap-4"
                            style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
                        >
                            <MiniStatCard title="Fungsional Dosen" {...rumpun.dosen} />
                            <MiniStatCard title="Fungsional Guru" {...rumpun.guru} />
                            <MiniStatCard title="Fungsional Medis" {...rumpun.medis} />
                        </div>

                        <div
                            className="grid gap-4"
                            style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
                        >
                            <MiniStatCard title="Fungsional Teknis" {...rumpun.teknis} />
                            <MiniStatCard title="Fungsional Auditor" {...rumpun.auditor} />
                            <MiniStatCard title="Fungsional P2UPD" {...rumpun.p2upd} />
                        </div>
                    </div>

                    <ChartCard title="Perbandingan Pejabat Fungsional berdasarkan Gender">
                        <BarChart data={genderChartData}>
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

export default FungsionalPanel;