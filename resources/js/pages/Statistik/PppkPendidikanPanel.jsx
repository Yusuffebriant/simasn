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

// PPPK Berdasarkan Tingkat Pendidikan dan Jenis Kelamin (hanya PPPK
// aktif — lihat statistikPppkPendidikan() di StatistikService). Polanya
// sama seperti GolonganPanel/PppkGolonganPanel: TotalCard "Jumlah PPPK"
// + MiniStatCard per jenjang pendidikan (total + rincian gender),
// diikuti grafik batang perbandingan.
function PppkPendidikanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pppk-pendidikan");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data PPPK berdasarkan tingkat pendidikan."
                    );
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data PPPK berdasarkan tingkat pendidikan."
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
        ? PENDIDIKAN_LIST.map((p) => ({
              label: p.chartLabel,
              laki_laki: data[p.key].laki_laki,
              perempuan: data[p.key].perempuan,
          }))
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                PPPK Berdasarkan Tingkat Pendidikan dan Jenis Kelamin
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
                <ChartCardLoading title="Perbandingan Tingkat Pendidikan PPPK berdasarkan Gender" />
            )}

            {data && (
                <>
                    <div className="mb-5">
                        <TotalCard
                            title="Jumlah PPPK"
                            total={data.jumlah_pppk}
                        />
                    </div>

                    <ChartCard title="Perbandingan Tingkat Pendidikan PPPK berdasarkan Gender">
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

                    <div
                        className="grid gap-4 mt-6"
                        style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
                    >
                        {PENDIDIKAN_LIST.map((p) => (
                            <MiniStatCard
                                key={p.key}
                                title={`Jumlah PPPK Tingkat Pendidikan ${p.label}`}
                                total={data[p.key].total}
                                laki_laki={data[p.key].laki_laki}
                                perempuan={data[p.key].perempuan}
                            />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

export default PppkPendidikanPanel;