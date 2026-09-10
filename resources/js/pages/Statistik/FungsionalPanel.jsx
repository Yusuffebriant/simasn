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
    StatCard,
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

    const rumpunChartData = data
        ? ["dosen", "guru", "medis", "teknis"].map((key) => ({
              label: key.charAt(0).toUpperCase() + key.slice(1),
              laki_laki: data.fungsional_tertentu_laki_laki[key] || 0,
              perempuan: data.fungsional_tertentu_perempuan[key] || 0,
          }))
        : [];

    // Total per rumpun dihitung dari data yang sudah ada di API
    // (fungsional_tertentu_laki_laki / fungsional_tertentu_perempuan),
    // jadi tidak butuh field tambahan dari backend.
    const rumpun = data
        ? ["dosen", "guru", "medis", "teknis"].reduce((acc, key) => {
              const laki_laki = data.fungsional_tertentu_laki_laki?.[key] || 0;
              const perempuan = data.fungsional_tertentu_perempuan?.[key] || 0;
              acc[key] = { laki_laki, perempuan, total: laki_laki + perempuan };
              return acc;
          }, {})
        : {};

    // Auditor & P2UPD belum ada di response API saat ini — perlu backend
    // menambahkan field `jumlah_fungsional_auditor` dan
    // `jumlah_fungsional_p2upd` pada /statistik/pejabat-fungsional.
    // Sementara itu ditampilkan sebagai "-" agar tidak error.
    const jumlahAuditor = data?.jumlah_fungsional_auditor;
    const jumlahP2upd = data?.jumlah_fungsional_p2upd;

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
                <ChartCardLoading title="Rumpun Jabatan Fungsional Tertentu berdasarkan Gender" />
            )}

            {data && (
                <>
                    <div
                        className="grid gap-4 mb-5"
                        style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
                    >
                        <MiniStatCard title="Fungsional Umum (JFU)" {...data.fungsional_umum} variant="dark" />
                        <MiniStatCard title="Fungsional Tertentu (JFT)" {...data.fungsional_tertentu} variant="dark" />
                        <MiniStatCard title="Fungsional Dosen" {...rumpun.dosen} />
                        <MiniStatCard title="Fungsional Guru" {...rumpun.guru} />
                        <StatCard title="Fungsional Auditor" value={jumlahAuditor} variant="dark" />
                        <StatCard title="Fungsional P2UPD" value={jumlahP2upd} variant="dark" />
                        <MiniStatCard title="Fungsional Medis" {...rumpun.medis} />
                        <MiniStatCard title="Fungsional Teknis" {...rumpun.teknis} />
                    </div>

                    <ChartCard title="Rumpun Jabatan Fungsional Tertentu berdasarkan Gender">
                        <BarChart data={rumpunChartData}>
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