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
} from "./components/StatUi";

function FungsionalPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    // Grup kartu yang sedang di-preview (mis. "fungsional_umum",
    // "dosen", dst). null berarti modal tertutup. Dipakai juga untuk
    // menyorot baris terkait di dalam tabel preview.
    const [previewGroup, setPreviewGroup] = useState(null);

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

    const genderChartData = data
        ? [
              {
                  label: "Fungsional Umum (JFU)",
                  laki_laki: data.fungsional_umum.laki_laki,
                  perempuan: data.fungsional_umum.perempuan,
              },
              {
                  label: "Fungsional Tertentu (JFT)",
                  laki_laki: data.fungsional_tertentu.laki_laki,
                  perempuan: data.fungsional_tertentu.perempuan,
              },
          ]
        : [];

    // Total per rumpun dihitung dari data yang sudah ada di API
    // (fungsional_tertentu_laki_laki / fungsional_tertentu_perempuan).
    const rumpun = data
        ? ["dosen", "guru", "medis", "teknis", "auditor", "p2upd"].reduce((acc, key) => {
              const laki_laki = data.fungsional_tertentu_laki_laki?.[key] || 0;
              const perempuan = data.fungsional_tertentu_perempuan?.[key] || 0;
              acc[key] = { laki_laki, perempuan, total: laki_laki + perempuan };
              return acc;
          }, {})
        : {};

    // Daftar grup untuk tabel preview, mengikuti persis 20 label resmi
    // yang dipakai untuk laporan Pejabat Fungsional — dikelompokkan per
    // kategori (1 baris total + rincian laki-laki/perempuan) supaya
    // tampilan modal rapi & gampang dipindai, bukan tabel datar 20 baris.
    const previewGroups = data
        ? [
              {
                  key: "fungsional_umum",
                  label: "Fungsional Umum (JFU)",
                  items: [
                      { type: "total", label: "Jumlah Pemangku Jabatan Fungsional Umum Pada Instansi Pemerintah", value: data.fungsional_umum.total },
                      { type: "laki_laki", label: "Laki-Laki", value: data.fungsional_umum.laki_laki },
                      { type: "perempuan", label: "Perempuan", value: data.fungsional_umum.perempuan },
                  ],
              },
              {
                  key: "fungsional_tertentu",
                  label: "Fungsional Tertentu (JFT)",
                  items: [
                      { type: "total", label: "Jumlah Pemangku Jabatan Fungsional Tertentu Pada Instansi Pemerintah", value: data.fungsional_tertentu.total },
                      { type: "laki_laki", label: "Laki-Laki", value: data.fungsional_tertentu.laki_laki },
                      { type: "perempuan", label: "Perempuan", value: data.fungsional_tertentu.perempuan },
                  ],
              },
              {
                  key: "dosen",
                  label: "Fungsional Dosen",
                  items: [
                      { type: "total", label: "Jumlah Pemangku Jabatan Fungsional Dosen Pada Instansi Pemerintah", value: rumpun.dosen.total },
                      { type: "laki_laki", label: "Laki-Laki", value: rumpun.dosen.laki_laki },
                      { type: "perempuan", label: "Perempuan", value: rumpun.dosen.perempuan },
                  ],
              },
              {
                  key: "guru",
                  label: "Fungsional Guru",
                  items: [
                      { type: "total", label: "Jumlah Pemangku Jabatan Fungsional Guru Pada Instansi Pemerintah", value: rumpun.guru.total },
                      { type: "laki_laki", label: "Laki-Laki", value: rumpun.guru.laki_laki },
                      { type: "perempuan", label: "Perempuan", value: rumpun.guru.perempuan },
                  ],
              },
              {
                  key: "medis",
                  label: "Fungsional Medis",
                  items: [
                      { type: "total", label: "Jumlah Pemangku Jabatan Fungsional Medis Pada Instansi Pemerintah", value: rumpun.medis.total },
                      { type: "laki_laki", label: "Laki-Laki", value: rumpun.medis.laki_laki },
                      { type: "perempuan", label: "Perempuan", value: rumpun.medis.perempuan },
                  ],
              },
              {
                  key: "teknis",
                  label: "Fungsional Teknis",
                  items: [
                      { type: "total", label: "Jumlah Pemangku Jabatan Fungsional Teknis Pada Instansi Pemerintah", value: rumpun.teknis.total },
                      { type: "laki_laki", label: "Laki-Laki", value: rumpun.teknis.laki_laki },
                      { type: "perempuan", label: "Perempuan", value: rumpun.teknis.perempuan },
                  ],
              },
              {
                  key: "auditor",
                  label: "Fungsional Auditor",
                  items: [
                      { type: "total", label: "Jumlah Pejabat Fungsional Auditor", value: rumpun.auditor.total },
                  ],
              },
              {
                  key: "p2upd",
                  label: "Fungsional P2UPD",
                  items: [
                      { type: "total", label: "Jumlah Pejabat Fungsional P2UPD", value: rumpun.p2upd.total },
                  ],
              },
          ]
        : [];

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
                <ChartCardLoading title="Perbandingan Pejabat Fungsional berdasarkan Gender" />
            )}

            {data && (
                <>
                    <div className="flex flex-col gap-4 mb-5">
                        <div
                            className="grid gap-4"
                            style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
                        >
                            <MiniStatCard
                                title="Fungsional Umum (JFU)"
                                {...data.fungsional_umum}
                                variant="dark"
                                onClick={() => setPreviewGroup("fungsional_umum")}
                            />
                            <MiniStatCard
                                title="Fungsional Tertentu (JFT)"
                                {...data.fungsional_tertentu}
                                variant="dark"
                                onClick={() => setPreviewGroup("fungsional_tertentu")}
                            />
                        </div>

                        <div
                            className="grid gap-4"
                            style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
                        >
                            <MiniStatCard title="Fungsional Dosen" {...rumpun.dosen} onClick={() => setPreviewGroup("dosen")} />
                            <MiniStatCard title="Fungsional Guru" {...rumpun.guru} onClick={() => setPreviewGroup("guru")} />
                            <MiniStatCard title="Fungsional Medis" {...rumpun.medis} onClick={() => setPreviewGroup("medis")} />
                        </div>

                        <div
                            className="grid gap-4"
                            style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
                        >
                            <MiniStatCard title="Fungsional Teknis" {...rumpun.teknis} onClick={() => setPreviewGroup("teknis")} />
                            <MiniStatCard title="Fungsional Auditor" {...rumpun.auditor} onClick={() => setPreviewGroup("auditor")} />
                            <MiniStatCard title="Fungsional P2UPD" {...rumpun.p2upd} onClick={() => setPreviewGroup("p2upd")} />
                        </div>
                    </div>

                    <ChartCard title="Perbandingan Pejabat Fungsional berdasarkan Gender">
                        <BarChart data={genderChartData}>
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

            {previewGroup && (
                <PreviewTableModal
                    title="Tabel Preview Pejabat Fungsional"
                    subtitle="Rekap jumlah pemangku jabatan fungsional per kategori dan jenis kelamin."
                    groups={previewGroups}
                    highlightGroup={previewGroup}
                    onClose={() => setPreviewGroup(null)}
                />
            )}
        </div>
    );
}

export default FungsionalPanel;