import { useState } from "react";
import Sidebar from "../../components/Sidebar";
import ImportData from "./ImportData";
import RiwayatImport from "./RiwayatImport";
import Rekapitulasi from "./Rekapitulasi";
import ExportLaporan from "./ExportLaporan";

const TABS = [
    { key: "import", label: "Import Data", component: ImportData },
    { key: "riwayat", label: "Riwayat Import", component: RiwayatImport },
    { key: "rekap", label: "Rekapitulasi", component: Rekapitulasi },
    { key: "export", label: "Export Laporan", component: ExportLaporan },
];

export default function Admin() {

    const [activeTab, setActiveTab] = useState("import");

    const isRekap = activeTab === "rekap";

    const ActiveComponent =
        TABS.find((t) => t.key === activeTab)?.component || ImportData;

    return (
        <div className="flex min-h-screen bg-gray-100">

            <Sidebar />

            <main
                className={`flex-1 min-w-0 p-8 ${
                    isRekap ? "overflow-x-clip" : "overflow-x-auto"
                }`}
            >
                <h1 className="text-4xl font-bold">
                    Admin
                </h1>
                {/* Garis kuning penanda judul, sama seperti halaman
                    Dashboard & Statistik. */}
                <div className="w-20 h-1 bg-[#D4A017] mt-4 mb-4" />

                {/* Posisi & ukuran judul/tab SAMA di semua tab. Khusus tab
                    Rekapitulasi, baris tab ini ikut freeze di atas layar
                    (judul "Admin" ikut ter-scroll). Tinggi baris tab + jarak
                    bawahnya (~69px) dipakai sebagai offset sticky judul
                    "Rekapitulasi ASN" di Rekapitulasi.jsx — kalau ukuran di
                    sini diubah, ubah juga angkanya di sana. */}
                <div
                    className={
                        isRekap
                            ? "sticky top-0 z-30 bg-gray-100 pb-6"
                            : "mb-6"
                    }
                >
                    <div className="flex gap-2 border-b">
                        {TABS.map((tab) => (
                            <button
                                key={tab.key}
                                onClick={() => setActiveTab(tab.key)}
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

                <ActiveComponent />

            </main>

        </div>
    );
}