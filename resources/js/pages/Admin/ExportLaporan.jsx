import { useState } from "react";
import { LoaderCircle, AlertTriangle, Download, FileSpreadsheet } from "lucide-react";
import { apiFetch } from "../../lib/api";

function defaultPeriode() {
    const now = new Date();
    const bulan = String(now.getMonth() + 1).padStart(2, "0");
    return `${now.getFullYear()}-${bulan}`;
}

function formatPeriodeLabel(periode) {
    const bulanNama = [
        "Januari", "Februari", "Maret", "April", "Mei", "Juni",
        "Juli", "Agustus", "September", "Oktober", "November", "Desember",
    ];
    const [tahun, bln] = periode.split("-");
    const idx = parseInt(bln, 10) - 1;
    return bulanNama[idx] ? `${bulanNama[idx]} ${tahun}` : periode;
}

// Ambil nama file dari header Content-Disposition kalau ada,
// fallback ke nama default berdasarkan periode.
function filenameFromResponse(res, fallback) {
    const disposition = res.headers.get("Content-Disposition") || "";
    const match = disposition.match(/filename="?([^"]+)"?/i);
    return match ? match[1] : fallback;
}

// Rekapitulasi yang tercakup dalam laporan "Export All".
// Yang lain (per-kategori) menyusul belakangan.
const REKAP_LIST = [
    "Rekap Agama",
    "Rekap Pendidikan",
    "Rekap Golongan",
    "Rekap Jabatan",
    "Rekap Eselon & Golongan",
];

function ExportLaporan() {
    const [periode, setPeriode] = useState(defaultPeriode());
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const [success, setSuccess] = useState("");

    async function handleExportAll() {
        if (!periode) {
            setError("Pilih periode terlebih dahulu.");
            return;
        }

        setLoading(true);
        setError("");
        setSuccess("");

        try {
            const res = await apiFetch(
                `/rekap/all/export?periode=${encodeURIComponent(periode)}`
            );

            if (!res.ok) {
                let message = "Gagal mengekspor laporan.";
                try {
                    const body = await res.json();
                    message = body.message || message;
                } catch {
                    // respons bukan JSON (mis. file/blob), pakai pesan default
                }
                throw new Error(message);
            }

            const blob = await res.blob();
            const filename = filenameFromResponse(
                res,
                `rekap-all-${periode}.xlsx`
            );

            const url = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);

            setSuccess(`Laporan rekapitulasi periode ${formatPeriodeLabel(periode)} berhasil diunduh.`);
        } catch (err) {
            setError(err.message || "Gagal mengekspor laporan.");
        } finally {
            setLoading(false);
        }
    }

    return (
        <div>
            <h2 className="text-2xl font-bold mb-5">
                Export Laporan
            </h2>

            <div className="bg-white p-8 rounded-xl shadow">

                <h3 className="text-lg font-bold mb-1">
                    Export Semua Rekapitulasi
                </h3>

                <p className="text-sm text-gray-500 mb-5">
                    Mengunduh 1 file Excel berisi seluruh rekapitulasi
                    (agama, pendidikan, golongan ruang, jabatan, dan
                    eselon) untuk periode yang dipilih — masing-masing
                    rekap pada sheet-nya sendiri.
                </p>

                <div className="flex flex-wrap items-end gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Periode
                        </label>
                        <input
                            type="month"
                            value={periode}
                            onChange={(e) => setPeriode(e.target.value)}
                            className="border p-3 rounded"
                        />
                    </div>

                    <button
                        onClick={handleExportAll}
                        disabled={loading}
                        className="flex items-center gap-2 bg-[#006A4E] text-white px-6 py-3 rounded font-semibold disabled:opacity-60 disabled:cursor-not-allowed hover:bg-[#005a41]"
                    >
                        {loading ? (
                            <>
                                <LoaderCircle className="animate-spin" size={18} />
                                Mengekspor...
                            </>
                        ) : (
                            <>
                                <Download size={18} />
                                Export Excel
                            </>
                        )}
                    </button>
                </div>

                {error && (
                    <div className="flex items-center gap-3 text-red-700 bg-red-50 p-4 rounded-lg text-sm mt-5">
                        <AlertTriangle size={18} />
                        {error}
                    </div>
                )}

                {success && !error && (
                    <div className="flex items-center gap-3 text-[#006A4E] bg-green-50 p-4 rounded-lg text-sm mt-5">
                        <FileSpreadsheet size={18} />
                        {success}
                    </div>
                )}

                <div className="mt-6 pt-5 border-t">
                    <p className="text-xs font-semibold text-gray-500 uppercase mb-2">
                        Isi laporan ini
                    </p>
                    <ul className="text-sm text-gray-600 space-y-1">
                        {REKAP_LIST.map((nama) => (
                            <li key={nama} className="flex items-center gap-2">
                                <span className="w-1.5 h-1.5 rounded-full bg-[#006A4E]" />
                                {nama}
                            </li>
                        ))}
                    </ul>
                </div>

            </div>
        </div>
    );
}

export default ExportLaporan;