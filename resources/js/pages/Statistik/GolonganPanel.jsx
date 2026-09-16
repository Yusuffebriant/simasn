import { useEffect, useState } from "react";
import { Download, LoaderCircle } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { ErrorBox } from "./components/StatUi";

// Ambil nama file dari header Content-Disposition kalau ada, dengan
// fallback ke nama default — pola sama seperti filenameFromResponse() di
// PensiunanPanel.jsx / PnsKelurahanPanel.jsx.
function filenameFromResponse(res, fallback) {
    const disposition = res.headers.get("Content-Disposition") || "";
    const match = disposition.match(/filename="?([^"]+)"?/i);
    return match ? match[1] : fallback;
}

// Definisi rincian pangkat/ruang per golongan, dipakai untuk merender
// baris tabel rincian secara konsisten untuk golongan I-IV.
const RINCIAN_GOLONGAN = {
    I: [
        { kode: "I/a", label: "Golongan I/a (Juru Muda)" },
        { kode: "I/b", label: "Golongan I/b (Juru Muda Tingkat I)" },
        { kode: "I/c", label: "Golongan I/c (Juru)" },
        { kode: "I/d", label: "Golongan I/d (Juru Tingkat I)" },
    ],
    II: [
        { kode: "II/a", label: "Golongan II/a (Pengatur Muda)" },
        { kode: "II/b", label: "Golongan II/b (Pengatur Muda Tingkat I)" },
        { kode: "II/c", label: "Golongan II/c (Pengatur)" },
        { kode: "II/d", label: "Golongan II/d (Pengatur Tingkat I)" },
    ],
    III: [
        { kode: "III/a", label: "Golongan III/a (Penata Muda)" },
        { kode: "III/b", label: "Golongan III/b (Penata Muda Tingkat I)" },
        { kode: "III/c", label: "Golongan III/c (Penata)" },
        { kode: "III/d", label: "Golongan III/d (Penata Tingkat I)" },
    ],
    IV: [
        { kode: "IV/a", label: "Golongan IV/a (Pembina Muda)" },
        { kode: "IV/b", label: "Golongan IV/b (Pembina Muda Tingkat I)" },
        { kode: "IV/c", label: "Golongan IV/c (Pembina)" },
        { kode: "IV/d", label: "Golongan IV/d (Pembina Tingkat I)" },
        { kode: "IV/e", label: "Golongan IV/e (Pembina Utama)" },
    ],
};

const ROMAWI_LIST = ["I", "II", "III", "IV"];

// Baris "Golongan <romawi>" (subtotal, tebal, latar abu-abu) diikuti
// baris rincian per pangkat/ruang untuk golongan tsb — pola sama seperti
// baris "Jumlah Kemantren ..." di RekapGolonganTable.jsx / PnsKelurahanExport.
function GolonganRows({ romawi, golongan }) {
    const rincianList = RINCIAN_GOLONGAN[romawi];

    return (
        <>
            <tr className="bg-[#F0F2F5] font-semibold">
                <td className="border px-3 py-2" colSpan={2}>
                    Golongan {romawi}
                </td>
                <td className="border px-2 py-2 text-center">
                    {golongan.laki_laki.total}
                </td>
                <td className="border px-2 py-2 text-center">
                    {golongan.perempuan.total}
                </td>
                <td className="border px-2 py-2 text-center">
                    {golongan.total}
                </td>
            </tr>

            {rincianList.map((r) => {
                const laki_laki = golongan.laki_laki.rincian[r.kode] || 0;
                const perempuan = golongan.perempuan.rincian[r.kode] || 0;

                return (
                    <tr key={r.kode} className="hover:bg-gray-50">
                        <td className="border px-3 py-2 text-center w-16">{r.kode}</td>
                        <td className="border px-3 py-2">{r.label}</td>
                        <td className="border px-2 py-2 text-center">{laki_laki}</td>
                        <td className="border px-2 py-2 text-center">{perempuan}</td>
                        <td className="border px-2 py-2 text-center font-medium">
                            {laki_laki + perempuan}
                        </td>
                    </tr>
                );
            })}
        </>
    );
}

function GolonganPanel() {
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
                const res = await apiFetch("/statistik/pns-golongan");

                if (!res.ok) {
                    throw new Error("Gagal memuat data PNS berdasarkan golongan.");
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message || "Gagal memuat data PNS berdasarkan golongan."
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
            const res = await apiFetch("/statistik/pns-golongan/export");

            if (!res.ok) {
                throw new Error("Gagal mengekspor data PNS berdasarkan golongan.");
            }

            const blob = await res.blob();
            const filename = filenameFromResponse(res, "pns-golongan.xlsx");

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
                err?.message || "Gagal mengekspor data PNS berdasarkan golongan."
            );
        } finally {
            setExporting(false);
        }
    }

    const totalLakiLaki = data
        ? ROMAWI_LIST.reduce((sum, r) => sum + data[`golongan_${r}`].laki_laki.total, 0)
        : 0;
    const totalPerempuan = data
        ? ROMAWI_LIST.reduce((sum, r) => sum + data[`golongan_${r}`].perempuan.total, 0)
        : 0;

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-start justify-between gap-3 mb-5">
                <div>
                    <h3 className="text-lg font-bold text-[#172033]">
                        PNS Berdasarkan Golongan
                    </h3>
                    <p className="text-sm text-gray-500">
                        Data PNS aktif per golongan dan pangkat/ruang, dipecah menurut
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
                                <th className="border px-3 py-2 text-center w-16">Kode</th>
                                <th className="border px-3 py-2 text-left">
                                    Golongan / Pangkat
                                </th>
                                <th className="border px-2 py-2 text-center">L</th>
                                <th className="border px-2 py-2 text-center">P</th>
                                <th className="border px-2 py-2 text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {ROMAWI_LIST.map((romawi) => (
                                <GolonganRows
                                    key={romawi}
                                    romawi={romawi}
                                    golongan={data[`golongan_${romawi}`]}
                                />
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-gray-100 font-bold">
                                <td colSpan={2} className="border px-3 py-2">Total</td>
                                <td className="border px-2 py-2 text-center">{totalLakiLaki}</td>
                                <td className="border px-2 py-2 text-center">{totalPerempuan}</td>
                                <td className="border px-2 py-2 text-center">{data.jumlah_pns}</td>
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

export default GolonganPanel;