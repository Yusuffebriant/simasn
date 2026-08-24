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

    const ActiveComponent =
        TABS.find((t) => t.key === activeTab)?.component || ImportData;

    return (
        <div className="flex min-h-screen bg-gray-100">

            <Sidebar />

            <main className="flex-1 p-8 overflow-x-auto">

                <h1 className="text-4xl font-bold mb-8">
                    Admin
                </h1>

                <div className="flex gap-2 mb-6 border-b">
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

                <ActiveComponent />

            </main>

        </div>
    );
}
