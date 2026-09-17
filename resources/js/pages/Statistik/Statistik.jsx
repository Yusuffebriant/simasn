import { useState } from "react";
import Sidebar from "../../components/Sidebar";
import StrukturalPanel from "./StrukturalPanel";
import FungsionalPanel from "./FungsionalPanel";
import PensiunanPanel from "./PensiunanPanel";
import GolonganPanel from "./GolonganPanel";
import PppkGolonganPanel from "./PppkGolonganPanel";
import AsnPendidikanPanel from "./AsnPendidikanPanel";
import PnsPendidikanPanel from "./PnsPendidikanPanel";
import PppkPendidikanPanel from "./PppkPendidikanPanel";
import SkpdDinasPanel from "./SkpdDinasPanel";
import AsnPerangkatDaerahJenisKelaminPanel from "./AsnPerangkatDaerahJenisKelaminPanel";
import PenjabatPerangkatDaerahPanel from "./PenjabatPerangkatDaerahPanel";
import AsnKemantrenPendidikanPanel from "./AsnKemantrenPendidikanPanel";
import PnsKemantrenPendidikanPanel from "./PnsKemantrenPendidikanPanel";
import PppkKemantrenPendidikanPanel from "./PppkKemantrenPendidikanPanel";
import PnsKemantrenGolonganPanel from "./PnsKemantrenGolonganPanel";
import PppkKemantrenGolonganPanel from "./PppkKemantrenGolonganPanel";
import PnsKelurahanPanel from "./PnsKelurahanPanel";
import PppkKelurahanPanel from "./PppkKelurahanPanel";
import StatistikExportAllPanel from "./StatistikExportAllPanel";

// Menu navigasi lokal di halaman Statistik. "export-all" sengaja
// ditaruh paling atas dan dipisah (lihat render nav di bawah) karena
// dia bukan tabel data seperti menu lain, tapi aksi lintas-tabel — sama
// seperti "Export Laporan" yang jadi tab terpisah di halaman Admin
// (Admin.jsx), bukan menyatu dengan daftar rekap per kategori.
const STATISTIK_MENU = [
    { key: "export-all", label: "Export Semua Statistik", component: StatistikExportAllPanel, ready: true },
    { key: "struktural", label: "Pejabat Struktural", component: StrukturalPanel, ready: true },
    { key: "fungsional", label: "Pejabat Fungsional", component: FungsionalPanel, ready: true },
    { key: "pensiun", label: "Pensiunan PNS", component: PensiunanPanel, ready: true },
    { key: "golongan", label: "PNS Berdasarkan Golongan", component: GolonganPanel, ready: true },
    { key: "pppk-golongan", label: "PPPK Berdasarkan Golongan", component: PppkGolonganPanel, ready: true },
    { key: "asn-pendidikan", label: "ASN Berdasarkan Pendidikan & Gender", component: AsnPendidikanPanel, ready: true },
    { key: "pns-pendidikan", label: "PNS Berdasarkan Pendidikan & Gender", component: PnsPendidikanPanel, ready: true },
    { key: "pppk-pendidikan", label: "PPPK Berdasarkan Pendidikan & Gender", component: PppkPendidikanPanel, ready: true },
    { key: "skpd-dinas", label: "Pegawai Berdasarkan Pendidikan & SKPD", component: SkpdDinasPanel, ready: true },
    { key: "asn-perangkat-daerah-gender", label: "ASN Perangkat Daerah Berdasarkan Jenis Kelamin", component: AsnPerangkatDaerahJenisKelaminPanel, ready: true },
    { key: "penjabat-perangkat-daerah-gender", label: "Penjabat Perangkat Daerah Berdasarkan Jenis Kelamin", component: PenjabatPerangkatDaerahPanel, ready: true },
    { key: "asn-kemantren-pendidikan", label: "ASN Kemantren Berdasarkan Tingkat Pendidikan", component: AsnKemantrenPendidikanPanel, ready: true },
    { key: "pns-kemantren-pendidikan", label: "PNS Kemantren Berdasarkan Tingkat Pendidikan", component: PnsKemantrenPendidikanPanel, ready: true },
    { key: "pppk-kemantren-pendidikan", label: "PPPK Kemantren Berdasarkan Tingkat Pendidikan", component: PppkKemantrenPendidikanPanel, ready: true },
    { key: "pns-kemantren-golongan", label: "PNS Kemantren Berdasarkan Golongan dan Jenis Kelamin", component: PnsKemantrenGolonganPanel, ready: true },
    { key: "pppk-kemantren-golongan", label: "PPPK Kemantren Berdasarkan Golongan dan Jenis Kelamin", component: PppkKemantrenGolonganPanel, ready: true },
    { key: "pns-kelurahan", label: "PNS Kelurahan", component: PnsKelurahanPanel, ready: true },
    { key: "pppk-kelurahan", label: "PPPK Kelurahan", component: PppkKelurahanPanel, ready: true },
];


function Statistik() {
    // Default tetap "struktural" (bukan STATISTIK_MENU[0]) supaya
    // halaman ini masih mendarat di Pejabat Struktural seperti
    // sebelumnya — "export-all" cuma ditaruh paling atas di nav, bukan
    // dimaksudkan jadi tampilan awal saat halaman Statistik dibuka.
    const [activeKey, setActiveKey] = useState("struktural");

    const activeMenu =
        STATISTIK_MENU.find((m) => m.key === activeKey) || STATISTIK_MENU[0];
    const ActivePanel = activeMenu.component;

    // Dipisah dari daftar: "export-all" dirender sendiri di luar area
    // scroll (lihat <aside> di bawah) supaya dia freeze di atas dan
    // tidak ikut turun waktu daftar kategori di-scroll.
    const exportAllMenu = STATISTIK_MENU.find((m) => m.key === "export-all");
    const otherMenus = STATISTIK_MENU.filter((m) => m.key !== "export-all");

    return (
        <div className="flex min-h-screen bg-[#F5F7FA]">
            <Sidebar />

            {/* Navigasi lokal Statistik — ditaruh mengambang di tengah layar
                (vertikal) dan sticky, jadi posisinya tetap di tengah walau
                konten utama di sebelah kanan di-scroll. */}
            <aside className="w-64 shrink-0 self-start sticky top-0 h-screen flex items-center py-6 pl-9 pr-4">
                <div className="w-full max-h-[calc(100vh-3rem)] flex flex-col overflow-hidden bg-white border border-[#E1E5EA] rounded-lg shadow-sm py-5">
                    <h2 className="shrink-0 px-6 text-xs font-semibold uppercase tracking-wide text-[#8A93A0] mb-3">
                        Statistik
                    </h2>

                    {/* Tombol "Export Semua Statistik" ditaruh di luar <nav>
                        yang scroll di bawah, jadi dia freeze di atas dan
                        tidak ikut turun waktu daftar kategori di-scroll.
                        Ukurannya sedikit dikecilkan (px-5 py-2.5, teks 13px)
                        supaya tidak makan tempat terlalu banyak di area
                        yang selalu terlihat ini. */}
                    <button
                        onClick={() => setActiveKey(exportAllMenu.key)}
                        className={`shrink-0 w-full text-left px-5 py-2.5 text-[13px] font-semibold border-l-4 transition-colors text-white border-[#006A4E] ${
                            activeKey === exportAllMenu.key
                                ? "bg-[#00593F]"
                                : "bg-[#006A4E] hover:bg-[#00593F] cursor-pointer"
                        }`}
                    >
                        {exportAllMenu.label}
                    </button>
                    <div className="shrink-0 my-2 border-t border-[#E1E5EA]" />

                    <nav className="max-h-[400px] overflow-y-auto">
                        {otherMenus.map((menu) => {
                            const isActive = activeKey === menu.key;

                            const itemClass = isActive
                                ? "border-[#006A4E] bg-[#E7F1FB] text-[#006A4E]"
                                : `border-transparent text-[#4B5563] ${
                                      menu.ready
                                          ? "hover:bg-[#F5F7FA] cursor-pointer"
                                          : "text-[#B8BFC9] cursor-not-allowed"
                                  }`;

                            return (
                                <button
                                    key={menu.key}
                                    onClick={() => menu.ready && setActiveKey(menu.key)}
                                    disabled={!menu.ready}
                                    title={menu.ready ? undefined : "Segera hadir"}
                                    className={`w-full text-left px-6 py-3 text-sm font-medium border-l-4 transition-colors ${itemClass}`}
                                >
                                    {menu.label}
                                    {!menu.ready && (
                                        <span className="ml-1.5 text-[10px] uppercase text-[#B8BFC9]">
                                            (segera)
                                        </span>
                                    )}
                                </button>
                            );
                        })}
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