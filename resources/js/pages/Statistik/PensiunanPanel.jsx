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
import { X } from "lucide-react";
import { apiFetch } from "../../lib/api";
import {
    ChartCard,
    ChartCardLoading,
    ErrorBox,
    MiniStatCard,
    SkeletonCard,
    TotalCard,
} from "./components/StatUi";

// Modal preview: rincian Pensiunan PNS per golongan (total saja, tanpa
// rincian gender — sesuai daftar data yang diminta).
function PensiunanDetailModal({ data, onClose }) {
    const rows = [
        { label: "Jumlah Pensiunan PNS", value: data.jumlah_pensiunan_pns },
        { label: "Jumlah Pensiunan PNS Golongan I", value: data.golongan_I?.total },
        { label: "Jumlah Pensiunan PNS Golongan II", value: data.golongan_II?.total },
        { label: "Jumlah Pensiunan PNS Golongan III", value: data.golongan_III?.total },
        { label: "Jumlah Pensiunan PNS Golongan IV", value: data.golongan_IV?.total },
    ];

    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col">
                <div className="flex items-center justify-between p-5 border-b border-[#E1E5EA]">
                    <h3 className="text-lg font-bold text-[#172033]">
                        Detail Pensiunan PNS
                    </h3>
                    <button
                        onClick={onClose}
                        className="p-2 hover:bg-gray-100 rounded-lg"
                    >
                        <X size={20} />
                    </button>
                </div>

                <div className="overflow-y-auto p-5 flex-1">
                    <div className="border border-[#E1E5EA] rounded-lg overflow-hidden">
                        {rows.map((row) => (
                            <div
                                key={row.label}
                                className="flex items-center justify-between px-4 py-2.5 border-b border-[#F0F2F5] last:border-b-0"
                            >
                                <span className="text-sm text-[#172033]">{row.label}</span>
                                <span className="text-sm font-bold text-[#172033] tabular-nums">
                                    {Number(row.value || 0).toLocaleString("id-ID")}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

function PensiunanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [showDetail, setShowDetail] = useState(false);

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
                <ChartCardLoading title="Perbandingan Pensiunan PNS berdasarkan Golongan dan Gender" />
            )}

            {data && (
                <>
                    <div className="mb-5">
                        <TotalCard title="Jumlah Pensiunan PNS" total={data.jumlah_pensiunan_pns} onClick={() => setShowDetail(true)} />
                    </div>

                    <ChartCard title="Perbandingan Pensiunan PNS berdasarkan Golongan dan Gender">
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

                    <div
                        className="grid gap-4 mt-6"
                        style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
                    >
                        <MiniStatCard title="Golongan I" total={data.golongan_I?.total} laki_laki={data.golongan_I?.laki_laki} perempuan={data.golongan_I?.perempuan} onClick={() => setShowDetail(true)} />
                        <MiniStatCard title="Golongan II" total={data.golongan_II?.total} laki_laki={data.golongan_II?.laki_laki} perempuan={data.golongan_II?.perempuan} onClick={() => setShowDetail(true)} />
                        <MiniStatCard title="Golongan III" total={data.golongan_III?.total} laki_laki={data.golongan_III?.laki_laki} perempuan={data.golongan_III?.perempuan} onClick={() => setShowDetail(true)} />
                        <MiniStatCard title="Golongan IV" total={data.golongan_IV?.total} laki_laki={data.golongan_IV?.laki_laki} perempuan={data.golongan_IV?.perempuan} onClick={() => setShowDetail(true)} />
                    </div>
                </>
            )}

            {showDetail && data && (
                <PensiunanDetailModal data={data} onClose={() => setShowDetail(false)} />
            )}
        </div>
    );
}

export default PensiunanPanel;