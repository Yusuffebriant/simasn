import { useState } from "react";
import RekapKategoriTable from "../../components/Rekap/RekapKategoriTable";
import RekapGolonganTable from "../../components/Rekap/RekapGolonganTable";
import RekapJabatanTable from "../../components/Rekap/RekapJabatanTable";
import RekapNakesTable from "../../components/Rekap/RekapNakesTable";
import RekapSdSmpTable from "../../components/Rekap/RekapSdSmpTable";
import RekapKecamatanTable from "../../components/Rekap/RekapKecamatanTable";
import RekapJfTertentuTable from "../../components/Rekap/RekapJfTertentuTable";
import RekapJfPelaksanaTable from "../../components/Rekap/RekapJfPelaksanaTable";
import RekapStrukturGolonganTable from "../../components/Rekap/RekapStrukturGolonganTable";
import RekapStrukturEselonTable from "../../components/Rekap/RekapStrukturEselonTable";
import RekapPensiunTable from "../../components/Rekap/RekapPensiunTable";

// Daftar sub-rekap. "ready: false" = belum dibuatkan (nyusul satu per satu).
const REKAP_TABS = [
    { key: "agama", label: "Agama", ready: true },
    { key: "golongan", label: "Golongan Ruang", ready: true },
    { key: "eselon", label: "Eselon", ready: true },
    { key: "pendidikan", label: "Pendidikan", ready: true },
    { key: "jabatan", label: "Jabatan", ready: true },
    { key: "nakes", label: "Nakes", ready: true },
    { key: "sd-smp", label: "SD & SMP", ready: true },
    { key: "kecamatan", label: "Kecamatan/Kelurahan", ready: true },
    { key: "jf-tertentu", label: "JF Tertentu", ready: true },
    { key: "jf-pelaksana", label: "JF Pelaksana", ready: true },
    { key: "struktur-golongan", label: "Struktural per Golongan", ready: true },
    { key: "struktur-eselon", label: "Struktural per Eselon", ready: true },
    { key: "pensiun", label: "Pensiun", ready: true },
];

const AGAMA_LIST = ["Islam", "Kristen", "Katholik", "Hindu", "Budha"];

// Sesuai golonganList di App\Exports\RekapEselonGolonganGenderExport &
// RekapService::rekapEselonGolonganGender.
const ESELON_GOLONGAN_LIST = [
    "III/a", "III/b", "III/c", "III/d",
    "IV/a", "IV/b", "IV/c", "IV/d", "IV/e",
];

// Sesuai pendidikanList di App\Exports\RekapPendidikanExport &
// RekapService::rekapPendidikan.
const PENDIDIKAN_LIST = [
    "SD", "SLTP", "SLTA",
    "D I", "D II", "D III", "D IV",
    "S1", "S2", "S3",
];

function Rekapitulasi() {
    const [activeTab, setActiveTab] = useState("agama");

    return (
        <div>
            {/* Jarak bawah 24px. Tinggi menu kiri & area tabel dibatasi
                calc(100vh-265px) (= tinggi layar dikurangi bagian atas
                halaman), jadi halaman pas satu layar: judul dan tab tidak
                bergeser, yang di-scroll hanya menu kiri dan tabel. */}
            <h2 className="mb-6 text-2xl font-bold">
                Rekapitulasi ASN
            </h2>

            {/* Dua kolom: menu kategori di kiri, tabel rekap di kanan.
                Masing-masing scroll sendiri di dalam kolomnya. */}
            <div className="flex items-start gap-6">
                <aside className="w-56 shrink-0">
                    <div className="max-h-[calc(100vh-265px)] flex flex-col overflow-hidden bg-white border border-[#E1E5EA] rounded-lg shadow-sm py-4">
                        <h3 className="shrink-0 px-5 text-[11px] font-semibold uppercase tracking-wide text-[#8A93A0] mb-2">
                            Rekapitulasi
                        </h3>

                        <nav className="min-h-0 overflow-y-auto">
                            {REKAP_TABS.map((tab) => {
                                const isActive = activeTab === tab.key;

                                const itemClass = isActive
                                    ? "border-[#006A4E] bg-[#E7F1FB] text-[#006A4E]"
                                    : `border-transparent ${
                                          tab.ready
                                              ? "text-[#4B5563] hover:bg-[#F5F7FA] cursor-pointer"
                                              : "text-[#B8BFC9] cursor-not-allowed"
                                      }`;

                                return (
                                    <button
                                        key={tab.key}
                                        onClick={() => tab.ready && setActiveTab(tab.key)}
                                        disabled={!tab.ready}
                                        title={tab.ready ? undefined : "Segera hadir"}
                                        className={`w-full text-left px-5 py-2 text-[13px] font-medium border-l-4 transition-colors ${itemClass}`}
                                    >
                                        {tab.label}
                                        {!tab.ready && (
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

                {/* Area tabel: kolom ini scroll sendiri. Wrapper ".overflow-x-auto"
                    milik tabel-tabel rekap dijadikan scroller dua arah setinggi
                    sisa layar, jadi scrollbar horizontalnya tetap kelihatan. */}
                <div className="flex-1 min-w-0 max-h-[calc(100vh-265px)] overflow-y-auto [&_.overflow-x-auto]:overflow-auto [&_.overflow-x-auto]:max-h-[calc(100vh-385px)] [&_.overflow-x-auto]:min-h-[20rem]">
                    {activeTab === "agama" && (
                        <RekapKategoriTable
                            title="Rekapitulasi Berdasarkan Agama"
                            jsonPath="/rekap/agama"
                            exportPath="/rekap/agama/export"
                            categories={AGAMA_LIST}
                            filenamePrefix="rekap-agama"
                        />
                    )}

                    {activeTab === "golongan" && <RekapGolonganTable />}

                    {activeTab === "eselon" && (
                        <RekapKategoriTable
                            title="Rekapitulasi Berdasarkan Eselon & Golongan Ruang"
                            jsonPath="/rekap/eselon-golongan-gender"
                            exportPath="/rekap/eselon-golongan-gender/export"
                            categories={ESELON_GOLONGAN_LIST}
                            filenamePrefix="rekap-eselon-golongan-gender"
                            rowField="eselon"
                            rowHeaderLabel="Eselon"
                        />
                    )}

                    {activeTab === "pendidikan" && (
                        <RekapKategoriTable
                            title="Rekapitulasi Berdasarkan Pendidikan"
                            jsonPath="/rekap/pendidikan"
                            exportPath="/rekap/pendidikan/export"
                            categories={PENDIDIKAN_LIST}
                            filenamePrefix="rekap-pendidikan"
                        />
                    )}

                    {activeTab === "jabatan" && <RekapJabatanTable />}

                    {activeTab === "nakes" && <RekapNakesTable />}

                    {activeTab === "sd-smp" && <RekapSdSmpTable />}

                    {activeTab === "kecamatan" && <RekapKecamatanTable />}

                    {activeTab === "jf-tertentu" && <RekapJfTertentuTable />}

                    {activeTab === "jf-pelaksana" && <RekapJfPelaksanaTable />}

                    {activeTab === "struktur-golongan" && <RekapStrukturGolonganTable />}

                    {activeTab === "struktur-eselon" && <RekapStrukturEselonTable />}

                    {activeTab === "pensiun" && <RekapPensiunTable />}
                </div>
            </div>
        </div>
    );
}

export default Rekapitulasi;