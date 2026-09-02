import { Fragment, useEffect, useState } from "react";
import { LoaderCircle, AlertTriangle, Download } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { getCurrentPeriode } from "../../lib/periode";
import PeriodeLabel from "./PeriodeLabel";

function filenameFromResponse(res, fallback) {
    const disposition = res.headers.get("Content-Disposition") || "";
    const match = disposition.match(/filename="?([^"]+)"?/i);
    return match ? match[1] : fallback;
}

/**
 * Tabel rekap generik untuk pola "kategori x Laki-laki/Perempuan per baris",
 * dipakai oleh Rekap Agama, Rekap Pendidikan, & Rekap Eselon (struktur data
 * dari RekapService sama: { [rowField]: label, pria: {kategori: n},
 * wanita: {kategori: n}, jml_pria, jml_wanita, jml_total }).
 *
 * Props:
 * - title: judul tabel
 * - jsonPath: endpoint GET untuk data tabel (mis. "/rekap/agama")
 * - exportPath: endpoint GET untuk export excel (mis. "/rekap/agama/export")
 * - categories: daftar nama kolom kategori, urutan sesuai backend
 * - filenamePrefix: prefix nama file fallback saat export
 * - rowField: nama field pada tiap baris data yang jadi label baris
 *   (default "instansi"; pakai "eselon" untuk Rekap Eselon)
 * - rowHeaderLabel: teks header kolom label baris (default "Instansi")
 */
function RekapKategoriTable({
    title,
    jsonPath,
    exportPath,
    categories,
    filenamePrefix,
    rowField = "instansi",
    rowHeaderLabel = "Instansi",
}) {
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
                    `${jsonPath}?periode=${encodeURIComponent(periode)}`
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
    }, [jsonPath, periode]);

    async function handleExport() {
        setExporting(true);
        setError("");

        try {
            const res = await apiFetch(
                `${exportPath}?periode=${encodeURIComponent(periode)}`
            );

            if (!res.ok) {
                throw new Error("Gagal mengekspor rekapitulasi.");
            }

            const blob = await res.blob();
            const filename = filenameFromResponse(
                res,
                `${filenamePrefix}-${periode}.xlsx`
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

    // Total per kolom, dijumlah dari semua instansi (baris terakhir tabel).
    const totalPria = Object.fromEntries(categories.map((k) => [k, 0]));
    const totalWanita = Object.fromEntries(categories.map((k) => [k, 0]));
    let totalJmlPria = 0;
    let totalJmlWanita = 0;
    let totalJmlTotal = 0;

    rows.forEach((row) => {
        categories.forEach((k) => {
            totalPria[k] += row.pria?.[k] || 0;
            totalWanita[k] += row.wanita?.[k] || 0;
        });
        totalJmlPria += row.jml_pria || 0;
        totalJmlWanita += row.jml_wanita || 0;
        totalJmlTotal += row.jml_total || 0;
    });

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-end justify-between gap-4 mb-5">
                <div>
                    <h3 className="text-lg font-bold">{title}</h3>
                    <p className="text-sm text-gray-500">
                        Data pegawai aktif per instansi, dipecah menurut jenis kelamin.
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
                                <th rowSpan={2} className="border px-3 py-2 text-left align-bottom">No</th>
                                <th rowSpan={2} className="border px-3 py-2 text-left align-bottom w-48 max-w-[12rem]">{rowHeaderLabel}</th>
                                {categories.map((k) => (
                                    <th key={k} colSpan={2} className="border px-3 py-2 text-center">
                                        {k}
                                    </th>
                                ))}
                                <th colSpan={3} className="border px-3 py-2 text-center">Jumlah</th>
                            </tr>
                            <tr className="bg-gray-50">
                                {categories.map((k) => (
                                    <Fragment key={k}>
                                        <th className="border px-2 py-1.5 text-center font-normal">L</th>
                                        <th className="border px-2 py-1.5 text-center font-normal">P</th>
                                    </Fragment>
                                ))}
                                <th className="border px-2 py-1.5 text-center font-normal">L</th>
                                <th className="border px-2 py-1.5 text-center font-normal">P</th>
                                <th className="border px-2 py-1.5 text-center font-normal">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, i) => (
                                <tr key={row[rowField]} className="hover:bg-gray-50">
                                    <td className="border px-3 py-2">{i + 1}</td>
                                    <td
                                        className="border px-3 py-2 w-48 max-w-[12rem] whitespace-normal break-words align-top"
                                        title={row[rowField]}
                                    >
                                        {row[rowField]}
                                    </td>
                                    {categories.map((k) => (
                                        <Fragment key={k}>
                                            <td className="border px-2 py-2 text-center">
                                                {row.pria?.[k] || 0}
                                            </td>
                                            <td className="border px-2 py-2 text-center">
                                                {row.wanita?.[k] || 0}
                                            </td>
                                        </Fragment>
                                    ))}
                                    <td className="border px-2 py-2 text-center font-medium">{row.jml_pria}</td>
                                    <td className="border px-2 py-2 text-center font-medium">{row.jml_wanita}</td>
                                    <td className="border px-2 py-2 text-center font-bold">{row.jml_total}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-gray-100 font-bold">
                                <td colSpan={2} className="border px-3 py-2">Total</td>
                                {categories.map((k) => (
                                    <Fragment key={k}>
                                        <td className="border px-2 py-2 text-center">
                                            {totalPria[k]}
                                        </td>
                                        <td className="border px-2 py-2 text-center">
                                            {totalWanita[k]}
                                        </td>
                                    </Fragment>
                                ))}
                                <td className="border px-2 py-2 text-center">{totalJmlPria}</td>
                                <td className="border px-2 py-2 text-center">{totalJmlWanita}</td>
                                <td className="border px-2 py-2 text-center">{totalJmlTotal}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}
        </div>
    );
}

export default RekapKategoriTable;