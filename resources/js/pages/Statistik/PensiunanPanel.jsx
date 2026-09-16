import { useEffect, useState } from "react";
import { Download, LoaderCircle } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { ErrorBox } from "./components/StatUi";

// Ambil nama file dari header Content-Disposition kalau ada, dengan
// fallback ke nama default — pola sama seperti filenameFromResponse() di
// StrukturalPanel.jsx / FungsionalPanel.jsx.
function filenameFromResponse(res, fallback) {
    const disposition = res.headers.get("Content-Disposition") || "";
    const match = disposition.match(/filename="?([^"]+)"?/i);
    return match ? match[1] : fallback;
}

// Golongan yang ditampilkan sebagai baris tabel, urutan I -> IV mengikuti
// struktur respons statistikPensiunanPNS() di StatistikService.
const GOLONGAN_LIST = [
    { key: "golongan_I", label: "Golongan I" },
    { key: "golongan_II", label: "Golongan II" },
    { key: "golongan_III", label: "Golongan III" },
    { key: "golongan_IV", label: "Golongan IV" },
];

function PensiunanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [exporting, setExporting] = useState(false);

    async function handleExport() {
        setExporting(true);
        setError(null);

        try {
            const res = await apiFetch("/statistik/pensiunan-pns/export");

            if (!res.ok) {
                throw new Error("Gagal mengekspor data Pensiunan PNS.");
            }

            const blob = await res.blob();
            const filename = filenameFromResponse(res, "pensiunan-pns.xlsx");

            const url = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            setError(err?.message || "Gagal mengekspor data Pensiunan PNS.");
        } finally {
            setExporting(false);
        }
    }

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pensiunan-pns");

                if (!res.ok) {
                    throw new Error("Gagal memuat data pensiunan PNS.");
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(err?.message || "Gagal memuat data pensiunan PNS.");
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

    // Baris tabel: Golongan I-IV, tiap baris punya total, laki_laki,
    // perempuan (langsung dari respons API).
    const rows = data
        ? GOLONGAN_LIST.map(({ key, label }) => ({
              label,
              laki_laki: data[key]?.laki_laki || 0,
              perempuan: data[key]?.perempuan || 0,
              total: data[key]?.total || 0,
          }))
        : [];

    const totalJumlah = data ? data.jumlah_pensiunan_pns || 0 : 0;
    const totalGolongan = rows.reduce((sum, r) => sum + r.total, 0);

    // Pensiunan dengan golongan kosong/tidak dikenali tetap dihitung di
    // jumlah_pensiunan_pns tapi tidak masuk bucket Golongan I-IV (lihat
    // statistikPensiunanPNS()). Selisihnya ditampilkan sebagai baris
    // terpisah supaya angka di tabel konsisten dengan baris Total.
    const tidakDikenali = Math.max(totalJumlah - totalGolongan, 0);

    const totalLakiLaki = rows.reduce((sum, r) => sum + r.laki_laki, 0);
    const totalPerempuan = rows.reduce((sum, r) => sum + r.perempuan, 0);

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-start justify-between gap-3 mb-5">
                <div>
                    <h3 className="text-lg font-bold text-[#172033]">
                        Pensiunan PNS
                    </h3>
                    <p className="text-sm text-gray-500">
                        Data pensiunan PNS
                        {data?.tahun ? ` tahun ${data.tahun}` : ""} per golongan,
                        dipecah menurut jenis kelamin.
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
                                <th className="border px-3 py-2 text-left">No</th>
                                <th className="border px-3 py-2 text-left">Golongan</th>
                                <th className="border px-2 py-2 text-center">L</th>
                                <th className="border px-2 py-2 text-center">P</th>
                                <th className="border px-2 py-2 text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, i) => (
                                <tr key={row.label} className="hover:bg-gray-50">
                                    <td className="border px-3 py-2">{i + 1}</td>
                                    <td className="border px-3 py-2">{row.label}</td>
                                    <td className="border px-2 py-2 text-center">{row.laki_laki}</td>
                                    <td className="border px-2 py-2 text-center">{row.perempuan}</td>
                                    <td className="border px-2 py-2 text-center font-medium">{row.total}</td>
                                </tr>
                            ))}

                            {tidakDikenali > 0 && (
                                <tr className="hover:bg-gray-50 text-gray-600">
                                    <td className="border px-3 py-2">&ndash;</td>
                                    <td className="border px-3 py-2 italic">Golongan Tidak Dikenali</td>
                                    <td className="border px-2 py-2 text-center">&ndash;</td>
                                    <td className="border px-2 py-2 text-center">&ndash;</td>
                                    <td className="border px-2 py-2 text-center">{tidakDikenali}</td>
                                </tr>
                            )}
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

export default PensiunanPanel;