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

function PensiunanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pensiunan-pns");

                if (!res.ok) {
                    throw new Error("Gagal memuat data pensiunan PNS.");
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(err?.message || "Gagal memuat data pensiunan PNS.");
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
              { label: "Golongan I", laki_laki: data.golongan_I?.laki_laki || 0, perempuan: data.golongan_I?.perempuan || 0 },
              { label: "Golongan II", laki_laki: data.golongan_II?.laki_laki || 0, perempuan: data.golongan_II?.perempuan || 0 },
              { label: "Golongan III", laki_laki: data.golongan_III?.laki_laki || 0, perempuan: data.golongan_III?.perempuan || 0 },
              { label: "Golongan IV", laki_laki: data.golongan_IV?.laki_laki || 0, perempuan: data.golongan_IV?.perempuan || 0 },
          ]
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                Pensiunan PNS
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
                <ChartCardLoading title="Pensiunan PNS berdasarkan Golongan dan Gender" />
            )}

            {data && (
                <>
                    <div
                        className="grid gap-4 mb-5"
                        style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
                    >
                        <TotalCard title="Jumlah Pensiunan PNS" total={data.jumlah_pensiunan_pns} />
                        <MiniStatCard title="Golongan I" total={data.golongan_I?.total} laki_laki={data.golongan_I?.laki_laki} perempuan={data.golongan_I?.perempuan} />
                        <MiniStatCard title="Golongan II" total={data.golongan_II?.total} laki_laki={data.golongan_II?.laki_laki} perempuan={data.golongan_II?.perempuan} />
                        <MiniStatCard title="Golongan III" total={data.golongan_III?.total} laki_laki={data.golongan_III?.laki_laki} perempuan={data.golongan_III?.perempuan} />
                        <MiniStatCard title="Golongan IV" total={data.golongan_IV?.total} laki_laki={data.golongan_IV?.laki_laki} perempuan={data.golongan_IV?.perempuan} />
                    </div>

                    <ChartCard title="Pensiunan PNS berdasarkan Golongan dan Gender">
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

export default PensiunanPanel;