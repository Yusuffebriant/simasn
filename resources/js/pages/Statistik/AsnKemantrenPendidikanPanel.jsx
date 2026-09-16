import { useEffect, useState } from "react";
import { LoaderCircle } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { ErrorBox } from "./components/StatUi";
import ExportExcelButton from "./components/ExportExcelButton";
import { PENDIDIKAN_LIST } from "./pendidikanList";

// ASN Kemantren Berdasarkan Tingkat Pendidikan (5.03.014).
// Scope: seluruh ASN aktif (PNS + PPPK, semua jenis_kedudukan) yang
// ber-UNIT salah satu dari 14 Kemantren Kota Yogyakarta — lihat
// statistikAsnKemantrenPendidikan() di StatistikService & endpoint
// GET /statistik/asn-kemantren-pendidikan. Data di sini TIDAK dipecah
// per gender (beda dengan statistikPnsKemantrenPendidikan()), tapi ADA
// dua dimensi (Kemantren x Tingkat Pendidikan), jadi ditampilkan sebagai
// satu tabel silang (baris = Kemantren, kolom = jenjang pendidikan) —
// pola sama seperti RekapKategoriTable di halaman Rekapitulasi ASN Admin
// (baris = instansi, kolom = kategori), bukan grid StatCard per jenjang.
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

function AsnKemantrenPendidikanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/asn-kemantren-pendidikan");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data ASN Kemantren berdasarkan tingkat pendidikan."
                    );
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data ASN Kemantren berdasarkan tingkat pendidikan."
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

    // Baris tabel: satu baris per Kemantren, kolom per jenjang pendidikan
    // (diambil dari data.pendidikan[key].kemantren[NAMA]), plus kolom
    // Total baris (jumlah seluruh jenjang untuk Kemantren tsb).
    const rows = data
        ? KEMANTREN_LIST.map((nama) => {
              const perPendidikan = PENDIDIKAN_LIST.map(
                  (p) => data.pendidikan[p.key]?.kemantren?.[nama] || 0
              );
              const totalBaris = perPendidikan.reduce((sum, v) => sum + v, 0);
              return { nama, perPendidikan, totalBaris };
          })
        : [];

    // Total per kolom (jenjang pendidikan), dijumlah dari seluruh baris
    // Kemantren di atas.
    const totalPerKolom = PENDIDIKAN_LIST.map((_, colIdx) =>
        rows.reduce((sum, row) => sum + row.perPendidikan[colIdx], 0)
    );
    const grandTotal = data ? data.jumlah_asn_kemantren : 0;

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-start justify-between gap-3 mb-5">
                <div>
                    <h3 className="text-lg font-bold text-[#172033]">
                        ASN Kemantren Berdasarkan Tingkat Pendidikan
                    </h3>
                    <p className="text-sm text-gray-500">
                        Data ASN aktif (PNS + PPPK) di 14 Kemantren Kota Yogyakarta, dipecah menurut tingkat pendidikan.
                    </p>
                </div>

                <ExportExcelButton
                    path="/statistik/asn-kemantren-pendidikan/export"
                    filename="asn-kemantren-pendidikan.xlsx"
                    errorMessage="Gagal mengekspor data ASN Kemantren berdasarkan tingkat pendidikan."
                    disabled={loading || !data}
                    onError={setError}
                />
            </div>

            {error && <ErrorBox message={error} />}

            {loading && !data ? (
                <div className="flex items-center justify-center gap-2 text-gray-500 py-12 text-sm">
                    <LoaderCircle className="animate-spin" size={18} />
                    Memuat data...
                </div>
            ) : data ? (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm border border-gray-200">
                        <thead>
                            <tr className="bg-gray-50">
                                <th className="border px-3 py-2 text-left">No</th>
                                <th className="border px-3 py-2 text-left">Kemantren</th>
                                {PENDIDIKAN_LIST.map((p) => (
                                    <th key={p.key} className="border px-2 py-2 text-center">
                                        {p.chartLabel}
                                    </th>
                                ))}
                                <th className="border px-2 py-2 text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, i) => (
                                <tr key={row.nama} className="hover:bg-gray-50">
                                    <td className="border px-3 py-2">{i + 1}</td>
                                    <td className="border px-3 py-2">
                                        Kemantren {toTitleCase(row.nama)}
                                    </td>
                                    {row.perPendidikan.map((val, idx) => (
                                        <td
                                            key={PENDIDIKAN_LIST[idx].key}
                                            className="border px-2 py-2 text-center"
                                        >
                                            {val}
                                        </td>
                                    ))}
                                    <td className="border px-2 py-2 text-center font-medium">
                                        {row.totalBaris}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-gray-100 font-bold">
                                <td colSpan={2} className="border px-3 py-2">Total</td>
                                {totalPerKolom.map((val, idx) => (
                                    <td
                                        key={PENDIDIKAN_LIST[idx].key}
                                        className="border px-2 py-2 text-center"
                                    >
                                        {val}
                                    </td>
                                ))}
                                <td className="border px-2 py-2 text-center">{grandTotal}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            ) : (
                <div className="text-center text-gray-400 py-12 text-sm">
                    Tidak ada data untuk ditampilkan.
                </div>
            )}
        </div>
    );
}

export default AsnKemantrenPendidikanPanel;