import { useState } from "react";
import Sidebar from "../../components/Sidebar";
import StrukturalPanel from "./StrukturalPanel";
import FungsionalPanel from "./FungsionalPanel";
import PensiunanPanel from "./PensiunanPanel";
import GolonganPanel from "./GolonganPanel";
import PppkGolonganPanel from "./PppkGolonganPanel";

// Menu navigasi lokal di halaman Statistik.
// "ready: false" = belum ada endpoint backend-nya, jadi menunya
// ditampilkan tapi nonaktif (pola sama seperti REKAP_TABS di
// pages/Admin/Rekapitulasi.jsx) sampai backend-nya siap.
// "Pensiunan PNS" sudah ready di sisi frontend (model/UI, data dummy)
// walau backend-nya belum ada — lihat catatan di PensiunanPanel.jsx.
const STATISTIK_MENU = [
    { key: "struktural", label: "Pejabat Struktural", component: StrukturalPanel, ready: true },
    { key: "fungsional", label: "Pejabat Fungsional", component: FungsionalPanel, ready: true },
    { key: "pensiun", label: "Pensiunan PNS", component: PensiunanPanel, ready: true },
    { key: "golongan", label: "PNS Berdasarkan Golongan", component: GolonganPanel, ready: true },
    { key: "pppk-golongan", label: "PPPK Berdasarkan Golongan", component: PppkGolonganPanel, ready: true },
];

function Statistik() {
    const [activeKey, setActiveKey] = useState(STATISTIK_MENU[0].key);

    const activeMenu =
        STATISTIK_MENU.find((m) => m.key === activeKey) || STATISTIK_MENU[0];
    const ActivePanel = activeMenu.component;

    return (
        <div className="flex min-h-screen bg-[#F5F7FA]">
            <Sidebar />

            {/* Navigasi lokal Statistik — ditaruh mengambang di tengah layar
                (vertikal) dan sticky, jadi posisinya tetap di tengah walau
                konten utama di sebelah kanan di-scroll. */}
            <aside className="w-64 shrink-0 self-start sticky top-0 h-screen flex items-center py-6 px-4">
                <div className="w-full max-h-[calc(100vh-3rem)] overflow-y-auto bg-white border border-[#E1E5EA] rounded-lg shadow-sm py-5">
                    <h2 className="px-6 text-xs font-semibold uppercase tracking-wide text-[#8A93A0] mb-3">
                        Statistik
                    </h2>
                    <nav>
                        {STATISTIK_MENU.map((menu) => (
                            <button
                                key={menu.key}
                                onClick={() => menu.ready && setActiveKey(menu.key)}
                                disabled={!menu.ready}
                                title={menu.ready ? undefined : "Segera hadir"}
                                className={`w-full text-left px-6 py-3 text-sm font-medium border-l-4 transition-colors ${
                                    activeKey === menu.key
                                        ? "border-[#006A4E] bg-[#E7F1FB] text-[#006A4E]"
                                        : "border-transparent text-[#4B5563]"
                                } ${
                                    menu.ready
                                        ? "hover:bg-[#F5F7FA] cursor-pointer"
                                        : "text-[#B8BFC9] cursor-not-allowed"
                                }`}
                            >
                                {menu.label}
                                {!menu.ready && (
                                    <span className="ml-1.5 text-[10px] uppercase text-[#B8BFC9]">
                                        (segera)
                                    </span>
                                )}
                            </button>
                        ))}
                    </nav>
                </div>
            </aside>

            <main className="flex-1 p-10 overflow-x-auto">
                <div className="mb-9">
                    <h1 className="text-3xl font-bold text-[#172033]">
                        Statistik Pegawai
                    </h1>
                    <div className="w-20 h-1 bg-[#D4A017] mt-4 mb-4" />
                    <p className="text-[#687386] text-[15px]">
                        Rekap jumlah pegawai dari data yang sudah diimport,
                        dikelompokkan per kategori di menu sebelah kiri.
                    </p>
                </div>

                {ActivePanel ? (
                    <ActivePanel />
                ) : (
                    <div className="bg-white border border-[#E1E5EA] rounded-xl p-8 text-center text-[#687386]">
                        Statistik "{activeMenu.label}" belum tersedia — menunggu
                        endpoint backend-nya.
                    </div>
                )}
            </main>
        </div>
    );
}

export default Statistik;