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

// Sesuai eselonList di App\Exports\RekapJabatanExport &
// RekapService::rekapJabatan.
const ESELON_LIST = ["II A", "II B", "III A", "III B", "IV A", "IV B"];

const JSON_PATH = "/rekap/jabatan";
const EXPORT_PATH = "/rekap/jabatan/export";
const FILENAME_PREFIX = "rekap-jabatan";

function RekapJabatanTable() {
    // Default awal bulan berjalan; PeriodeLabel akan menimpanya begitu
    // periode aktif (dari import terakhir yang berhasil) selesai dimuat.
    // Periode di sini tidak bisa dipilih bebas, lihat PeriodeLabel.jsx.
    const [periode, setPeriode] = useState(getCurrentPeriode());
    const [rows, setRows] = useState([]);
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

                const data = await res.json();
                if (!cancelled) {
                    setRows(Array.isArray(data) ? data : []);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err.message || "Gagal memuat data rekapitulasi.");
                    setRows([]);
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

    // Total per kolom (baris terakhir tabel).
    const totalEselon = Object.fromEntries(ESELON_LIST.map((k) => [k, 0]));
    let totalJmlEselon = 0;
    let totalFungsionalUmum = 0;
    let totalFungsionalTertentu = 0;
    let totalJmlTotal = 0;

    rows.forEach((row) => {
        ESELON_LIST.forEach((k) => {
            totalEselon[k] += row.eselon?.[k] || 0;
        });
        totalJmlEselon += row.jml_eselon || 0;
        totalFungsionalUmum += row.fungsional_umum || 0;
        totalFungsionalTertentu += row.fungsional_tertentu || 0;
        totalJmlTotal += row.jml_total || 0;
    });

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-end justify-between gap-4 mb-5">
                <div>
                    <h3 className="text-lg font-bold">
                        Rekapitulasi Berdasarkan Jabatan
                    </h3>
                    <p className="text-sm text-gray-500">
                        Jumlah pegawai per eselon, fungsional umum
                        (jab. pelaksana), dan fungsional tertentu, per instansi.
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
            ) : rows.length === 0 ? (
                <div className="text-center text-gray-400 py-12 text-sm">
                    Tidak ada data untuk periode ini.
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm border border-gray-200">
                        <thead>
                            <tr className="bg-gray-50">
                                <th rowSpan={2} className="border px-3 py-2 text-left align-bottom sticky left-0 bg-gray-50">No</th>
                                <th rowSpan={2} className="border px-3 py-2 text-left align-bottom sticky left-8 bg-gray-50 w-48 max-w-[12rem]">Instansi</th>
                                <th colSpan={ESELON_LIST.length + 1} className="border px-3 py-2 text-center">Eselon</th>
                                <th rowSpan={2} className="border px-2 py-2 text-center align-bottom whitespace-nowrap">
                                    Fungsional Umum /<br />Jab. Pelaksana
                                </th>
                                <th rowSpan={2} className="border px-2 py-2 text-center align-bottom whitespace-nowrap">
                                    Fungsional Tertentu /<br />Jab. Fungsional
                                </th>
                                <th rowSpan={2} className="border px-2 py-2 text-center align-bottom">Jml Total</th>
                            </tr>
                            <tr className="bg-gray-50">
                                {ESELON_LIST.map((k) => (
                                    <th key={k} className="border px-2 py-1.5 text-center font-normal whitespace-nowrap">
                                        {k}
                                    </th>
                                ))}
                                <th className="border px-2 py-1.5 text-center font-normal whitespace-nowrap">Jml</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, i) => (
                                <tr key={row.instansi} className="hover:bg-gray-50">
                                    <td className="border px-3 py-2 sticky left-0 bg-white">{i + 1}</td>
                                    <td
                                        className="border px-3 py-2 sticky left-8 bg-white w-48 max-w-[12rem] whitespace-normal break-words align-top"
                                        title={row.instansi}
                                    >
                                        {row.instansi}
                                    </td>
                                    {ESELON_LIST.map((k) => (
                                        <td key={k} className="border px-2 py-2 text-center">
                                            {row.eselon?.[k] || 0}
                                        </td>
                                    ))}
                                    <td className="border px-2 py-2 text-center font-medium">{row.jml_eselon}</td>
                                    <td className="border px-2 py-2 text-center">{row.fungsional_umum}</td>
                                    <td className="border px-2 py-2 text-center">{row.fungsional_tertentu}</td>
                                    <td className="border px-2 py-2 text-center font-bold">{row.jml_total}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-gray-100 font-bold">
                                <td colSpan={2} className="border px-3 py-2 sticky left-0 bg-gray-100">Total</td>
                                {ESELON_LIST.map((k) => (
                                    <td key={`total-${k}`} className="border px-2 py-2 text-center">
                                        {totalEselon[k]}
                                    </td>
                                ))}
                                <td className="border px-2 py-2 text-center">{totalJmlEselon}</td>
                                <td className="border px-2 py-2 text-center">{totalFungsionalUmum}</td>
                                <td className="border px-2 py-2 text-center">{totalFungsionalTertentu}</td>
                                <td className="border px-2 py-2 text-center">{totalJmlTotal}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}
        </div>
    );
}

export default RekapJabatanTable;