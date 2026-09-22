import { useEffect, useState } from "react";
import { LoaderCircle, AlertTriangle, Download } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { getCurrentPeriode } from "../../lib/periode";
import PeriodeLabel from "./PeriodeLabel";

function filenameFromResponse(res, fallback) {
    const disposition = res.headers.get("Content-Disposition") || "";
    const match = disposition.match(/filename=\"?([^\"]+)\"?/i);
    return match ? match[1] : fallback;
}

const JSON_PATH = "/rekap/nakes";
const EXPORT_PATH = "/rekap/nakes/export";
const FILENAME_PREFIX = "rekap-nakes";

function RekapNakesTable() {
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
    let totalPria = 0;
    let totalWanita = 0;
    let totalJumlah = 0;

    rows.forEach((row) => {
        totalPria += row.pria || 0;
        totalWanita += row.wanita || 0;
        totalJumlah += row.jumlah || 0;
    });

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-end justify-between gap-4 mb-5">
                <div>
                    <h3 className="text-lg font-bold">
                        Data Pejabat Fungsional Nakes
                    </h3>
                    <p className="text-sm text-gray-500">
                        Jumlah tenaga kesehatan aktif per fasilitas
                        kesehatan (Puskesmas, RS Pratama, dan RSUD),
                        dipecah menurut jenis kelamin.
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
                                <th className="border px-3 py-2 text-left align-bottom">No</th>
                                <th className="border px-3 py-2 text-left align-bottom w-64 max-w-[16rem]">Fasilitas Kesehatan</th>
                                <th className="border px-2 py-2 text-center align-bottom">Pria</th>
                                <th className="border px-2 py-2 text-center align-bottom">Wanita</th>
                                <th className="border px-2 py-2 text-center align-bottom">Jumlah</th>
                                <th className="border px-3 py-2 text-left align-bottom">Alamat</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, i) => (
                                <tr key={row.fasilitas} className="hover:bg-gray-50">
                                    <td className="border px-3 py-2">{i + 1}</td>
                                    <td
                                        className="border px-3 py-2 w-64 max-w-[16rem] whitespace-normal break-words align-top"
                                        title={row.fasilitas}
                                    >
                                        {row.fasilitas}
                                    </td>
                                    <td className="border px-2 py-2 text-center">{row.pria || 0}</td>
                                    <td className="border px-2 py-2 text-center">{row.wanita || 0}</td>
                                    <td className="border px-2 py-2 text-center font-bold">{row.jumlah || 0}</td>
                                    <td className="border px-3 py-2 whitespace-normal break-words align-top">
                                        {row.alamat || "-"}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-gray-100 font-bold">
                                <td colSpan={2} className="border px-3 py-2">Total</td>
                                <td className="border px-2 py-2 text-center">{totalPria}</td>
                                <td className="border px-2 py-2 text-center">{totalWanita}</td>
                                <td className="border px-2 py-2 text-center">{totalJumlah}</td>
                                <td className="border px-3 py-2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}
        </div>
    );
}

export default RekapNakesTable;