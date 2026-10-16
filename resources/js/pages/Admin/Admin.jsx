import { useState } from "react";
import Sidebar from "../../components/Sidebar";
import ImportData from "./ImportData";
import RiwayatImport from "./RiwayatImport";
import Rekapitulasi from "./Rekapitulasi";
import ExportLaporan from "./ExportLaporan";
import DaftarPegawai from "./DaftarPegawai";
import PerubahanBulanan from "./PerubahanBulanan";
import IndeksKumulatif from "./IndeksKumulatif";
import PeringatanData from "../../components/Admin/PeringatanData";

const TABS = [
    { key: "import", label: "Import Data", component: ImportData },
    { key: "riwayat", label: "Riwayat Import", component: RiwayatImport },
    { key: "rekap", label: "Rekapitulasi", component: Rekapitulasi },
    { key: "pegawai", label: "Daftar Pegawai", component: DaftarPegawai },
    { key: "indeks", label: "Indeks Kumulatif", component: IndeksKumulatif },
    { key: "perubahan", label: "Perubahan Pegawai", component: PerubahanBulanan },
    { key: "export", label: "Export Laporan", component: ExportLaporan },
];

// Tab awal bisa ditentukan lewat URL, mis. /admin?tab=pegawai&kelengkapan=belum
// (dipakai tautan "Lengkapi" dari hasil import dan banner peringatan).
function tabAwal() {
    const key = new URLSearchParams(window.location.search).get("tab");
    return TABS.some((t) => t.key === key) ? key : "import";
}

export default function Admin() {

    const [activeTab, setActiveTab] = useState(tabAwal);

    // Pindah tab secara manual = filter dari URL tidak berlaku lagi.
    function pilihTab(key) {
        setActiveTab(key);
        if (window.location.search) {
            window.history.replaceState(null, "", window.location.pathname);
        }
    }

    const isRekap = activeTab === "rekap";

    const ActiveComponent =
        TABS.find((t) => t.key === activeTab)?.component || ImportData;

    return (
        <div className="flex min-h-screen bg-gray-100">

            <Sidebar />

            <main
                className={`flex-1 min-w-0 p-8 ${isRekap ? "overflow-x-clip" : "overflow-x-auto"
                    }`}
            >
                {/* Blok judul: kelasnya SAMA di semua tab, jadi posisi &
                    ukurannya pasti seragam. */}
                <div className="mb-6">
                    <h1 className="text-4xl font-bold">
                        Admin
                    </h1>
                    {/* Garis kuning penanda judul, sama seperti halaman
                        Dashboard & Statistik. */}
                    <div className="w-20 h-1 bg-[#D4A017] mt-4 mb-4" />

                    <div className="flex gap-2 border-b">
                        {TABS.map((tab) => (
                            <button
                                key={tab.key}
                                onClick={() => pilihTab(tab.key)}
                                className={`
                                    px-4 py-3 text-sm font-semibold border-b-2 -mb-px
                                    ${activeTab === tab.key
                                        ? "border-[#006A4E] text-[#006A4E]"
                                        : "border-transparent text-gray-500 hover:text-gray-700"}
                                `}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>
                </div>

                <PeringatanData refreshKey={activeTab} />

                <ActiveComponent />

            </main>

        </div>
    );
}