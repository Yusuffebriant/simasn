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

// Definisi rincian pangkat/ruang per golongan, dipakai untuk merender
// kartu StatCard rincian secara konsisten untuk golongan I-IV.
const RINCIAN_GOLONGAN = {
    I: [
        { kode: "I/a", label: "Golongan I/a (Juru Muda)" },
        { kode: "I/b", label: "Golongan I/b (Juru Muda Tingkat I)" },
        { kode: "I/c", label: "Golongan I/c (Juru)" },
        { kode: "I/d", label: "Golongan I/d (Juru Tingkat I)" },
    ],
    II: [
        { kode: "II/a", label: "Golongan II/a (Pengatur Muda)" },
        { kode: "II/b", label: "Golongan II/b (Pengatur Muda Tingkat I)" },
        { kode: "II/c", label: "Golongan II/c (Pengatur)" },
        { kode: "II/d", label: "Golongan II/d (Pengatur Tingkat I)" },
    ],
    III: [
        { kode: "III/a", label: "Golongan III/a (Penata Muda)" },
        { kode: "III/b", label: "Golongan III/b (Penata Muda Tingkat I)" },
        { kode: "III/c", label: "Golongan III/c (Penata)" },
        { kode: "III/d", label: "Golongan III/d (Penata Tingkat I)" },
    ],
    IV: [
        { kode: "IV/a", label: "Golongan IV/a (Pembina Muda)" },
        { kode: "IV/b", label: "Golongan IV/b (Pembina Muda Tingkat I)" },
        { kode: "IV/c", label: "Golongan IV/c (Pembina)" },
        { kode: "IV/d", label: "Golongan IV/d (Pembina Tingkat I)" },
        { kode: "IV/e", label: "Golongan IV/e (Pembina Utama)" },
    ],
};

// Satu blok golongan: card putih pembungkus dengan judul "Golongan ...",
// diawali kartu ringkasan "Jumlah PNS Golongan ..." (variant="dark",
// warnanya disamakan dengan kartu "Jumlah PNS"), diikuti rincian per
// pangkat/ruang — satu MiniStatCard per rincian yang sudah menggabungkan
// gender (total + chip Laki-laki/Perempuan).
function GolonganSection({ romawi, golongan }) {
    const rincianList = RINCIAN_GOLONGAN[romawi];

    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5 mb-5">
            <h3 className="text-[#172033] font-semibold mb-4">
                Golongan {romawi}
            </h3>

            <div className="mb-4">
                <MiniStatCard
                    title={`Jumlah PNS Golongan ${romawi}`}
                    total={golongan.total}
                    laki_laki={golongan.laki_laki.total}
                    perempuan={golongan.perempuan.total}
                    variant="dark"
                />
            </div>

            <div
                className="grid gap-4"
                style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
            >
                {rincianList.map((r) => {
                    const laki_laki = golongan.laki_laki.rincian[r.kode] || 0;
                    const perempuan = golongan.perempuan.rincian[r.kode] || 0;

                    return (
                        <MiniStatCard
                            key={r.kode}
                            title={r.label}
                            total={laki_laki + perempuan}
                            laki_laki={laki_laki}
                            perempuan={perempuan}
                        />
                    );
                })}
            </div>
        </div>
    );
}

function GolonganPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pns-golongan");

                if (!res.ok) {
                    throw new Error("Gagal memuat data PNS berdasarkan golongan.");
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message || "Gagal memuat data PNS berdasarkan golongan."
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
        ? [
              {
                  label: "Golongan I",
                  laki_laki: data.golongan_I.laki_laki.total,
                  perempuan: data.golongan_I.perempuan.total,
              },
              {
                  label: "Golongan II",
                  laki_laki: data.golongan_II.laki_laki.total,
                  perempuan: data.golongan_II.perempuan.total,
              },
              {
                  label: "Golongan III",
                  laki_laki: data.golongan_III.laki_laki.total,
                  perempuan: data.golongan_III.perempuan.total,
              },
              {
                  label: "Golongan IV",
                  laki_laki: data.golongan_IV.laki_laki.total,
                  perempuan: data.golongan_IV.perempuan.total,
              },
          ]
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                PNS Berdasarkan Golongan
            </h2>

            {error && <ErrorBox message={error} />}

            {loading && !data && (
                <div className="mb-6">
                    <SkeletonCard />
                </div>
            )}

            {loading && !data && (
                <ChartCardLoading title="Perbandingan Golongan berdasarkan Gender" />
            )}

            {data && (
                <>
                    <div className="mb-5">
                        <TotalCard title="Jumlah PNS" total={data.jumlah_pns} />
                    </div>

                    <ChartCard title="Perbandingan Golongan berdasarkan Gender">
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

                    <div className="mt-6">
                        <GolonganSection romawi="I" golongan={data.golongan_I} />
                        <GolonganSection romawi="II" golongan={data.golongan_II} />
                        <GolonganSection romawi="III" golongan={data.golongan_III} />
                        <GolonganSection romawi="IV" golongan={data.golongan_IV} />
                    </div>
                </>
            )}
        </div>
    );
}

export default GolonganPanel;