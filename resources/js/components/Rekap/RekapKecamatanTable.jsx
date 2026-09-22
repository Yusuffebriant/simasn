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

const JSON_PATH = "/rekap/kecamatan";
const EXPORT_PATH = "/rekap/kecamatan/export";
const FILENAME_PREFIX = "rekap-kecamatan";

// Sel abu-abu ringan untuk memisahkan kolom Kecamatan dan Kelurahan.
const kelurahanColClass = "bg-slate-50";

function RekapKecamatanTable() {
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

    // Total keseluruhan (Kecamatan + Kelurahan digabung), dihitung dari
    // baris pertama tiap kelompok Kemantren (rollup) + tiap baris Kelurahan.
    let totalL = 0;
    let totalP = 0;
    let noKemantren = 0;

    rows.forEach((row) => {
        if (row.kemantren !== null) {
            noKemantren += 1;
            totalL += row.kecamatan_total_l || 0;
            totalP += row.kecamatan_total_p || 0;
        }
        totalL += row.kelurahan_total_l || 0;
        totalP += row.kelurahan_total_p || 0;
    });

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-end justify-between gap-4 mb-5">
                <div>
                    <h3 className="text-lg font-bold">
                        Jumlah ASN yang Bekerja di Kecamatan / Kelurahan
                    </h3>
                    <p className="text-sm text-gray-500">
                        PNS aktif per Kemantren dan Kelurahan, dipecah
                        menurut jenis kedudukan (Fungsional/Struktural/
                        Pelaksana) dan jenis kelamin.
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
                <div className="overflow-x-auto max-h-[36rem] overflow-y-auto">
                    <table className="min-w-full text-xs border border-gray-200 whitespace-nowrap">
                        <thead className="sticky top-0 bg-gray-50 z-10">
                            <tr>
                                <th rowSpan={3} className="border px-2 py-2 align-middle">No</th>
                                <th colSpan={10} className="border px-2 py-2 text-center">
                                    Kecamatan (Kemantren)
                                </th>
                                <th colSpan={8} className={`border px-2 py-2 text-center ${kelurahanColClass}`}>
                                    Kelurahan
                                </th>
                            </tr>
                            <tr>
                                <th rowSpan={2} className="border px-2 py-2 align-middle">Kemantren</th>
                                <th rowSpan={2} className="border px-2 py-2 align-middle">Alamat</th>
                                <th colSpan={2} className="border px-2 py-2 text-center">Fungsional</th>
                                <th colSpan={2} className="border px-2 py-2 text-center">Struktural</th>
                                <th colSpan={2} className="border px-2 py-2 text-center">Pelaksana</th>
                                <th colSpan={2} className="border px-2 py-2 text-center">Total</th>
                                <th rowSpan={2} className={`border px-2 py-2 align-middle ${kelurahanColClass}`}>Kelurahan</th>
                                <th rowSpan={2} className={`border px-2 py-2 align-middle ${kelurahanColClass}`}>Alamat</th>
                                <th colSpan={2} className={`border px-2 py-2 text-center ${kelurahanColClass}`}>Struktural</th>
                                <th colSpan={2} className={`border px-2 py-2 text-center ${kelurahanColClass}`}>Pelaksana</th>
                                <th colSpan={2} className={`border px-2 py-2 text-center ${kelurahanColClass}`}>Total</th>
                            </tr>
                            <tr>
                                <th className="border px-2 py-1">L</th>
                                <th className="border px-2 py-1">P</th>
                                <th className="border px-2 py-1">L</th>
                                <th className="border px-2 py-1">P</th>
                                <th className="border px-2 py-1">L</th>
                                <th className="border px-2 py-1">P</th>
                                <th className="border px-2 py-1">L</th>
                                <th className="border px-2 py-1">P</th>
                                <th className={`border px-2 py-1 ${kelurahanColClass}`}>L</th>
                                <th className={`border px-2 py-1 ${kelurahanColClass}`}>P</th>
                                <th className={`border px-2 py-1 ${kelurahanColClass}`}>L</th>
                                <th className={`border px-2 py-1 ${kelurahanColClass}`}>P</th>
                                <th className={`border px-2 py-1 ${kelurahanColClass}`}>L</th>
                                <th className={`border px-2 py-1 ${kelurahanColClass}`}>P</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(() => {
                                let kemantrenNo = 0;
                                return rows.map((row, i) => {
                                    const isFirst = row.kemantren !== null;
                                    if (isFirst) kemantrenNo += 1;
                                    const span = row.jumlah_baris_kemantren;

                                    return (
                                        <tr key={i} className="hover:bg-gray-50">
                                            {isFirst && (
                                                <td rowSpan={span} className="border px-2 py-1 text-center align-top">
                                                    {kemantrenNo}
                                                </td>
                                            )}
                                            {isFirst && (
                                                <td rowSpan={span} className="border px-2 py-1 align-top font-semibold">
                                                    {row.kemantren}
                                                </td>
                                            )}
                                            {isFirst && (
                                                <td rowSpan={span} className="border px-2 py-1 align-top whitespace-normal break-words max-w-[14rem]">
                                                    {row.kemantren_alamat || "-"}
                                                </td>
                                            )}
                                            {isFirst && (
                                                <td rowSpan={span} className="border px-2 py-1 text-center align-top">{row.fungsional_l || 0}</td>
                                            )}
                                            {isFirst && (
                                                <td rowSpan={span} className="border px-2 py-1 text-center align-top">{row.fungsional_p || 0}</td>
                                            )}
                                            {isFirst && (
                                                <td rowSpan={span} className="border px-2 py-1 text-center align-top">{row.struktural_l || 0}</td>
                                            )}
                                            {isFirst && (
                                                <td rowSpan={span} className="border px-2 py-1 text-center align-top">{row.struktural_p || 0}</td>
                                            )}
                                            {isFirst && (
                                                <td rowSpan={span} className="border px-2 py-1 text-center align-top">{row.pelaksana_l || 0}</td>
                                            )}
                                            {isFirst && (
                                                <td rowSpan={span} className="border px-2 py-1 text-center align-top">{row.pelaksana_p || 0}</td>
                                            )}
                                            {isFirst && (
                                                <td rowSpan={span} className="border px-2 py-1 text-center align-top font-bold">{row.kecamatan_total_l || 0}</td>
                                            )}
                                            {isFirst && (
                                                <td rowSpan={span} className="border px-2 py-1 text-center align-top font-bold">{row.kecamatan_total_p || 0}</td>
                                            )}

                                            <td className={`border px-2 py-1 ${kelurahanColClass}`}>{row.kelurahan}</td>
                                            <td className={`border px-2 py-1 whitespace-normal break-words max-w-[14rem] ${kelurahanColClass}`}>
                                                {row.kelurahan_alamat || "-"}
                                            </td>
                                            <td className={`border px-2 py-1 text-center ${kelurahanColClass}`}>{row.kel_struktural_l || 0}</td>
                                            <td className={`border px-2 py-1 text-center ${kelurahanColClass}`}>{row.kel_struktural_p || 0}</td>
                                            <td className={`border px-2 py-1 text-center ${kelurahanColClass}`}>{row.kel_pelaksana_l || 0}</td>
                                            <td className={`border px-2 py-1 text-center ${kelurahanColClass}`}>{row.kel_pelaksana_p || 0}</td>
                                            <td className={`border px-2 py-1 text-center font-bold ${kelurahanColClass}`}>{row.kelurahan_total_l || 0}</td>
                                            <td className={`border px-2 py-1 text-center font-bold ${kelurahanColClass}`}>{row.kelurahan_total_p || 0}</td>
                                        </tr>
                                    );
                                });
                            })()}
                        </tbody>
                        <tfoot>
                            <tr className="bg-gray-100 font-bold">
                                <td colSpan={9} className="border px-2 py-2 text-right">
                                    Total Keseluruhan ({noKemantren} Kemantren)
                                </td>
                                <td className="border px-2 py-2 text-center">{totalL}</td>
                                <td className="border px-2 py-2 text-center">{totalP}</td>
                                <td colSpan={8} className={`border px-2 py-2 ${kelurahanColClass}`}></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}
        </div>
    );
}

export default RekapKecamatanTable;