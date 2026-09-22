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

const EXPORT_PATH = "/rekap/sd-smp/export";
const FILENAME_PREFIX = "rekap-sd-smp";

// Sub-tabel SD/SMP: bentuknya identik, cuma beda endpoint JSON & label.
function SubTable({ title, rows, loading, colLabel }) {
    let totalPria = 0;
    let totalWanita = 0;
    let totalJumlah = 0;

    rows.forEach((row) => {
        totalPria += row.pria || 0;
        totalWanita += row.wanita || 0;
        totalJumlah += row.jumlah || 0;
    });

    return (
        <div>
            <h4 className="text-sm font-bold text-gray-700 mb-2">{title}</h4>

            {loading ? (
                <div className="flex items-center justify-center gap-2 text-gray-500 py-8 text-sm">
                    <LoaderCircle className="animate-spin" size={16} />
                    Memuat data...
                </div>
            ) : rows.length === 0 ? (
                <div className="text-center text-gray-400 py-8 text-sm">
                    Tidak ada data untuk periode ini.
                </div>
            ) : (
                <div className="overflow-x-auto max-h-[22rem] overflow-y-auto">
                    <table className="min-w-full text-sm border border-gray-200">
                        <thead className="sticky top-0 bg-gray-50">
                            <tr>
                                <th className="border px-3 py-2 text-left">No</th>
                                <th className="border px-3 py-2 text-left">{colLabel}</th>
                                <th className="border px-2 py-2 text-center">Pria</th>
                                <th className="border px-2 py-2 text-center">Wanita</th>
                                <th className="border px-2 py-2 text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, i) => (
                                <tr key={row.sekolah} className="hover:bg-gray-50">
                                    <td className="border px-3 py-2">{i + 1}</td>
                                    <td className="border px-3 py-2">{row.sekolah}</td>
                                    <td className="border px-2 py-2 text-center">{row.pria || 0}</td>
                                    <td className="border px-2 py-2 text-center">{row.wanita || 0}</td>
                                    <td className="border px-2 py-2 text-center font-bold">{row.jumlah || 0}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-gray-100 font-bold">
                                <td colSpan={2} className="border px-3 py-2">Total</td>
                                <td className="border px-2 py-2 text-center">{totalPria}</td>
                                <td className="border px-2 py-2 text-center">{totalWanita}</td>
                                <td className="border px-2 py-2 text-center">{totalJumlah}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}
        </div>
    );
}

function RekapSdSmpTable() {
    const [periode, setPeriode] = useState(getCurrentPeriode());
    const [sdRows, setSdRows] = useState([]);
    const [smpRows, setSmpRows] = useState([]);
    const [loading, setLoading] = useState(false);
    const [exporting, setExporting] = useState(false);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError("");

            try {
                const [resSd, resSmp] = await Promise.all([
                    apiFetch(`/rekap/sd?periode=${encodeURIComponent(periode)}`),
                    apiFetch(`/rekap/smp?periode=${encodeURIComponent(periode)}`),
                ]);

                if (!resSd.ok || !resSmp.ok) {
                    throw new Error("Gagal memuat data rekapitulasi.");
                }

                const [dataSd, dataSmp] = await Promise.all([
                    resSd.json(),
                    resSmp.json(),
                ]);

                if (!cancelled) {
                    setSdRows(Array.isArray(dataSd) ? dataSd : []);
                    setSmpRows(Array.isArray(dataSmp) ? dataSmp : []);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err.message || "Gagal memuat data rekapitulasi.");
                    setSdRows([]);
                    setSmpRows([]);
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

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-end justify-between gap-4 mb-5">
                <div>
                    <h3 className="text-lg font-bold">
                        Rekap Data Fungsional SD & SMP
                    </h3>
                    <p className="text-sm text-gray-500">
                        Jumlah guru/tenaga fungsional aktif per sekolah
                        (SD dan SMP Negeri), dipecah menurut jenis
                        kelamin. Export menghasilkan 1 file Excel
                        dengan sheet "SD" dan "SMP", sesuai format
                        laporan.
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

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <SubTable title="SD" rows={sdRows} loading={loading} colLabel="SD" />
                <SubTable title="SMP" rows={smpRows} loading={loading} colLabel="SMP" />
            </div>
        </div>
    );
}

export default RekapSdSmpTable;