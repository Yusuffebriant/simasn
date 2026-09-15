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

// PNS Kemantren Berdasarkan Tingkat Pendidikan dan Jenis Kelamin (5.03.015).
// Scope: hanya PNS aktif (status_kepegawaian = 'PNS') yang ber-UNIT salah
// satu dari 14 Kemantren Kota Yogyakarta — lihat
// statistikPnsKemantrenPendidikan() di StatistikService & endpoint
// GET /statistik/pns-kemantren-pendidikan. Beda dengan
// AsnKemantrenPendidikanPanel.jsx (5.03.014): data di sini dipecah lagi
// per gender (nested pendidikan -> gender -> kemantren), jadi kartu
// rincian per Kemantren pakai MiniStatCard (total + rincian laki-laki/
// perempuan) — pola sama seperti PnsPendidikanPanel.jsx, digabung dengan
// struktur per-Kemantren dari AsnKemantrenPendidikanPanel.jsx.
const KEMANTREN_LIST = [
    "TEGALREJO", "JETIS", "GONDOKUSUMAN", "DANUREJAN", "GEDONGTENGEN",
    "NGAMPILAN", "WIROBRAJAN", "MANTRIJERON", "KRATON", "GONDOMANAN",
    "PAKUALAMAN", "MERGANGSAN", "UMBULHARJO", "KOTAGEDE",
];

// Judul rapi ("TEGALREJO" -> "Tegalrejo") untuk label kartu & grafik
// Kemantren — sama seperti helper di AsnKemantrenPendidikanPanel.jsx.
function toTitleCase(text) {
    return text
        .toLowerCase()
        .replace(/(^|\s)\S/g, (c) => c.toUpperCase());
}

// Satu blok bagian per jenjang pendidikan: card putih pembungkus dengan
// judul + TotalCard ringkasan jenjang + grid MiniStatCard rincian per
// Kemantren (total + gender). Pola sama seperti KemantrenPendidikanSection
// di AsnKemantrenPendidikanPanel.jsx, tapi grid-nya pakai MiniStatCard
// karena data di sini punya rincian gender per Kemantren.
function KemantrenPendidikanSection({ title, total, onClick, children }) {
    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5 mb-5">
            <h3 className="text-[#172033] font-semibold mb-4">{title}</h3>
            <div className="mb-4">
                <TotalCard title={title} total={total} onClick={onClick} />
            </div>
            <div
                className="grid gap-4"
                style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
            >
                {children}
            </div>
        </div>
    );
}

function PnsKemantrenPendidikanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    // Jenjang pendidikan yang sedang di-preview (key di PENDIDIKAN_LIST,
    // mis. "sd", "smp", dst). null berarti modal tertutup. Nilai khusus
    // "__all__" dipakai saat TotalCard "Jumlah PNS Kemantren" diklik.
    const [previewGroup, setPreviewGroup] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pns-kemantren-pendidikan");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data PNS Kemantren berdasarkan tingkat pendidikan."
                    );
                }

                const json = await res.json();
                if (!cancelled) setData(json.data ?? json);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data PNS Kemantren berdasarkan tingkat pendidikan."
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

    // Grafik ringkasan: total PNS Kemantren per jenjang pendidikan
    // (dijumlahkan dari seluruh 14 Kemantren), dipecah laki-laki/
    // perempuan — pola sama seperti grafik ringkasan di
    // PnsPendidikanPanel.jsx.
    const ringkasanChartData = data
        ? PENDIDIKAN_LIST.map((p) => ({
              label: p.chartLabel,
              laki_laki: data.pendidikan[p.key].laki_laki.total,
              perempuan: data.pendidikan[p.key].perempuan.total,
          }))
        : [];

    // Daftar grup untuk tabel preview, 3 tingkat rincian per jenjang
    // pendidikan: (1) total jenjang, (2) total per gender, (3) rincian
    // per Kemantren untuk masing-masing gender — mengikuti label resmi
    // "Jumlah PNS Kemantren berdasarkan Tingkat Pendidikan {jenjang}",
    // "... {jenjang} Laki-laki/Perempuan", dan
    // "Jumlah PNS Tingkat Pendidikan {jenjang} Laki-laki/Perempuan
    // Kemantren {nama}". Grup pertama ("__all__") adalah ringkasan
    // keseluruhan dari kartu "Jumlah PNS Kemantren" di atas.
    const previewGroups = data
        ? [
              {
                  key: "__all__",
                  label: "Jumlah Keseluruhan PNS Kemantren",
                  items: [
                      {
                          type: "total",
                          label: "Jumlah PNS Kemantren",
                          value: data.jumlah_pns_kemantren,
                      },
                      {
                          type: "laki_laki",
                          label: "Laki-laki",
                          value: PENDIDIKAN_LIST.reduce(
                              (sum, p) => sum + (data.pendidikan[p.key].laki_laki.total || 0),
                              0
                          ),
                      },
                      {
                          type: "perempuan",
                          label: "Perempuan",
                          value: PENDIDIKAN_LIST.reduce(
                              (sum, p) => sum + (data.pendidikan[p.key].perempuan.total || 0),
                              0
                          ),
                      },
                  ],
              },
              ...PENDIDIKAN_LIST.map((p) => {
                  const jenjang = data.pendidikan[p.key];
                  const perKemantrenL = jenjang.laki_laki.kemantren;
                  const perKemantrenP = jenjang.perempuan.kemantren;

                  return {
                      key: p.key,
                      label: `Tingkat Pendidikan ${p.label}`,
                      items: [
                          {
                              type: "total",
                              indent: 0,
                              label: `Jumlah PNS Kemantren berdasarkan Tingkat Pendidikan ${p.label}`,
                              value: jenjang.total,
                          },
                          {
                              type: "laki_laki",
                              indent: 1,
                              label: `Jumlah PNS Kemantren berdasarkan Tingkat Pendidikan ${p.label} Laki-laki`,
                              value: jenjang.laki_laki.total,
                          },
                          // Label rincian per Kemantren dipersingkat jadi "Kemantren
                          // {nama}" saja — konteks jenjang pendidikan & gender-nya
                          // sudah jelas dari baris "Laki-laki"/"Perempuan" tepat di
                          // atasnya, jadi tidak perlu diulang di 14 baris berikutnya
                          // (biar tidak jadi tembok teks panjang berulang-ulang).
                          ...KEMANTREN_LIST.map((nama) => ({
                              type: "sub",
                              indent: 2,
                              label: `Kemantren ${toTitleCase(nama)}`,
                              value: perKemantrenL[nama] || 0,
                          })),
                          {
                              type: "perempuan",
                              indent: 1,
                              label: `Jumlah PNS Kemantren berdasarkan Tingkat Pendidikan ${p.label} Perempuan`,
                              value: jenjang.perempuan.total,
                          },
                          ...KEMANTREN_LIST.map((nama) => ({
                              type: "sub",
                              indent: 2,
                              label: `Kemantren ${toTitleCase(nama)}`,
                              value: perKemantrenP[nama] || 0,
                          })),
                      ],
                  };
              }),
              // Grup kecil 1 per kartu Kemantren (10 jenjang x 14 Kemantren =
              // 140 grup) — supaya SETIAP kartu MiniStatCard rincian per
              // Kemantren di bawah juga bisa diklik & langsung menyorot
              // grup kecilnya sendiri di tabel preview, persis pola kartu
              // "Fungsional Dosen/Guru/dst" di Tabel Preview Pejabat
              // Fungsional (setiap kartu -> grup sendiri yang di-highlight).
              ...PENDIDIKAN_LIST.flatMap((p) => {
                  const jenjang = data.pendidikan[p.key];
                  const perKemantrenL = jenjang.laki_laki.kemantren;
                  const perKemantrenP = jenjang.perempuan.kemantren;

                  return KEMANTREN_LIST.map((nama) => {
                      const laki_laki = perKemantrenL[nama] || 0;
                      const perempuan = perKemantrenP[nama] || 0;

                      return {
                          key: `${p.key}__${nama}`,
                          label: `Kemantren ${toTitleCase(nama)} — Tingkat Pendidikan ${p.label}`,
                          items: [
                              {
                                  type: "total",
                                  label: `Jumlah PNS Tingkat Pendidikan ${p.label} Kemantren ${toTitleCase(nama)}`,
                                  value: laki_laki + perempuan,
                              },
                              {
                                  type: "laki_laki",
                                  label: `Jumlah PNS Tingkat Pendidikan ${p.label} Laki-laki Kemantren ${toTitleCase(nama)}`,
                                  value: laki_laki,
                              },
                              {
                                  type: "perempuan",
                                  label: `Jumlah PNS Tingkat Pendidikan ${p.label} Perempuan Kemantren ${toTitleCase(nama)}`,
                                  value: perempuan,
                              },
                          ],
                      };
                  });
              }),
          ]
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                PNS Kemantren Berdasarkan Tingkat Pendidikan dan Jenis Kelamin
            </h2>

            {error && <ErrorBox message={error} />}

            {loading && !data && (
                <div className="mb-6">
                    <SkeletonCard />
                </div>
            )}

            {loading && !data && (
                <ChartCardLoading title="Jumlah PNS Kemantren berdasarkan Tingkat Pendidikan" />
            )}

            {data && (
                <>
                    <div className="mb-5">
                        <TotalCard
                            title="Jumlah PNS Kemantren"
                            total={data.jumlah_pns_kemantren}
                            onClick={() => setPreviewGroup("__all__")}
                        />
                    </div>

                    <ChartCard title="Jumlah PNS Kemantren berdasarkan Tingkat Pendidikan dan Jenis Kelamin">
                        <BarChart data={ringkasanChartData}>
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

                    <div className="mt-6">
                        {PENDIDIKAN_LIST.map((p) => {
                            const jenjang = data.pendidikan[p.key];
                            const perKemantrenL = jenjang.laki_laki.kemantren;
                            const perKemantrenP = jenjang.perempuan.kemantren;

                            const chartData = KEMANTREN_LIST.map((nama) => ({
                                label: toTitleCase(nama),
                                laki_laki: perKemantrenL[nama] || 0,
                                perempuan: perKemantrenP[nama] || 0,
                            }));

                            return (
                                <div key={p.key} className="mb-6">
                                    <KemantrenPendidikanSection
                                        title={`Jumlah PNS Kemantren berdasarkan Tingkat Pendidikan ${p.label} per Kemantren`}
                                        total={jenjang.total}
                                        onClick={() => setPreviewGroup(p.key)}
                                    >
                                        {KEMANTREN_LIST.map((nama) => (
                                            <MiniStatCard
                                                key={nama}
                                                title={`Kemantren ${toTitleCase(nama)}`}
                                                total={
                                                    (perKemantrenL[nama] || 0) +
                                                    (perKemantrenP[nama] || 0)
                                                }
                                                laki_laki={perKemantrenL[nama] || 0}
                                                perempuan={perKemantrenP[nama] || 0}
                                                onClick={() => setPreviewGroup(`${p.key}__${nama}`)}
                                            />
                                        ))}
                                    </KemantrenPendidikanSection>

                                    <ChartCard
                                        title={`PNS Tingkat Pendidikan ${p.label} per Kemantren berdasarkan Jenis Kelamin`}
                                    >
                                        <BarChart data={chartData} margin={{ bottom: 30 }}>
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
                            );
                        })}
                    </div>
                </>
            )}

            {previewGroup && (
                <PreviewTableModal
                    title="Tabel Preview PNS Kemantren Berdasarkan Tingkat Pendidikan"
                    subtitle="Rekap jumlah PNS Kemantren per tingkat pendidikan, jenis kelamin, dan Kemantren."
                    groups={previewGroups}
                    highlightGroup={previewGroup}
                    onClose={() => setPreviewGroup(null)}
                />
            )}
        </div>
    );
}

export default PnsKemantrenPendidikanPanel;