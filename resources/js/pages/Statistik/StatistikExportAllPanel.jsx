import { useState } from "react";
import { LoaderCircle, AlertTriangle, Download, FileSpreadsheet } from "lucide-react";
import { apiFetch } from "../../lib/api";

// Ambil nama file dari header Content-Disposition kalau ada, fallback ke
// nama default — pola sama seperti filenameFromResponse() di
// ExportLaporan.jsx (halaman Admin) & ExportExcelButton.jsx.
function filenameFromResponse(res, fallback) {
    const disposition = res.headers.get("Content-Disposition") || "";
    const match = disposition.match(/filename="?([^"]+)"?/i);
    return match ? match[1] : fallback;
}

// Daftar tabel yang tercakup dalam "Export Semua Statistik", urut sama
// persis seperti urutan sheet di StatistikAllExport (backend) & urutan
// menu di STATISTIK_MENU (Statistik.jsx).
const TABEL_LIST = [
    "Pejabat Struktural",
    "Pejabat Fungsional",
    "Pensiunan PNS",
    "PNS Berdasarkan Golongan",
    "PPPK Berdasarkan Golongan",
    "ASN Berdasarkan Pendidikan & Gender",
    "PNS Berdasarkan Pendidikan & Gender",
    "PPPK Berdasarkan Pendidikan & Gender",
    "Pegawai Berdasarkan Pendidikan & SKPD",
    "ASN Perangkat Daerah Berdasarkan Jenis Kelamin",
    "Penjabat Perangkat Daerah Berdasarkan Jenis Kelamin",
    "ASN Kemantren Berdasarkan Tingkat Pendidikan",
    "PNS Kemantren Berdasarkan Tingkat Pendidikan",
    "PPPK Kemantren Berdasarkan Tingkat Pendidikan",
    "PNS Kemantren Berdasarkan Golongan",
    "PPPK Kemantren Berdasarkan Golongan",
    "PNS Kelurahan",
    "PPPK Kelurahan",
];

// Beda dengan ExportLaporan.jsx (halaman Admin) yang punya pilihan
// periode lewat PeriodeLabel: seluruh endpoint statistik di halaman ini
// belum memakai parameter periode untuk memfilter data (lihat komentar
// "$periode belum dipakai untuk filter" di banyak method
// StatistikService), jadi di sini tombolnya polos tanpa selector periode
// — sama seperti panel statistik lain yang juga tidak menampilkan
// pilihan periode.
function StatistikExportAllPanel() {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const [success, setSuccess] = useState("");

    async function handleExportAll() {
        setLoading(true);
        setError("");
        setSuccess("");

        try {
            const res = await apiFetch("/statistik/all/export");

            if (!res.ok) {
                let message = "Gagal mengekspor statistik.";
                try {
                    const body = await res.json();
                    message = body.message || message;
                } catch {
                    // respons bukan JSON (mis. file/blob), pakai pesan default
                }
                throw new Error(message);
            }

            const blob = await res.blob();
            const filename = filenameFromResponse(res, "statistik-semua.xlsx");

            const url = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);

            setSuccess("Semua statistik berhasil diunduh.");
        } catch (err) {
            setError(err.message || "Gagal mengekspor statistik.");
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="bg-white p-8 rounded-xl shadow">
            <h3 className="text-lg font-bold text-[#172033] mb-1">
                Export Semua Statistik
            </h3>

            <p className="text-sm text-gray-500 mb-5">
                Mengunduh 1 file Excel berisi seluruh tabel yang ada di
                halaman Statistik ini — masing-masing tabel pada sheet-nya
                sendiri.
            </p>

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
                    Isi file ini ({TABEL_LIST.length} tabel)
                </p>
                <ul className="text-sm text-gray-600 space-y-1">
                    {TABEL_LIST.map((nama) => (
                        <li key={nama} className="flex items-center gap-2">
                            <span className="w-1.5 h-1.5 rounded-full bg-[#006A4E]" />
                            {nama}
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

export default StatistikExportAllPanel;