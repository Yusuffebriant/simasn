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

// Label resmi per jenjang pendidikan untuk baris "total" pada tabel
// preview (beda formatnya per jenjang — SD/SMP/SMA pakai "atau
// sederajat"/"dan sederajat", Diploma & Strata tidak pakai
// "sederajat" di baris total). Key HARUS sama dengan key di
// PENDIDIKAN_LIST / data.pendidikan (lihat pendidikanList.js).
const PENDIDIKAN_PREVIEW_LABELS = {
    sd: "Jumlah ASN Tingkat Pendidikan Tamat SD atau sederajat",
    smp: "Jumlah ASN Tingkat Pendidikan SMP dan sederajat",
    sma: "Jumlah ASN Tingkat Pendidikan SMA dan sederajat",
    diploma_i: "Jumlah ASN Tingkat Pendidikan Diploma I",
    diploma_ii: "Jumlah ASN Tingkat Pendidikan Diploma II",
    diploma_iii: "Jumlah ASN Tingkat Pendidikan Diploma III",
    diploma_iv: "Jumlah ASN Tingkat Pendidikan Diploma IV",
    strata_1: "Jumlah ASN Tingkat Pendidikan Strata 1",
    strata_2: "Jumlah ASN Tingkat Pendidikan Strata 2",
    strata_3: "Jumlah ASN Tingkat Pendidikan Strata 3",
};

// Baris gender di tabel preview dibuat singkat "Laki-Laki"/"Perempuan"
// (sama seperti modal Pejabat Fungsional), bukan kalimat panjang —
// jadi tidak perlu label khusus per jenjang untuk baris ini.

// ASN Berdasarkan Tingkat Pendidikan dan Jenis Kelamin (PNS + PPPK
// digabung, scope satu kota — lihat statistikAsnPendidikan() di
// StatistikService). PENTING: beda dari PNS/PPPK, hasil per-jenjang
// pendidikan di endpoint ini dibungkus di dalam key "pendidikan"
// (data.pendidikan.sd, data.pendidikan.smp, dst) — bukan langsung di
// root seperti data.sd. Polanya tetap sama seperti GolonganPanel/
// PppkGolonganPanel: TotalCard "Jumlah ASN di Kota Yogyakarta" +
// MiniStatCard per jenjang pendidikan (total + rincian gender), diikuti
// grafik batang perbandingan. Setiap kartu (TotalCard & MiniStatCard)
// bisa diklik untuk membuka tabel preview rincian angka resmi, sama
// seperti pola di FungsionalPanel.
function AsnPendidikanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    // Grup kartu yang sedang di-preview: "total" untuk TotalCard, atau
    // key jenjang pendidikan (mis. "sd", "sma", "strata_1") untuk
    // MiniStatCard. null berarti modal tertutup.
    const [previewGroup, setPreviewGroup] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/asn-pendidikan");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data ASN berdasarkan tingkat pendidikan."
                    );
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data ASN berdasarkan tingkat pendidikan."
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
              laki_laki: data.pendidikan[p.key].laki_laki,
              perempuan: data.pendidikan[p.key].perempuan,
          }))
        : [];

    // Grup "Jumlah ASN di Kota Yogyakarta" (TotalCard) — ditampilkan
    // sendiri sebagai satu baris total tanpa rincian gender.
    const totalGroup = data
        ? {
              key: "total",
              label: "Jumlah ASN di Kota Yogyakarta",
              items: [
                  {
                      type: "total",
                      label: "Jumlah ASN di Kota Yogyakarta",
                      value: data.jumlah_asn,
                  },
              ],
          }
        : null;

    // Satu grup per jenjang pendidikan, mengikuti urutan PENDIDIKAN_LIST
    // supaya konsisten dengan chart & MiniStatCard di atas.
    const pendidikanGroups = data
        ? PENDIDIKAN_LIST.map((p) => {
              const jenjang = data.pendidikan[p.key];
              const totalLabel =
                  PENDIDIKAN_PREVIEW_LABELS[p.key] ||
                  `Jumlah ASN Tingkat Pendidikan ${p.label}`;

              return {
                  key: p.key,
                  label: `Jumlah ASN Tingkat Pendidikan ${p.label}`,
                  items: [
                      { type: "total", label: totalLabel, value: jenjang.total },
                      { type: "laki_laki", label: "Laki-Laki", value: jenjang.laki_laki },
                      { type: "perempuan", label: "Perempuan", value: jenjang.perempuan },
                  ],
              };
          })
        : [];

    const previewGroups = data ? [totalGroup, ...pendidikanGroups] : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                ASN Berdasarkan Tingkat Pendidikan dan Jenis Kelamin
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
                <ChartCardLoading title="Perbandingan Tingkat Pendidikan ASN berdasarkan Gender" />
            )}

            {data && (
                <>
                    <div className="mb-5">
                        <TotalCard
                            title="Jumlah ASN di Kota Yogyakarta"
                            total={data.jumlah_asn}
                            onClick={() => setPreviewGroup("total")}
                        />
                    </div>

                    <ChartCard title="Perbandingan Tingkat Pendidikan ASN berdasarkan Gender">
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
                                title={`Jumlah ASN Tingkat Pendidikan ${p.label}`}
                                total={data.pendidikan[p.key].total}
                                laki_laki={data.pendidikan[p.key].laki_laki}
                                perempuan={data.pendidikan[p.key].perempuan}
                                onClick={() => setPreviewGroup(p.key)}
                            />
                        ))}
                    </div>
                </>
            )}

            {previewGroup && (
                <PreviewTableModal
                    title="Tabel Preview ASN Berdasarkan Tingkat Pendidikan"
                    subtitle="Rekap jumlah ASN per jenjang pendidikan dan jenis kelamin."
                    groups={previewGroups}
                    highlightGroup={previewGroup}
                    onClose={() => setPreviewGroup(null)}
                />
            )}
        </div>
    );
}

export default AsnPendidikanPanel;