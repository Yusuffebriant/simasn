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

// Urutan kolom golongan ruang Pria/Wanita HARUS sama persis dengan
// App\Services\RekapService::STRUK_GOL_LIST dan
// App\Exports\RekapStrukturGolonganExport::$golonganList (excel),
// yaitu hanya golongan struktural III/a - IV/d.
const GOLONGAN_LIST = [
    "III/a", "III/b", "III/c", "III/d",
    "IV/a", "IV/b", "IV/c", "IV/d",
];

const JSON_PATH = "/rekap/struktur-golongan";
const EXPORT_PATH = "/rekap/struktur-golongan/export";
const FILENAME_PREFIX = "rekap-struktur-golongan";

function RekapStrukturGolonganTable() {
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

    // Total per kolom (baris terakhir tabel), mengikuti struktur yang
    // sama dengan baris TOTAL di RekapStrukturGolonganExport (sum per kolom).
    const totalPria = Object.fromEntries(GOLONGAN_LIST.map((k) => [k, 0]));
    const totalWanita = Object.fromEntries(GOLONGAN_LIST.map((k) => [k, 0]));
    let totalJmlPria = 0;
    let totalJmlWanita = 0;
    let totalGrand = 0;

    rows.forEach((row) => {
        GOLONGAN_LIST.forEach((k) => {
            totalPria[k] += row.pria?.[k] || 0;
            totalWanita[k] += row.wanita?.[k] || 0;
        });
        totalJmlPria += row.jml_pria || 0;
        totalJmlWanita += row.jml_wanita || 0;
        totalGrand += row.jml_total || 0;
    });

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-end justify-between gap-4 mb-5">
                <div>
                    <h3 className="text-lg font-bold">
                        Rekapitulasi Struktural Berdasarkan Golongan
                    </h3>
                    <p className="text-sm text-gray-500">
                        Jumlah pejabat struktural diperinci menurut instansi,
                        golongan ruang, dan jenis kelamin.
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
                                <th colSpan={GOLONGAN_LIST.length} className="border px-2 py-2 text-center">PRIA</th>
                                <th rowSpan={2} className="border px-2 py-2 text-center align-bottom">JML</th>
                                <th colSpan={GOLONGAN_LIST.length} className="border px-2 py-2 text-center">WANITA</th>
                                <th rowSpan={2} className="border px-2 py-2 text-center align-bottom">JML</th>
                                <th rowSpan={2} className="border px-2 py-2 text-center align-bottom">JML TOTAL</th>
                            </tr>
                            <tr className="bg-gray-50">
                                {GOLONGAN_LIST.map((k, idx) => (
                                    <th key={`pria-h-${k}-${idx}`} className="border px-1.5 py-1.5 text-center font-normal whitespace-nowrap">
                                        {k}
                                    </th>
                                ))}
                                {GOLONGAN_LIST.map((k, idx) => (
                                    <th key={`wanita-h-${k}-${idx}`} className="border px-1.5 py-1.5 text-center font-normal whitespace-nowrap">
                                        {k}
                                    </th>
                                ))}
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
                                    {GOLONGAN_LIST.map((k, idx) => (
                                        <td key={`pria-${row.instansi}-${k}-${idx}`} className="border px-1.5 py-2 text-center">
                                            {row.pria?.[k] || 0}
                                        </td>
                                    ))}
                                    <td className="border px-1.5 py-2 text-center font-medium">{row.jml_pria}</td>
                                    {GOLONGAN_LIST.map((k, idx) => (
                                        <td key={`wanita-${row.instansi}-${k}-${idx}`} className="border px-1.5 py-2 text-center">
                                            {row.wanita?.[k] || 0}
                                        </td>
                                    ))}
                                    <td className="border px-1.5 py-2 text-center font-medium">{row.jml_wanita}</td>
                                    <td className="border px-1.5 py-2 text-center font-bold">{row.jml_total}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-gray-100 font-bold">
                                <td colSpan={2} className="border px-3 py-2 sticky left-0 bg-gray-100">Total</td>
                                {GOLONGAN_LIST.map((k, idx) => (
                                    <td key={`total-pria-${k}-${idx}`} className="border px-1.5 py-2 text-center">{totalPria[k]}</td>
                                ))}
                                <td className="border px-1.5 py-2 text-center">{totalJmlPria}</td>
                                {GOLONGAN_LIST.map((k, idx) => (
                                    <td key={`total-wanita-${k}-${idx}`} className="border px-1.5 py-2 text-center">{totalWanita[k]}</td>
                                ))}
                                <td className="border px-1.5 py-2 text-center">{totalJmlWanita}</td>
                                <td className="border px-1.5 py-2 text-center">{totalGrand}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}
        </div>
    );
}

export default RekapStrukturGolonganTable;