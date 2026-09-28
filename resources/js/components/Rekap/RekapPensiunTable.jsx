import { useEffect, useState } from "react";
import { LoaderCircle, AlertTriangle, Download } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { getCurrentPeriode } from "../../lib/periode";
import PeriodeLabel from "./PeriodeLabel";

function filenameFromResponse(res, fallback) {
    const disposition = res.headers.get("Content-Disposition") || "";
    const match = disposition.match(/filename="?([^"]+)"?/i);
    return match ? match[1] : fallback;
}

const JSON_PATH = "/rekap/pensiun";
const EXPORT_PATH = "/rekap/pensiun/export";
const FILENAME_PREFIX = "rekap-pensiun";

// Bentuk respons /rekap/pensiun (lihat RekapService::rekapPensiun):
// { tahun_awal, tahun_list: [2026..2035], rows: [{ instansi, per_tahun: {2026: n, ...}, sub_total }],
//   total: { per_tahun: {...}, sub_total } }
// Layout tabel sama dengan Excel referensi & App\Exports\RekapPensiunExport:
// No | Unit | Tahun (per tahun) | Sub Total, ditutup baris TOTAL.
function RekapPensiunTable() {
    // Default awal bulan berjalan; PeriodeLabel akan menimpanya begitu
    // periode aktif (dari import terakhir yang berhasil) selesai dimuat.
    const [periode, setPeriode] = useState(getCurrentPeriode());
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [exporting, setExporting] = useState(false);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError("");

            try {
                const res = await apiFetch(
                    `${JSON_PATH}?periode=${encodeURIComponent(periode)}`
                );

                if (!res.ok) {
                    throw new Error("Gagal memuat data rekapitulasi.");
                }

                const json = await res.json();
                if (!cancelled) {
                    setData(json);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err.message || "Gagal memuat data rekapitulasi.");
                    setData(null);
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        }

        load();

        return () => {
            cancelled = true;
        };
    }, [periode]);

    async function handleExport() {
        setExporting(true);
        setError("");

        try {
            const res = await apiFetch(
                `${EXPORT_PATH}?periode=${encodeURIComponent(periode)}`
            );

            if (!res.ok) {
                throw new Error("Gagal mengekspor rekapitulasi.");
            }

            const blob = await res.blob();
            const filename = filenameFromResponse(
                res,
                `${FILENAME_PREFIX}-${periode}.xlsx`
            );

            const url = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            setError(err.message || "Gagal mengekspor rekapitulasi.");
        } finally {
            setExporting(false);
        }
    }

    const tahunList = data?.tahun_list ?? [];
    const rows = data?.rows ?? [];
    const total = data?.total;

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-end justify-between gap-4 mb-5">
                <div>
                    <h3 className="text-lg font-bold">
                        Rekapitulasi Pensiun
                    </h3>
                    <p className="text-sm text-gray-500">
                        Jumlah PNS yang memasuki masa pensiun diperinci
                        menurut unit dan tahun pensiun.
                    </p>
                </div>

                <div className="flex items-end gap-3">
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">
                            Periode
                        </label>
                        <PeriodeLabel onLoaded={setPeriode} />
                    </div>

                    <button
                        onClick={handleExport}
                        disabled={exporting || loading}
                        className="flex items-center gap-2 bg-[#006A4E] text-white px-4 py-2 rounded text-sm font-semibold disabled:opacity-60 disabled:cursor-not-allowed hover:bg-[#005a41]"
                    >
                        {exporting ? (
                            <LoaderCircle className="animate-spin" size={16} />
                        ) : (
                            <Download size={16} />
                        )}
                        Export
                    </button>
                </div>
            </div>

            {error && (
                <div className="flex items-center gap-3 text-red-700 bg-red-50 p-3 rounded-lg text-sm mb-4">
                    <AlertTriangle size={16} />
                    {error}
                </div>
            )}

            {loading ? (
                <div className="flex items-center justify-center gap-2 text-gray-500 py-12 text-sm">
                    <LoaderCircle className="animate-spin" size={18} />
                    Memuat data...
                </div>
            ) : !data || rows.length === 0 ? (
                <div className="text-center text-gray-400 py-12 text-sm">
                    Tidak ada data untuk periode ini.
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm border border-gray-200">
                        <thead>
                            <tr className="bg-gray-50">
                                <th rowSpan={2} className="border px-3 py-2 text-center align-middle sticky left-0 bg-gray-50">No</th>
                                <th rowSpan={2} className="border px-3 py-2 text-center align-middle sticky left-10 bg-gray-50 w-72 max-w-[18rem]">Unit</th>
                                <th colSpan={tahunList.length} className="border px-2 py-2 text-center">Tahun</th>
                                <th rowSpan={2} className="border px-3 py-2 text-center align-middle whitespace-nowrap">Sub Total</th>
                            </tr>
                            <tr className="bg-gray-50">
                                {tahunList.map((tahun) => (
                                    <th key={`th-${tahun}`} className="border px-2 py-1.5 text-center whitespace-nowrap">
                                        {tahun}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, i) => (
                                <tr key={row.instansi} className="hover:bg-gray-50">
                                    <td className="border px-3 py-2 text-center sticky left-0 bg-white">{i + 1}</td>
                                    <td
                                        className="border px-3 py-2 sticky left-10 bg-white w-72 max-w-[18rem] whitespace-normal break-words align-top"
                                        title={row.instansi}
                                    >
                                        {row.instansi}
                                    </td>
                                    {tahunList.map((tahun) => (
                                        <td key={`${row.instansi}-${tahun}`} className="border px-2 py-2 text-center">
                                            {row.per_tahun?.[tahun] || 0}
                                        </td>
                                    ))}
                                    <td className="border px-3 py-2 text-center font-bold">{row.sub_total}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-gray-100 font-bold">
                                <td colSpan={2} className="border px-3 py-2 text-center sticky left-0 bg-gray-100">TOTAL</td>
                                {tahunList.map((tahun) => (
                                    <td key={`total-${tahun}`} className="border px-2 py-2 text-center">
                                        {total?.per_tahun?.[tahun] || 0}
                                    </td>
                                ))}
                                <td className="border px-3 py-2 text-center">{total?.sub_total || 0}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}
        </div>
    );
}

export default RekapPensiunTable;