import { useEffect, useState } from "react";
import { LoaderCircle } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { ErrorBox } from "./components/StatUi";

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

// Judul rapi ("TEGALREJO" -> "Tegalrejo") untuk label baris Kemantren.
function toTitleCase(text) {
    return text
        .toLowerCase()
        .replace(/(^|\s)\S/g, (c) => c.toUpperCase());
}

// Tabel generik L/P/Total (No / label / L / P / Total), dipakai berulang
// untuk tiap kelompok Perangkat Daerah — pola sama seperti
// StrukturalPanel/FungsionalPanel/PnsPendidikanPanel & tabel Rekapitulasi
// ASN di halaman Admin. Baris Total di tfoot dijumlah otomatis dari rows.
function GenderTable({ title, description, columnLabel, rows }) {
    const totalLakiLaki = rows.reduce((sum, r) => sum + (r.laki_laki || 0), 0);
    const totalPerempuan = rows.reduce((sum, r) => sum + (r.perempuan || 0), 0);
    const totalJumlah = rows.reduce((sum, r) => sum + (r.total || 0), 0);

    return (
        <div className="bg-white p-6 rounded-xl shadow mb-5">
            <div className="mb-5">
                <h3 className="text-lg font-bold text-[#172033]">{title}</h3>
                {description && (
                    <p className="text-sm text-gray-500">{description}</p>
                )}
            </div>
            <div className="overflow-x-auto">
                <table className="min-w-full text-sm border border-gray-200">
                    <thead>
                        <tr className="bg-gray-50">
                            <th className="border px-3 py-2 text-left">No</th>
                            <th className="border px-3 py-2 text-left">{columnLabel}</th>
                            <th className="border px-2 py-2 text-center">L</th>
                            <th className="border px-2 py-2 text-center">P</th>
                            <th className="border px-2 py-2 text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, i) => (
                            <tr key={row.label} className="hover:bg-gray-50">
                                <td className="border px-3 py-2">{i + 1}</td>
                                <td className="border px-3 py-2">{row.label}</td>
                                <td className="border px-2 py-2 text-center">{row.laki_laki || 0}</td>
                                <td className="border px-2 py-2 text-center">{row.perempuan || 0}</td>
                                <td className="border px-2 py-2 text-center font-medium">{row.total || 0}</td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="bg-gray-100 font-bold">
                            <td colSpan={2} className="border px-3 py-2">Total</td>
                            <td className="border px-2 py-2 text-center">{totalLakiLaki}</td>
                            <td className="border px-2 py-2 text-center">{totalPerempuan}</td>
                            <td className="border px-2 py-2 text-center">{totalJumlah}</td>
                        </tr>
                    </tfoot>
                </table>
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
    // dipakai untuk baris ringkasan per kelompok (Sekretariat Daerah &
    // Bagian, Dinas, Badan, Lembaga Lain, Kemantren, Lainnya).
    const sumGroup = (keys) => {
        let laki_laki = 0;
        let perempuan = 0;
        keys.forEach((key) => {
            laki_laki += pd?.[key]?.laki_laki || 0;
            perempuan += pd?.[key]?.perempuan || 0;
        });
        return { laki_laki, perempuan, total: laki_laki + perempuan };
    };

    // Baris ringkasan per kelompok (dipakai sebagai pengganti grafik batang
    // yang lama). "Lainnya / Belum Terpetakan" tetap ditampilkan sebagai
    // baris sendiri supaya Total di tfoot tabel ini sama persis dengan
    // "Jumlah ASN Pemerintah Kota Yogyakarta" di bawah — tidak ada angka
    // yang dihitung tapi disembunyikan dari tabel.
    const groupRows = pd
        ? [
              {
                  label: "Sekretariat Daerah & Bagian",
                  ...sumGroup(["sekretariat_daerah", ...BAGIAN_LIST.map((b) => b.key)]),
              },
              { label: "Dinas", ...sumGroup(DINAS_LIST.map((d) => d.key)) },
              { label: "Badan Daerah", ...sumGroup(BADAN_LIST.map((b) => b.key)) },
              { label: "Lembaga Lain", ...sumGroup(LEMBAGA_LAIN_LIST.map((l) => l.key)) },
              {
                  label: "Kemantren",
                  laki_laki: pd.kemantren?.laki_laki || 0,
                  perempuan: pd.kemantren?.perempuan || 0,
                  total: pd.kemantren?.total || 0,
              },
              {
                  label: "Lainnya / Belum Terpetakan",
                  laki_laki: pd.lainnya?.laki_laki || 0,
                  perempuan: pd.lainnya?.perempuan || 0,
                  total: pd.lainnya?.total || 0,
              },
          ]
        : [];

    const sekretariatRows = pd
        ? [
              {
                  label: "Sekretariat Daerah",
                  laki_laki: pd.sekretariat_daerah.laki_laki,
                  perempuan: pd.sekretariat_daerah.perempuan,
                  total: pd.sekretariat_daerah.total,
              },
              ...BAGIAN_LIST.map((b) => ({
                  label: b.label,
                  laki_laki: pd[b.key].laki_laki,
                  perempuan: pd[b.key].perempuan,
                  total: pd[b.key].total,
              })),
          ]
        : [];

    const dinasRows = pd
        ? DINAS_LIST.map((d) => ({
              label: d.label,
              laki_laki: pd[d.key].laki_laki,
              perempuan: pd[d.key].perempuan,
              total: pd[d.key].total,
          }))
        : [];

    const badanRows = pd
        ? BADAN_LIST.map((b) => ({
              label: b.label,
              laki_laki: pd[b.key].laki_laki,
              perempuan: pd[b.key].perempuan,
              total: pd[b.key].total,
          }))
        : [];

    const lembagaLainRows = pd
        ? LEMBAGA_LAIN_LIST.map((l) => ({
              label: l.label,
              laki_laki: pd[l.key].laki_laki,
              perempuan: pd[l.key].perempuan,
              total: pd[l.key].total,
          }))
        : [];

    const kemantrenRows = pd
        ? KEMANTREN_LIST.map((nama) => {
              const detail = pd.kemantren.detail?.[nama] || {
                  total: 0,
                  laki_laki: 0,
                  perempuan: 0,
              };
              return {
                  label: `Kemantren ${toTitleCase(nama)}`,
                  laki_laki: detail.laki_laki,
                  perempuan: detail.perempuan,
                  total: detail.total,
              };
          })
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                ASN Perangkat Daerah Berdasarkan Jenis Kelamin
            </h2>

            {error && <ErrorBox message={error} />}

            {loading && !data && (
                <div className="flex items-center justify-center gap-2 text-gray-500 py-12 text-sm">
                    <LoaderCircle className="animate-spin" size={18} />
                    Memuat data...
                </div>
            )}

            {data && pd && (
                <>
                    <GenderTable
                        title="Perbandingan ASN per Kelompok Perangkat Daerah"
                        description="Ringkasan jumlah ASN per kelompok Perangkat Daerah, dipecah menurut jenis kelamin. Baris Total sama dengan Jumlah ASN Pemerintah Kota Yogyakarta secara keseluruhan."
                        columnLabel="Kelompok Perangkat Daerah"
                        rows={groupRows}
                    />

                    <GenderTable
                        title="Sekretariat Daerah & Bagian"
                        columnLabel="Unit"
                        rows={sekretariatRows}
                    />

                    <GenderTable
                        title="Dinas"
                        columnLabel="Dinas"
                        rows={dinasRows}
                    />

                    <GenderTable
                        title="Badan Daerah"
                        columnLabel="Badan"
                        rows={badanRows}
                    />

                    <GenderTable
                        title="Satpol PP, Inspektorat, Sekretariat DPRD & RSUD"
                        columnLabel="Unit"
                        rows={lembagaLainRows}
                    />

                    <GenderTable
                        title="Kemantren"
                        description="Jumlah ASN Kemantren keseluruhan ada pada baris Total tabel ini."
                        columnLabel="Kemantren"
                        rows={kemantrenRows}
                    />
                </>
            )}
        </div>
    );
}

export default AsnPerangkatDaerahJenisKelaminPanel;