import { useState } from "react";
import RekapKategoriTable from "../../components/Rekap/RekapKategoriTable";
import RekapGolonganTable from "../../components/Rekap/RekapGolonganTable";
import RekapJabatanTable from "../../components/Rekap/RekapJabatanTable";

// Daftar sub-rekap. "ready: false" = belum dibuatkan (nyusul satu per satu).
const REKAP_TABS = [
    { key: "agama", label: "Agama", ready: true },
    { key: "golongan", label: "Golongan Ruang", ready: true },
    { key: "eselon", label: "Eselon", ready: true },
    { key: "pendidikan", label: "Pendidikan", ready: true },
    { key: "jabatan", label: "Jabatan", ready: true },
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
            <h2 className="text-2xl font-bold mb-5">
                Rekapitulasi ASN
            </h2>

            <div className="flex flex-wrap gap-2 mb-5 border-b">
                {REKAP_TABS.map((tab) => (
                    <button
                        key={tab.key}
                        onClick={() => tab.ready && setActiveTab(tab.key)}
                        disabled={!tab.ready}
                        className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ${
                            activeTab === tab.key
                                ? "border-[#006A4E] text-[#006A4E]"
                                : "border-transparent text-gray-400"
                        } ${
                            tab.ready
                                ? "hover:text-[#006A4E] cursor-pointer"
                                : "cursor-not-allowed"
                        }`}
                        title={tab.ready ? undefined : "Segera hadir"}
                    >
                        {tab.label}
                        {!tab.ready && (
                            <span className="ml-1.5 text-[10px] uppercase text-gray-400">
                                (segera)
                            </span>
                        )}
                    </button>
                ))}
            </div>

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
        </div>
    );
}

export default Rekapitulasi;