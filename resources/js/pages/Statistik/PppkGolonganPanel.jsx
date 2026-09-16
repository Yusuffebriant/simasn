import { useEffect, useState } from "react";
import { Download, LoaderCircle } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { ErrorBox } from "./components/StatUi";

// Ambil nama file dari header Content-Disposition kalau ada, dengan
// fallback ke nama default — pola sama seperti filenameFromResponse() di
// PensiunanPanel.jsx / StrukturalPanel.jsx.
function filenameFromResponse(res, fallback) {
    const disposition = res.headers.get("Content-Disposition") || "";
    const match = disposition.match(/filename="?([^"]+)"?/i);
    return match ? match[1] : fallback;
}

// Daftar golongan PPPK yang benar-benar dipakai — beda dari golongan
// PNS (I-IV). Golongan genap (II, IV, VI, VIII) & XII+ sengaja tidak
// dimasukkan karena memang tidak pernah ada datanya (lihat docblock
// statistikPppkGolongan() di StatistikService). Master data golongan_ruang
// di database tidak diubah, ini murni daftar untuk tampilan.
const GOLONGAN_LIST = ["I", "III", "V", "VII", "IX", "X", "XI"];

function PppkGolonganPanel() {
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
                const res = await apiFetch("/statistik/pppk-golongan");

                if (!res.ok) {
                    throw new Error("Gagal memuat data PPPK berdasarkan golongan.");
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message || "Gagal memuat data PPPK berdasarkan golongan."
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
            const res = await apiFetch("/statistik/pppk-golongan/export");

            if (!res.ok) {
                throw new Error("Gagal mengekspor data PPPK berdasarkan golongan.");
            }

            const blob = await res.blob();
            const filename = filenameFromResponse(res, "pppk-golongan.xlsx");

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
                err?.message || "Gagal mengekspor data PPPK berdasarkan golongan."
            );
        } finally {
            setExporting(false);
        }
    }

    // Baris tabel: Golongan I, III, V, VII, IX, X, XI, tiap baris punya
    // laki_laki, perempuan, total (langsung dari respons API).
    const rows = data
        ? GOLONGAN_LIST.map((g) => ({
              label: `Golongan ${g}`,
              laki_laki: data.golongan[g]?.laki_laki || 0,
              perempuan: data.golongan[g]?.perempuan || 0,
              total: data.golongan[g]?.total || 0,
          }))
        : [];

    const totalJumlah = data ? data.jumlah_pppk || 0 : 0;
    const totalLakiLaki = rows.reduce((sum, r) => sum + r.laki_laki, 0);
    const totalPerempuan = rows.reduce((sum, r) => sum + r.perempuan, 0);

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-start justify-between gap-3 mb-5">
                <div>
                    <h3 className="text-lg font-bold text-[#172033]">
                        PPPK Berdasarkan Golongan
                    </h3>
                    <p className="text-sm text-gray-500">
                        Data PPPK aktif per golongan, dipecah menurut jenis kelamin.
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
                                <th className="border px-3 py-2 text-left">Golongan</th>
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

export default PppkGolonganPanel;