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
} from "./components/StatUi";

// Urutan & pengelompokan key harus persis sama dengan key
// `perangkat_daerah` yang dikembalikan
// StatistikService::statistikAsnPerangkatDaerahJenisKelamin() di backend.

const BAGIAN_LIST = [
    { key: "administrasi_dan_keuangan", label: "Bagian Administrasi dan Keuangan" },
    { key: "administrasi_pembangunan", label: "Bagian Administrasi Pembangunan" },
    { key: "hukum", label: "Bagian Hukum" },
    { key: "kesejahteraan_rakyat", label: "Bagian Kesejahteraan Rakyat" },
    { key: "organisasi", label: "Bagian Organisasi" },
    { key: "pengadaan_barang_dan_jasa", label: "Bagian Pengadaan Barang dan Jasa" },
    { key: "perekonomian_dan_kerjasama", label: "Bagian Perekonomian dan Kerjasama" },
    { key: "tata_pemerintahan", label: "Bagian Tata Pemerintahan" },
    { key: "umum_dan_protokol", label: "Bagian Umum dan Protokol" },
];

const DINAS_LIST = [
    { key: "kebudayaan", label: "Dinas Kebudayaan" },
    { key: "kependudukan_dan_pencatatan_sipil", label: "Dinas Kependudukan dan Pencatatan Sipil" },
    { key: "kesehatan", label: "Dinas Kesehatan" },
    { key: "kominfo_persandian", label: "Dinas Komunikasi Informatika dan Persandian" },
    { key: "lingkungan_hidup", label: "Dinas Lingkungan Hidup" },
    { key: "pariwisata", label: "Dinas Pariwisata" },
    { key: "pupr", label: "Dinas Pekerjaan Umum Perumahan dan Kawasan Permukiman" },
    { key: "pemadam_kebakaran", label: "Dinas Pemadam Kebakaran dan Penyelamatan" },
    { key: "p3akb", label: "Dinas Pemberdayaan Perempuan Perlindungan Anak dan Pengendalian Penduduk dan KB" },
    { key: "dpmptsp", label: "Dinas Penanaman Modal dan Pelayanan Terpadu Satu Pintu" },
    { key: "pendidikan_pemuda_olahraga", label: "Dinas Pendidikan Pemuda dan Olahraga" },
    { key: "perdagangan", label: "Dinas Perdagangan" },
    { key: "perhubungan", label: "Dinas Perhubungan" },
    { key: "perindustrian_koperasi_ukm", label: "Dinas Perindustrian Koperasi Usaha Kecil dan Menengah" },
    { key: "perpustakaan_dan_kearsipan", label: "Dinas Perpustakaan dan Kearsipan" },
    { key: "pertanahan_dan_tata_ruang", label: "Dinas Pertanahan dan Tata Ruang" },
    { key: "pertanian_dan_pangan", label: "Dinas Pertanian dan Pangan" },
    { key: "sosial_nakertrans", label: "Dinas Sosial Tenaga Kerja dan Transmigrasi" },
];

const BADAN_LIST = [
    { key: "bkpsdm", label: "Badan Kepegawaian dan Pengembangan Sumber Daya Manusia" },
    { key: "kesbangpol", label: "Badan Kesatuan Bangsa dan Politik" },
    { key: "bpbd", label: "Badan Penanggulangan Bencana Daerah" },
    { key: "bpkad", label: "Badan Pengelola Keuangan dan Aset Daerah" },
    { key: "bappeda", label: "Badan Perencanaan Pembangunan Daerah" },
];

const LEMBAGA_LAIN_LIST = [
    { key: "satpol_pp", label: "Satuan Polisi Pamong Praja" },
    { key: "inspektorat", label: "Inspektorat" },
    { key: "setwan", label: "Sekretariat DPRD" },
    { key: "rsud", label: "RSUD Kota Yogyakarta" },
];

const KEMANTREN_LIST = [
    "TEGALREJO", "JETIS", "GONDOKUSUMAN", "DANUREJAN", "GEDONGTENGEN",
    "NGAMPILAN", "WIROBRAJAN", "MANTRIJERON", "KRATON", "GONDOMANAN",
    "PAKUALAMAN", "MERGANGSAN", "UMBULHARJO", "KOTAGEDE",
];

// Judul rapi ("TEGALREJO" -> "Tegalrejo") untuk label kartu Kemantren.
function toTitleCase(text) {
    return text
        .toLowerCase()
        .replace(/(^|\s)\S/g, (c) => c.toUpperCase());
}

// Satu blok bagian: card putih pembungkus dengan judul + grid MiniStatCard
// rincian — pola sama seperti GolonganSection (GolonganPanel.jsx) dan
// DinasSection (SkpdDinasPanel.jsx), dipakai berulang untuk tiap kelompok
// Perangkat Daerah (Sekretariat Daerah & Bagian, Dinas, Badan, Lembaga
// Lain, Kemantren).
function PerangkatDaerahSection({ title, summary, children }) {
    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5 mb-5">
            <h3 className="text-[#172033] font-semibold mb-4">{title}</h3>

            {summary && <div className="mb-4">{summary}</div>}

            <div
                className="grid gap-4"
                style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}
            >
                {children}
            </div>
        </div>
    );
}

function AsnPerangkatDaerahJenisKelaminPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch(
                    "/statistik/asn-perangkat-daerah-jenis-kelamin"
                );

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data ASN Perangkat Daerah berdasarkan jenis kelamin."
                    );
                }

                const json = await res.json();
                if (!cancelled) setData(json);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data ASN Perangkat Daerah berdasarkan jenis kelamin."
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

    const pd = data?.perangkat_daerah;

    // Jumlahkan laki_laki/perempuan sekelompok key perangkat_daerah,
    // dipakai untuk grafik ringkasan per kelompok (Sekretariat Daerah &
    // Bagian, Dinas, Badan, Lembaga Lain, Kemantren).
    const sumGroup = (keys) => {
        let laki_laki = 0;
        let perempuan = 0;
        keys.forEach((key) => {
            laki_laki += pd?.[key]?.laki_laki || 0;
            perempuan += pd?.[key]?.perempuan || 0;
        });
        return { laki_laki, perempuan };
    };

    // PENTING: kartu total "Jumlah ASN Pemerintah Kota Yogyakarta" DIHITUNG
    // ULANG di sini dari seluruh baris perangkat_daerah yang benar-benar
    // ditampilkan di panel ini (Sekretariat Daerah, semua Bagian, semua
    // Dinas, semua Badan, Lembaga Lain, Kemantren, dan "Lainnya / Belum
    // Terpetakan") — BUKAN memakai field data.jumlah_asn dari API apa
    // adanya. Dengan begini kartu total dijamin sama dengan hasil
    // penjumlahan rincian di bawahnya, murni di sisi frontend, terlepas
    // dari bagaimana backend menghitung jumlah_asn.
    const totalDariRincian = pd
        ? (() => {
              const semuaKeyLeaf = [
                  "sekretariat_daerah",
                  ...BAGIAN_LIST.map((b) => b.key),
                  ...DINAS_LIST.map((d) => d.key),
                  ...BADAN_LIST.map((b) => b.key),
                  ...LEMBAGA_LAIN_LIST.map((l) => l.key),
              ];

              let { laki_laki, perempuan } = sumGroup(semuaKeyLeaf);

              // Kemantren sudah berupa satu entri agregat (jangan ambil
              // dari .detail lagi supaya tidak dobel hitung).
              laki_laki += pd.kemantren?.laki_laki || 0;
              perempuan += pd.kemantren?.perempuan || 0;

              // "Lainnya / Belum Terpetakan" tetap ikut dijumlah supaya
              // kartu total mencerminkan SELURUH ASN yang tampil di
              // panel ini, termasuk yang belum masuk OPD manapun.
              laki_laki += pd.lainnya?.laki_laki || 0;
              perempuan += pd.lainnya?.perempuan || 0;

              return { laki_laki, perempuan, total: laki_laki + perempuan };
          })()
        : null;

    const chartData = pd
        ? [
              {
                  label: "Sekretariat Daerah & Bagian",
                  ...sumGroup([
                      "sekretariat_daerah",
                      ...BAGIAN_LIST.map((b) => b.key),
                  ]),
              },
              {
                  label: "Dinas",
                  ...sumGroup(DINAS_LIST.map((d) => d.key)),
              },
              {
                  label: "Badan Daerah",
                  ...sumGroup(BADAN_LIST.map((b) => b.key)),
              },
              {
                  label: "Lembaga Lain",
                  ...sumGroup(LEMBAGA_LAIN_LIST.map((l) => l.key)),
              },
              {
                  label: "Kemantren",
                  laki_laki: pd.kemantren?.laki_laki || 0,
                  perempuan: pd.kemantren?.perempuan || 0,
              },
          ]
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                ASN Perangkat Daerah Berdasarkan Jenis Kelamin
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
                <ChartCardLoading title="Perbandingan ASN per Kelompok Perangkat Daerah berdasarkan Gender" />
            )}

            {data && pd && (
                <>
                    <div className="mb-5">
                        <MiniStatCard
                            title="Jumlah ASN Pemerintah Kota Yogyakarta"
                            total={totalDariRincian.total}
                            laki_laki={totalDariRincian.laki_laki}
                            perempuan={totalDariRincian.perempuan}
                            variant="dark"
                        />
                    </div>

                    <ChartCard title="Perbandingan ASN per Kelompok Perangkat Daerah berdasarkan Gender">
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

                    <div className="mt-6">
                        <PerangkatDaerahSection title="Sekretariat Daerah & Bagian">
                            <MiniStatCard
                                title="Sekretariat Daerah"
                                total={pd.sekretariat_daerah.total}
                                laki_laki={pd.sekretariat_daerah.laki_laki}
                                perempuan={pd.sekretariat_daerah.perempuan}
                            />
                            {BAGIAN_LIST.map((b) => (
                                <MiniStatCard
                                    key={b.key}
                                    title={b.label}
                                    total={pd[b.key].total}
                                    laki_laki={pd[b.key].laki_laki}
                                    perempuan={pd[b.key].perempuan}
                                />
                            ))}
                        </PerangkatDaerahSection>

                        <PerangkatDaerahSection title="Dinas">
                            {DINAS_LIST.map((d) => (
                                <MiniStatCard
                                    key={d.key}
                                    title={d.label}
                                    total={pd[d.key].total}
                                    laki_laki={pd[d.key].laki_laki}
                                    perempuan={pd[d.key].perempuan}
                                />
                            ))}
                        </PerangkatDaerahSection>

                        <PerangkatDaerahSection title="Badan Daerah">
                            {BADAN_LIST.map((b) => (
                                <MiniStatCard
                                    key={b.key}
                                    title={b.label}
                                    total={pd[b.key].total}
                                    laki_laki={pd[b.key].laki_laki}
                                    perempuan={pd[b.key].perempuan}
                                />
                            ))}
                        </PerangkatDaerahSection>

                        <PerangkatDaerahSection title="Satpol PP, Inspektorat, Sekretariat DPRD & RSUD">
                            {LEMBAGA_LAIN_LIST.map((l) => (
                                <MiniStatCard
                                    key={l.key}
                                    title={l.label}
                                    total={pd[l.key].total}
                                    laki_laki={pd[l.key].laki_laki}
                                    perempuan={pd[l.key].perempuan}
                                />
                            ))}
                        </PerangkatDaerahSection>

                        <PerangkatDaerahSection
                            title="Kemantren"
                            summary={
                                <MiniStatCard
                                    title="Jumlah ASN Kemantren"
                                    total={pd.kemantren.total}
                                    laki_laki={pd.kemantren.laki_laki}
                                    perempuan={pd.kemantren.perempuan}
                                    variant="dark"
                                />
                            }
                        >
                            {KEMANTREN_LIST.map((nama) => {
                                const detail = pd.kemantren.detail?.[nama] || {
                                    total: 0,
                                    laki_laki: 0,
                                    perempuan: 0,
                                };
                                return (
                                    <MiniStatCard
                                        key={nama}
                                        title={`Kemantren ${toTitleCase(nama)}`}
                                        total={detail.total}
                                        laki_laki={detail.laki_laki}
                                        perempuan={detail.perempuan}
                                    />
                                );
                            })}
                        </PerangkatDaerahSection>
                    </div>
                </>
            )}
        </div>
    );
}

export default AsnPerangkatDaerahJenisKelaminPanel;