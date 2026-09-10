import {
    Bar,
    BarChart,
    CartesianGrid,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";
import { ChartCard, StatCard, TotalCard } from "./components/StatUi";

// MODEL SEMENTARA (dummy) — endpoint backend untuk Pensiunan PNS belum
// ada. Struktur data ini mengikuti bentuk yang sama seperti
// /statistik/pejabat-struktural, jadi begitu backend-nya siap tinggal
// ganti konstanta ini dengan pemanggilan apiFetch("/statistik/pensiunan-pns")
// (lihat pola loading/error di StrukturalPanel.jsx atau FungsionalPanel.jsx).
const DUMMY_DATA = {
    jumlah_pensiunan_pns: 245,
    golongan_i: 12,
    golongan_ii: 58,
    golongan_iii: 140,
    golongan_iv: 35,
};

function PensiunanPanel() {
    const data = DUMMY_DATA;

    const chartData = [
        { label: "Golongan I", jumlah: data.golongan_i },
        { label: "Golongan II", jumlah: data.golongan_ii },
        { label: "Golongan III", jumlah: data.golongan_iii },
        { label: "Golongan IV", jumlah: data.golongan_iv },
    ];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                Pensiunan PNS
            </h2>

            <div
                className="grid gap-4 mb-5"
                style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
            >
                <TotalCard title="Jumlah Pensiunan PNS" total={data.jumlah_pensiunan_pns} />
                <StatCard title="Jumlah Pensiunan PNS Golongan I" value={data.golongan_i} />
                <StatCard title="Jumlah Pensiunan PNS Golongan II" value={data.golongan_ii} />
                <StatCard title="Jumlah Pensiunan PNS Golongan III" value={data.golongan_iii} />
                <StatCard title="Jumlah Pensiunan PNS Golongan IV" value={data.golongan_iv} />
            </div>

            <ChartCard title="Pensiunan PNS berdasarkan Golongan">
                <BarChart data={chartData}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E1E5EA" />
                    <XAxis dataKey="label" tick={{ fontSize: 12, fill: "#687386" }} axisLine={{ stroke: "#E1E5EA" }} tickLine={false} />
                    <YAxis allowDecimals={false} tick={{ fontSize: 12, fill: "#687386" }} axisLine={false} tickLine={false} />
                    <Tooltip contentStyle={{ borderRadius: 8, border: "1px solid #E1E5EA" }} />
                    <Bar dataKey="jumlah" name="Jumlah" fill="#0F6E6E" radius={[4, 4, 0, 0]} />
                </BarChart>
            </ChartCard>
        </div>
    );
}

export default PensiunanPanel;