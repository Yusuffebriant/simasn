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

// Urutan & key harus persis sama dengan key yang dikembalikan
// StatistikService::statistikPenjabatPerangkatDaerahJenisKelamin() di
// backend (lihat response controller StatistikPenjabatPerangkatDaerahController).
const PENJABAT_LIST = [
    { key: "kepala_daerah", label: "Kepala Daerah", chartLabel: "Kepala Daerah" },
    { key: "mantri_pamong_praja", label: "Jumlah Mantri Pamong Praja", chartLabel: "Mantri Pamong Praja" },
    { key: "lurah", label: "Jumlah Lurah", chartLabel: "Lurah" },
    { key: "kepala_opd", label: "Jumlah Kepala OPD", chartLabel: "Kepala OPD" },
    { key: "pejabat_asn_struktural", label: "Jumlah Pejabat ASN Struktural", chartLabel: "ASN Struktural" },
    { key: "pejabat_asn_pelaksana", label: "Jumlah Pejabat ASN Pelaksana", chartLabel: "ASN Pelaksana" },
    {
        key: "anggota_tim_baperjakat",
        label: "Jumlah Anggota Tim Badan Pertimbangan dan Kepangkatan",
        chartLabel: "Tim Badan Pertimbangan dan Kepangkatan",
    },
];

function PenjabatPerangkatDaerahPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/penjabat-perangkat-daerah");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data Penjabat Perangkat Daerah berdasarkan jenis kelamin."
                    );
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data Penjabat Perangkat Daerah berdasarkan jenis kelamin."
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
        ? PENJABAT_LIST.map((p) => ({
              label: p.chartLabel,
              laki_laki: data[p.key]?.laki_laki || 0,
              perempuan: data[p.key]?.perempuan || 0,
          }))
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                Penjabat Perangkat Daerah Berdasarkan Jenis Kelamin
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
                </div>
            )}

            {loading && !data && (
                <ChartCardLoading title="Perbandingan Penjabat Perangkat Daerah berdasarkan Gender" />
            )}

            {data && (
                <>
                    <div
                        className="grid gap-4 mb-5"
                        style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
                    >
                        {PENJABAT_LIST.map((p) => (
                            <MiniStatCard
                                key={p.key}
                                title={p.label}
                                total={data[p.key]?.total || 0}
                                laki_laki={data[p.key]?.laki_laki || 0}
                                perempuan={data[p.key]?.perempuan || 0}
                            />
                        ))}
                    </div>

                    <ChartCard
                        title="Perbandingan Penjabat Perangkat Daerah berdasarkan Gender"
                        height={360}
                    >
                        <BarChart data={chartData} margin={{ bottom: 60 }}>
                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E1E5EA" />
                            <XAxis
                                dataKey="label"
                                interval={0}
                                angle={-30}
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
                </>
            )}
        </div>
    );
}

export default PenjabatPerangkatDaerahPanel;