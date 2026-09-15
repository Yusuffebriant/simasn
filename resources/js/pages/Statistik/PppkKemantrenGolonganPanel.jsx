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

// PPPK Kemantren Berdasarkan Golongan dan Jenis Kelamin (5.03.018).
// Scope: PPPK aktif yang ber-instansi salah satu dari 14 Kemantren Kota
// Yogyakarta — lihat statistikPppkKemantrenGolongan() di StatistikService
// & endpoint GET /statistik/pppk-kemantren-golongan. BEDA dengan
// PnsKemantrenGolonganPanel.jsx: golongan PPPK memakai kode romawi POLOS
// (I..XVII, bukan 'III/a'), jadi dikelompokkan jadi 4 rentang laporan
// (I-IV, V-VIII, IX-XII, XIII-XVII), bukan per golongan tunggal. Struktur
// UI (card & grafik) tetap mengikuti pola PnsKemantrenGolonganPanel.jsx
// supaya gaya, bentuk, dan posisi konsisten dengan panel Statistik lain.
const KEMANTREN_LIST = [
    "TEGALREJO", "JETIS", "GONDOKUSUMAN", "DANUREJAN", "GEDONGTENGEN",
    "NGAMPILAN", "WIROBRAJAN", "MANTRIJERON", "KRATON", "GONDOMANAN",
    "PAKUALAMAN", "MERGANGSAN", "UMBULHARJO", "KOTAGEDE",
];

// 4 rentang golongan PPPK, key harus sama persis dengan key yang
// dikembalikan statistikPppkKemantrenGolongan() di $d['laki_laki'] /
// $d['perempuan'] (golongan_I_IV, golongan_V_VIII, dst).
const GOLONGAN_LIST = [
    { key: "golongan_I_IV", label: "Golongan I-IV" },
    { key: "golongan_V_VIII", label: "Golongan V-VIII" },
    { key: "golongan_IX_XII", label: "Golongan IX-XII" },
    { key: "golongan_XIII_XVII", label: "Golongan XIII-XVII" },
];

// Judul rapi ("TEGALREJO" -> "Tegalrejo") — sama seperti helper di
// PnsKemantrenGolonganPanel.jsx / AsnKemantrenPendidikanPanel.jsx.
function toTitleCase(text) {
    return text
        .toLowerCase()
        .replace(/(^|\s)\S/g, (c) => c.toUpperCase());
}

// Satu blok Kemantren: card putih pembungkus dengan judul "Kemantren ...",
// diawali MiniStatCard ringkasan (variant="dark", disamakan dengan
// TotalCard), diikuti grafik perbandingan rentang golongan berdasarkan
// gender, lalu grid rincian per rentang golongan (MiniStatCard, total +
// gender) — pola sama persis dengan KemantrenGolonganSection di
// PnsKemantrenGolonganPanel.jsx.
function KemantrenGolonganSection({ nama, data }) {
    const chartData = GOLONGAN_LIST.map((g) => ({
        label: g.label,
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
                    title={`Jumlah PPPK Kemantren ${toTitleCase(nama)}`}
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
                        title={g.label}
                        total={(data.laki_laki[g.key] || 0) + (data.perempuan[g.key] || 0)}
                        laki_laki={data.laki_laki[g.key] || 0}
                        perempuan={data.perempuan[g.key] || 0}
                    />
                ))}
            </div>
        </div>
    );
}

function PppkKemantrenGolonganPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pppk-kemantren-golongan");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data PPPK Kemantren berdasarkan golongan dan jenis kelamin."
                    );
                }

                const json = await res.json();
                // StatistikPppkKemantrenGolonganController mengembalikan
                // hasil service apa adanya (tanpa pembungkus { success,
                // data }) — sama seperti StatistikPnsKemantrenGolonganController.
                // Dibaca toleran (json.data ?? json) supaya panel tidak
                // pernah tampil kosong walau controllernya belum/tidak
                // dibungkus { data }.
                if (!cancelled) setData(json.data ?? json);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data PPPK Kemantren berdasarkan golongan dan jenis kelamin."
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

    // Grafik ringkasan: total PPPK per Kemantren, dipecah gender — pola
    // sama seperti grafik ringkasan Kemantren di PnsKemantrenGolonganPanel.jsx.
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
                PPPK Kemantren Berdasarkan Golongan dan Jenis Kelamin
            </h2>

            {error && <ErrorBox message={error} />}

            {loading && !data && (
                <div className="mb-6">
                    <SkeletonCard />
                </div>
            )}

            {loading && !data && (
                <ChartCardLoading title="Jumlah PPPK Kemantren per Kemantren berdasarkan Gender" />
            )}

            {data && (
                <>
                    <div className="mb-5">
                        <TotalCard
                            title="Jumlah PPPK Kemantren"
                            total={data.jumlah_pppk_kemantren}
                        />
                    </div>

                    <div className="mb-6">
                        <ChartCard title="Jumlah PPPK Kemantren per Kemantren berdasarkan Gender">
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

export default PppkKemantrenGolonganPanel;