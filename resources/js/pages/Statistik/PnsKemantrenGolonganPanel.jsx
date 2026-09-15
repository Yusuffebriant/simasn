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

// PNS Kemantren Berdasarkan Golongan dan Jenis Kelamin (5.03.017).
// Scope: PNS aktif yang ber-UNIT salah satu dari 14 Kemantren Kota
// Yogyakarta — lihat statistikPnsKemantrenGolongan() di StatistikService
// & endpoint GET /statistik/pns-kemantren-golongan. Data dipecah per
// Kemantren -> gender -> golongan I-IV, jadi rincian pakai MiniStatCard
// (total + chip Laki-laki/Perempuan) — pola sama seperti GolonganSection
// di GolonganPanel.jsx, diulang per Kemantren seperti
// AsnKemantrenPendidikanPanel.jsx.
const KEMANTREN_LIST = [
    "TEGALREJO", "JETIS", "GONDOKUSUMAN", "DANUREJAN", "GEDONGTENGEN",
    "NGAMPILAN", "WIROBRAJAN", "MANTRIJERON", "KRATON", "GONDOMANAN",
    "PAKUALAMAN", "MERGANGSAN", "UMBULHARJO", "KOTAGEDE",
];

const GOLONGAN_LIST = [
    { key: "golongan_I", romawi: "I" },
    { key: "golongan_II", romawi: "II" },
    { key: "golongan_III", romawi: "III" },
    { key: "golongan_IV", romawi: "IV" },
];

// Judul rapi ("TEGALREJO" -> "Tegalrejo") — sama seperti helper di
// AsnKemantrenPendidikanPanel.jsx / AsnPerangkatDaerahJenisKelaminPanel.jsx.
function toTitleCase(text) {
    return text
        .toLowerCase()
        .replace(/(^|\s)\S/g, (c) => c.toUpperCase());
}

// Satu blok Kemantren: card putih pembungkus dengan judul "Kemantren ...",
// diawali MiniStatCard ringkasan (variant="dark", disamakan dengan
// TotalCard), diikuti grafik perbandingan golongan berdasarkan gender,
// lalu grid rincian per golongan I-IV (MiniStatCard, total + gender).
function KemantrenGolonganSection({ nama, data }) {
    const chartData = GOLONGAN_LIST.map((g) => ({
        label: `Golongan ${g.romawi}`,
        laki_laki: data.laki_laki[g.key] || 0,
        perempuan: data.perempuan[g.key] || 0,
    }));

    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5 mb-5">
            <h3 className="text-[#172033] font-semibold mb-4">
                Kemantren {toTitleCase(nama)}
            </h3>

            <div className="mb-4">
                <MiniStatCard
                    title={`Jumlah PNS Kemantren ${toTitleCase(nama)}`}
                    total={data.total}
                    laki_laki={data.laki_laki.total}
                    perempuan={data.perempuan.total}
                    variant="dark"
                />
            </div>

            <div className="mb-4">
                <ChartCard
                    title={`Perbandingan Golongan berdasarkan Gender - Kemantren ${toTitleCase(nama)}`}
                    height={260}
                >
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
            </div>

            <div
                className="grid gap-4"
                style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
            >
                {GOLONGAN_LIST.map((g) => (
                    <MiniStatCard
                        key={g.key}
                        title={`Golongan ${g.romawi}`}
                        total={(data.laki_laki[g.key] || 0) + (data.perempuan[g.key] || 0)}
                        laki_laki={data.laki_laki[g.key] || 0}
                        perempuan={data.perempuan[g.key] || 0}
                    />
                ))}
            </div>
        </div>
    );
}

function PnsKemantrenGolonganPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pns-kemantren-golongan");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data PNS Kemantren berdasarkan golongan dan jenis kelamin."
                    );
                }

                const json = await res.json();
                // StatistikPnsKemantrenGolonganController mengembalikan hasil
                // service apa adanya (tanpa pembungkus { success, data }) —
                // beda dengan controller ASN Kemantren. Dibaca toleran (sama
                // seperti PnsKemantrenPendidikanPanel.jsx &
                // PppkKemantrenPendidikanPanel.jsx) supaya panel tetap jalan
                // kalau nanti controller-nya diseragamkan. Sebelumnya di sini
                // langsung pakai json.data saja — makanya card & grafik tidak
                // pernah muncul (json.data selalu undefined).
                if (!cancelled) setData(json.data ?? json);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data PNS Kemantren berdasarkan golongan dan jenis kelamin."
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

    // Grafik ringkasan: total PNS per Kemantren, dipecah gender — pola
    // sama seperti grafik ringkasan Kemantren di AsnKemantrenPendidikanPanel.jsx.
    const ringkasanChartData = data
        ? KEMANTREN_LIST.map((nama) => ({
              label: toTitleCase(nama),
              laki_laki: data.kemantren[nama]?.laki_laki?.total || 0,
              perempuan: data.kemantren[nama]?.perempuan?.total || 0,
          }))
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                PNS Kemantren Berdasarkan Golongan dan Jenis Kelamin
            </h2>

            {error && <ErrorBox message={error} />}

            {loading && !data && (
                <div className="mb-6">
                    <SkeletonCard />
                </div>
            )}

            {loading && !data && (
                <ChartCardLoading title="Jumlah PNS Kemantren per Kemantren berdasarkan Gender" />
            )}

            {data && (
                <>
                    <div className="mb-5">
                        <TotalCard
                            title="Jumlah PNS Kemantren"
                            total={data.jumlah_pns_kemantren}
                        />
                    </div>

                    <div className="mb-6">
                        <ChartCard title="Jumlah PNS Kemantren per Kemantren berdasarkan Gender">
                            <BarChart data={ringkasanChartData} margin={{ bottom: 30 }}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E1E5EA" />
                                <XAxis
                                    dataKey="label"
                                    interval={0}
                                    angle={-35}
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
                    </div>

                    <div className="mt-6">
                        {KEMANTREN_LIST.map((nama) => (
                            <KemantrenGolonganSection
                                key={nama}
                                nama={nama}
                                data={data.kemantren[nama]}
                            />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

export default PnsKemantrenGolonganPanel;