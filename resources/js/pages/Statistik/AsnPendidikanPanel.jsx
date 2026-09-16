import { useEffect, useState } from "react";
import { Download, LoaderCircle } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { ErrorBox } from "./components/StatUi";
import { PENDIDIKAN_LIST } from "./pendidikanList";

// Ambil nama file dari header Content-Disposition kalau ada, dengan
// fallback ke nama default — pola sama seperti filenameFromResponse() di
// PensiunanPanel.jsx / PppkGolonganPanel.jsx.
function filenameFromResponse(res, fallback) {
    const disposition = res.headers.get("Content-Disposition") || "";
    const match = disposition.match(/filename="?([^"]+)"?/i);
    return match ? match[1] : fallback;
}

// ASN Berdasarkan Tingkat Pendidikan dan Jenis Kelamin (PNS + PPPK
// digabung, scope satu kota — lihat statistikAsnPendidikan() di
// StatistikService). Data per-jenjang pendidikan dibungkus di dalam key
// "pendidikan" (data.pendidikan.sd, data.pendidikan.smp, dst).
function AsnPendidikanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [exporting, setExporting] = useState(false);

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

    async function handleExport() {
        setExporting(true);
        setError(null);

        try {
            const res = await apiFetch("/statistik/asn-pendidikan/export");

            if (!res.ok) {
                throw new Error(
                    "Gagal mengekspor data ASN berdasarkan tingkat pendidikan."
                );
            }

            const blob = await res.blob();
            const filename = filenameFromResponse(res, "asn-pendidikan.xlsx");

            const url = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            setError(
                err?.message ||
                    "Gagal mengekspor data ASN berdasarkan tingkat pendidikan."
            );
        } finally {
            setExporting(false);
        }
    }

    // Baris tabel: SD s.d. S3, tiap baris punya laki_laki, perempuan,
    // total (langsung dari respons API).
    const rows = data
        ? PENDIDIKAN_LIST.map((p) => ({
              label: p.label,
              laki_laki: data.pendidikan[p.key]?.laki_laki || 0,
              perempuan: data.pendidikan[p.key]?.perempuan || 0,
              total: data.pendidikan[p.key]?.total || 0,
          }))
        : [];

    const totalJumlah = data ? data.jumlah_asn || 0 : 0;
    const totalLakiLaki = rows.reduce((sum, r) => sum + r.laki_laki, 0);
    const totalPerempuan = rows.reduce((sum, r) => sum + r.perempuan, 0);

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-start justify-between gap-3 mb-5">
                <div>
                    <h3 className="text-lg font-bold text-[#172033]">
                        ASN Berdasarkan Tingkat Pendidikan dan Jenis Kelamin
                    </h3>
                    <p className="text-sm text-gray-500">
                        Data ASN (PNS + PPPK) per tingkat pendidikan, dipecah menurut
                        jenis kelamin.
                    </p>
                </div>

                <button
                    onClick={handleExport}
                    disabled={exporting || loading || !data}
                    className="flex items-center gap-2 bg-[#006A4E] text-white px-4 py-2 rounded text-sm font-semibold disabled:opacity-60 disabled:cursor-not-allowed hover:bg-[#005a41]"
                >
                    {exporting ? (
                        <LoaderCircle className="animate-spin" size={16} />
                    ) : (
                        <Download size={16} />
                    )}
                    Export Excel
                </button>
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
                                <th className="border px-3 py-2 text-center w-12">No</th>
                                <th className="border px-3 py-2 text-left">
                                    Tingkat Pendidikan
                                </th>
                                <th className="border px-2 py-2 text-center">L</th>
                                <th className="border px-2 py-2 text-center">P</th>
                                <th className="border px-2 py-2 text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, i) => (
                                <tr key={row.label} className="hover:bg-gray-50">
                                    <td className="border px-3 py-2 text-center">{i + 1}</td>
                                    <td className="border px-3 py-2">{row.label}</td>
                                    <td className="border px-2 py-2 text-center">{row.laki_laki}</td>
                                    <td className="border px-2 py-2 text-center">{row.perempuan}</td>
                                    <td className="border px-2 py-2 text-center font-medium">{row.total}</td>
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
            ) : (
                <div className="text-center text-gray-400 py-12 text-sm">
                    Tidak ada data untuk ditampilkan.
                </div>
            )}
        </div>
    );
}

export default AsnPendidikanPanel;