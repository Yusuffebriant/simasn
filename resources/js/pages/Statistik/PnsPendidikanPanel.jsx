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
    PreviewTableModal,
    SkeletonCard,
    TotalCard,
} from "./components/StatUi";
import { PENDIDIKAN_LIST } from "./pendidikanList";

// PNS Berdasarkan Tingkat Pendidikan dan Jenis Kelamin (hanya PNS aktif
// — lihat statistikPnsPendidikan() di StatistikService). Polanya sama
// seperti GolonganPanel/PppkGolonganPanel: TotalCard "Jumlah PNS" +
// MiniStatCard per jenjang pendidikan (total + rincian gender), diikuti
// grafik batang perbandingan, dan tabel preview saat kartu diklik.
function PnsPendidikanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    // Jenjang pendidikan yang sedang di-preview (mis. "sd", "smp", dst,
    // sesuai key di PENDIDIKAN_LIST). null berarti modal tertutup.
    // Nilai khusus "__all__" dipakai saat kartu "Jumlah PNS" (TotalCard)
    // diklik — merujuk ke grup ringkasan "Jumlah Keseluruhan PNS" yang
    // ditaruh paling atas di previewGroups.
    const [previewGroup, setPreviewGroup] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pns-pendidikan");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data PNS berdasarkan tingkat pendidikan."
                    );
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data PNS berdasarkan tingkat pendidikan."
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

    // Daftar grup untuk tabel preview. Grup pertama ("__all__") adalah
    // ringkasan keseluruhan PNS (dari kartu "Jumlah PNS" di atas),
    // rincian laki-laki/perempuan-nya dihitung dari jumlah semua jenjang
    // pendidikan. Sisanya 1 grup per jenjang pendidikan (SD, SMP, SMA,
    // Diploma I-IV, Strata 1-3) — mengikuti pola PreviewTableModal di
    // Pejabat Fungsional / PPPK Berdasarkan Golongan: 1 baris total +
    // label "Laki-Laki" / "Perempuan" langsung untuk baris rincian gender.
    const previewGroups = data
        ? [
              {
                  key: "__all__",
                  label: "Jumlah Keseluruhan PNS",
                  items: [
                      {
                          type: "total",
                          label: "Jumlah Keseluruhan PNS",
                          value: data.jumlah_pns,
                      },
                      {
                          type: "laki_laki",
                          label: "Laki-Laki",
                          value: PENDIDIKAN_LIST.reduce(
                              (sum, p) => sum + (data[p.key].laki_laki || 0),
                              0
                          ),
                      },
                      {
                          type: "perempuan",
                          label: "Perempuan",
                          value: PENDIDIKAN_LIST.reduce(
                              (sum, p) => sum + (data[p.key].perempuan || 0),
                              0
                          ),
                      },
                  ],
              },
              ...PENDIDIKAN_LIST.map((p) => ({
                  key: p.key,
                  label: p.label,
                  items: [
                      {
                          type: "total",
                          label: `Jumlah PNS Tingkat Pendidikan ${p.label}`,
                          value: data[p.key].total,
                      },
                      {
                          type: "laki_laki",
                          label: "Laki-Laki",
                          value: data[p.key].laki_laki,
                      },
                      {
                          type: "perempuan",
                          label: "Perempuan",
                          value: data[p.key].perempuan,
                      },
                  ],
              })),
          ]
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                PNS Berdasarkan Tingkat Pendidikan dan Jenis Kelamin
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
                <ChartCardLoading title="Perbandingan Tingkat Pendidikan PNS berdasarkan Gender" />
            )}

            {data && (
                <>
                    <div className="mb-5">
                        <TotalCard
                            title="Jumlah PNS"
                            total={data.jumlah_pns}
                            onClick={() => setPreviewGroup("__all__")}
                        />
                    </div>

                    <ChartCard title="Perbandingan Tingkat Pendidikan PNS berdasarkan Gender">
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
                                title={`Jumlah PNS Tingkat Pendidikan ${p.label}`}
                                total={data[p.key].total}
                                laki_laki={data[p.key].laki_laki}
                                perempuan={data[p.key].perempuan}
                                onClick={() => setPreviewGroup(p.key)}
                            />
                        ))}
                    </div>
                </>
            )}

            {previewGroup && (
                <PreviewTableModal
                    title="Tabel Preview PNS Berdasarkan Tingkat Pendidikan"
                    subtitle="Rekap jumlah PNS per tingkat pendidikan dan jenis kelamin."
                    groups={previewGroups}
                    highlightGroup={previewGroup}
                    onClose={() => setPreviewGroup(null)}
                />
            )}
        </div>
    );
}

export default PnsPendidikanPanel;