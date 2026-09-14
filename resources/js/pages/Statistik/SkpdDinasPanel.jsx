import { useEffect, useState } from "react";
import {
    Bar,
    BarChart,
    CartesianGrid,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";
import { apiFetch } from "../../lib/api";
import {
    ChartCard,
    ChartCardLoading,
    ErrorBox,
    SkeletonCard,
    StatCard,
    TotalCard,
} from "./components/StatUi";

// Pegawai Berdasarkan Tingkat Pendidikan dan SKPD (18 Dinas).
// Beda dari panel Pendidikan lainnya (ASN/PNS/PPPK): scope-nya cuma
// staf/pejabat pada 18 Dinas, datanya TIDAK dipecah per gender
// (lihat statistikStafDinasPendidikan/Golongan, statistikPejabatStruktural
// Dinas, statistikPejabatFungsionalDinas, statistikPensiunanDinas di
// StatistikService — semua sudah agregat se-18 dinas). Jadi kartu
// rincian di sini pakai StatCard (angka tunggal), bukan MiniStatCard,
// dan grafiknya satu seri batang saja.
const PENDIDIKAN_STAF_LIST = [
    { key: "sd", label: "Tamat SD atau sederajat", chartLabel: "SD" },
    { key: "smp", label: "SMP dan sederajat", chartLabel: "SMP" },
    { key: "sma", label: "SMA dan sederajat", chartLabel: "SMA" },
    { key: "diploma", label: "Diploma", chartLabel: "Diploma" },
    { key: "strata_1", label: "Strata I", chartLabel: "S1" },
    { key: "strata_2", label: "Strata 2", chartLabel: "S2" },
    { key: "strata_3", label: "Strata 3", chartLabel: "S3" },
];

const GOLONGAN_LIST = ["I", "II", "III", "IV"];
const ESELON_LIST = ["I", "II", "III", "IV"];

// Satu blok bagian: card putih pembungkus dengan judul + TotalCard
// ringkasan + grid StatCard rincian. Dipakai berulang untuk bagian
// Pendidikan, Golongan, dan Pejabat Struktural supaya konsisten dengan
// gaya GolonganSection di GolonganPanel.jsx.
function DinasSection({ title, total, children }) {
    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5 mb-5">
            <h3 className="text-[#172033] font-semibold mb-4">{title}</h3>
            <div className="mb-4">
                <TotalCard title={title} total={total} />
            </div>
            <div
                className="grid gap-4"
                style={{ gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))" }}
            >
                {children}
            </div>
        </div>
    );
}

function SkpdDinasPanel() {
    const [pendidikan, setPendidikan] = useState(null);
    const [golongan, setGolongan] = useState(null);
    const [struktural, setStruktural] = useState(null);
    const [fungsional, setFungsional] = useState(null);
    const [pensiunan, setPensiunan] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const [resPendidikan, resGolongan, resStruktural, resFungsional, resPensiunan] =
                    await Promise.all([
                        apiFetch("/statistik/staf-dinas/pendidikan"),
                        apiFetch("/statistik/staf-dinas/golongan"),
                        apiFetch("/statistik/pejabat-struktural-dinas"),
                        apiFetch("/statistik/pejabat-fungsional-dinas"),
                        apiFetch("/statistik/pensiunan-dinas"),
                    ]);

                if (
                    !resPendidikan.ok ||
                    !resGolongan.ok ||
                    !resStruktural.ok ||
                    !resFungsional.ok ||
                    !resPensiunan.ok
                ) {
                    throw new Error(
                        "Gagal memuat data pegawai berdasarkan tingkat pendidikan dan SKPD."
                    );
                }

                const [
                    jsonPendidikan,
                    jsonGolongan,
                    jsonStruktural,
                    jsonFungsional,
                    jsonPensiunan,
                ] = await Promise.all([
                    resPendidikan.json(),
                    resGolongan.json(),
                    resStruktural.json(),
                    resFungsional.json(),
                    resPensiunan.json(),
                ]);

                if (!cancelled) {
                    setPendidikan(jsonPendidikan);
                    setGolongan(jsonGolongan);
                    setStruktural(jsonStruktural);
                    setFungsional(jsonFungsional);
                    setPensiunan(jsonPensiunan);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data pegawai berdasarkan tingkat pendidikan dan SKPD."
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

    const data = pendidikan && golongan && struktural && fungsional && pensiunan;

    const pendidikanChartData = pendidikan
        ? PENDIDIKAN_STAF_LIST.map((p) => ({
              label: p.chartLabel,
              jumlah: pendidikan[p.key],
          }))
        : [];

    const golonganChartData = golongan
        ? GOLONGAN_LIST.map((g) => ({
              label: `Golongan ${g}`,
              jumlah: golongan.golongan[g],
          }))
        : [];

    const eselonChartData = struktural
        ? ESELON_LIST.map((e) => ({
              label: `Eselon ${e}`,
              jumlah: struktural.eselon[e],
          }))
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                Pegawai Berdasarkan Tingkat Pendidikan dan SKPD (18 Dinas)
            </h2>

            {error && <ErrorBox message={error} />}

            {loading && !data && (
                <div className="mb-6">
                    <SkeletonCard />
                </div>
            )}

            {loading && !data && (
                <ChartCardLoading title="Staf Kantor Dinas Daerah berdasarkan Tingkat Pendidikan" />
            )}

            {data && (
                <>
                    <DinasSection
                        title="Jumlah Staf Kantor Dinas Daerah Berdasarkan Tingkat Pendidikan"
                        total={pendidikan.jumlah_staf_dinas}
                    >
                        {PENDIDIKAN_STAF_LIST.map((p) => (
                            <StatCard
                                key={p.key}
                                title={p.label}
                                value={pendidikan[p.key]}
                            />
                        ))}
                    </DinasSection>

                    <ChartCard title="Staf Kantor Dinas Daerah berdasarkan Tingkat Pendidikan">
                        <BarChart data={pendidikanChartData}>
                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E1E5EA" />
                            <XAxis dataKey="label" tick={{ fontSize: 11, fill: "#687386" }} axisLine={{ stroke: "#E1E5EA" }} tickLine={false} />
                            <YAxis allowDecimals={false} tick={{ fontSize: 12, fill: "#687386" }} axisLine={false} tickLine={false} />
                            <Tooltip contentStyle={{ borderRadius: 8, border: "1px solid #E1E5EA" }} />
                            <Bar dataKey="jumlah" name="Jumlah Staf" fill="#0F6E6E" radius={[4, 4, 0, 0]} />
                        </BarChart>
                    </ChartCard>

                    <div className="mt-6">
                        <DinasSection
                            title="Jumlah Staf Kantor Dinas Daerah Berdasarkan Golongan"
                            total={golongan.jumlah_staf_dinas}
                        >
                            {GOLONGAN_LIST.map((g) => (
                                <StatCard
                                    key={g}
                                    title={`Golongan ${g}`}
                                    value={golongan.golongan[g]}
                                />
                            ))}
                        </DinasSection>

                        <ChartCard title="Staf Kantor Dinas Daerah berdasarkan Golongan">
                            <BarChart data={golonganChartData}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E1E5EA" />
                                <XAxis dataKey="label" tick={{ fontSize: 12, fill: "#687386" }} axisLine={{ stroke: "#E1E5EA" }} tickLine={false} />
                                <YAxis allowDecimals={false} tick={{ fontSize: 12, fill: "#687386" }} axisLine={false} tickLine={false} />
                                <Tooltip contentStyle={{ borderRadius: 8, border: "1px solid #E1E5EA" }} />
                                <Bar dataKey="jumlah" name="Jumlah Staf" fill="#D4A017" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ChartCard>
                    </div>

                    <div className="mt-6">
                        <DinasSection
                            title="Jumlah Pejabat Struktural Kantor Dinas Daerah"
                            total={struktural.jumlah_pejabat_struktural}
                        >
                            {ESELON_LIST.map((e) => (
                                <StatCard
                                    key={e}
                                    title={`Eselon ${e}`}
                                    value={struktural.eselon[e]}
                                />
                            ))}
                        </DinasSection>

                        <ChartCard title="Pejabat Struktural Kantor Dinas Daerah berdasarkan Eselon">
                            <BarChart data={eselonChartData}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E1E5EA" />
                                <XAxis dataKey="label" tick={{ fontSize: 12, fill: "#687386" }} axisLine={{ stroke: "#E1E5EA" }} tickLine={false} />
                                <YAxis allowDecimals={false} tick={{ fontSize: 12, fill: "#687386" }} axisLine={false} tickLine={false} />
                                <Tooltip contentStyle={{ borderRadius: 8, border: "1px solid #E1E5EA" }} />
                                <Bar dataKey="jumlah" name="Jumlah Pejabat" fill="#0F6E6E" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ChartCard>
                    </div>

                    <div
                        className="grid gap-4 mt-6"
                        style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
                    >
                        <TotalCard
                            title="Jumlah Pejabat Fungsional Kantor Dinas Daerah"
                            total={fungsional.jumlah_pejabat_fungsional}
                        />
                        <TotalCard
                            title="Jumlah Pensiunan Kantor Dinas Daerah"
                            total={pensiunan.jumlah_pensiunan}
                        />
                    </div>
                </>
            )}
        </div>
    );
}

export default SkpdDinasPanel;