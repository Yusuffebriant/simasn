import { useState } from "react";
import { Download, LoaderCircle } from "lucide-react";
import { apiFetch } from "../../../lib/api";

// Ambil nama file dari header Content-Disposition kalau ada, dengan
// fallback ke nama default — logikanya sama persis dengan
// filenameFromResponse() yang sebelumnya ditulis ulang di tiap panel
// (GolonganPanel, PensiunanPanel, PnsKelurahanPanel, dst).
function filenameFromResponse(res, fallback) {
    const disposition = res.headers.get("Content-Disposition") || "";
    const match = disposition.match(/filename="?([^"]+)"?/i);
    return match ? match[1] : fallback;
}

/**
 * Tombol "Export Excel" untuk panel Statistik.
 *
 * Tampilan & perilakunya dibuat sama persis dengan tombol export yang
 * sudah ada di GolonganPanel/PppkGolonganPanel/AsnPendidikanPanel:
 * tombol hijau #006A4E, ikon Download yang berganti jadi spinner selagi
 * proses, dan otomatis disabled selama export/loading. Bedanya, logika
 * fetch -> blob -> klik <a> tersembunyi dikumpulkan di satu tempat supaya
 * panel baru tidak perlu menyalin ulang ~40 baris yang sama.
 *
 * Props:
 * - path: path endpoint export (mis. "/statistik/pns-pendidikan/export")
 * - filename: nama file cadangan kalau server tidak mengirim
 *   Content-Disposition
 * - errorMessage: pesan yang ditampilkan panel kalau export gagal
 * - disabled: dipakai panel untuk mematikan tombol selagi data utama
 *   masih dimuat atau kosong
 * - onError: dipanggil dengan pesan error supaya panel bisa
 *   menampilkannya lewat <ErrorBox> seperti biasa
 */
function ExportExcelButton({
    path,
    filename,
    errorMessage = "Gagal mengekspor data.",
    disabled = false,
    onError,
}) {
    const [exporting, setExporting] = useState(false);

    async function handleExport() {
        setExporting(true);
        onError?.(null);

        try {
            const res = await apiFetch(path);

            if (!res.ok) {
                throw new Error(errorMessage);
            }

            const blob = await res.blob();
            const namaFile = filenameFromResponse(res, filename);

            const url = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = namaFile;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            onError?.(err?.message || errorMessage);
        } finally {
            setExporting(false);
        }
    }

    return (
        <button
            onClick={handleExport}
            disabled={exporting || disabled}
            className="flex items-center gap-2 bg-[#006A4E] text-white px-4 py-2 rounded text-sm font-semibold disabled:opacity-60 disabled:cursor-not-allowed hover:bg-[#005a41]"
        >
            {exporting ? (
                <LoaderCircle className="animate-spin" size={16} />
            ) : (
                <Download size={16} />
            )}
            Export Excel
        </button>
    );
}

export default ExportExcelButton;