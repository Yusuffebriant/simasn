import { useEffect, useState } from "react";
import { Download, LoaderCircle } from "lucide-react";
import { apiFetch } from "../../lib/api";
import KelurahanRekapTable from "./components/KelurahanRekapTable";

// Ambil nama file dari header Content-Disposition kalau ada, dengan
// fallback ke nama default — pola sama seperti filenameFromResponse() di
// RekapGolonganTable.jsx.
function filenameFromResponse(res, fallback) {
    const disposition = res.headers.get("Content-Disposition") || "";
    const match = disposition.match(/filename="?([^"]+)"?/i);
    return match ? match[1] : fallback;
}

// PPPK Kelurahan, dikelompokkan berdasarkan Kemantren induknya (5.03.020).
// Scope: PPPK aktif (status_kepegawaian = 'PPPK') — lihat statistikPppkKelurahan()
// di StatistikService & endpoint GET /statistik/pppk-kelurahan. Ditampilkan
// sebagai tabel rekap (satu baris per Kelurahan, dikelompokkan per
// Kemantren dengan baris subtotal), mengikuti bentuk & layout kolom
// PppkKelurahanExport.php (No, Kemantren, Kelurahan, L, P, Jumlah PPPK) —
// menggantikan tampilan card & grafik sebelumnya. Rincian gender diambil
// dari kelurahan_gender pada response (sebelumnya cuma dipakai export
// Excel, sekarang juga dipakai tabel ini).
const KEMANTREN_KELURAHAN_MAP = {
    TEGALREJO: ["KRICAK", "KARANGWARU", "TEGALREJO", "BENER"],
    JETIS: ["BUMIJO", "COKRODININGRATAN", "GOWONGAN"],
    GONDOKUSUMAN: ["DEMANGAN", "KOTABARU", "KLITREN", "BACIRO", "TERBAN"],
    DANUREJAN: ["SURYATMAJAN", "TEGALPANGGUNG", "BAUSASRAN"],
    GEDONGTENGEN: ["SOSROMENDURAN", "PRINGGOKUSUMAN"],
    NGAMPILAN: ["NGAMPILAN", "NOTOPRAJAN"],
    WIROBRAJAN: ["PAKUNCEN", "WIROBRAJAN", "PATANGPULUHAN"],
    MANTRIJERON: ["GEDONGKIWO", "SURYODININGRATAN", "MANTRIJERON"],
    KRATON: ["PATEHAN", "PANEMBAHAN", "KADIPATEN"],
    GONDOMANAN: ["NGUPASAN", "PRAWIRODIRJAN"],
    PAKUALAMAN: ["PURWOKINANTI", "GUNUNGKETUR"],
    MERGANGSAN: ["KEPARAKAN", "WIROGUNAN", "BRONTOKUSUMAN"],
    UMBULHARJO: [
        "SEMAKI", "MUJAMUJU", "TAHUNAN", "WARUNGBOTO",
        "PANDEYAN", "SOROSUTAN", "GIWANGAN",
    ],
    KOTAGEDE: ["REJOWINANGUN", "PRENGGAN", "PURBAYAN"],
};

function PppkKelurahanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [exporting, setExporting] = useState(false);

    async function handleExport() {
        setExporting(true);
        setError(null);

        try {
            const res = await apiFetch("/statistik/pppk-kelurahan/export");

            if (!res.ok) {
                throw new Error("Gagal mengekspor data PPPK Kelurahan.");
            }

            const blob = await res.blob();
            const filename = filenameFromResponse(res, "pppk-kelurahan.xlsx");

            const url = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            setError(err?.message || "Gagal mengekspor data PPPK Kelurahan.");
        } finally {
            setExporting(false);
        }
    }

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pppk-kelurahan");

                if (!res.ok) {
                    throw new Error("Gagal memuat data PPPK Kelurahan.");
                }

                const json = await res.json();
                // StatistikPnsKelurahanController mengembalikan hasil service
                // apa adanya (tanpa pembungkus { success, data }) — sama
                // seperti controller *Kemantren lain. Dibaca toleran (json.data
                // ?? json) supaya panel tidak pernah tampil kosong walau
                // controllernya belum/tidak dibungkus { data }.
                if (!cancelled) setData(json.data ?? json);
            } catch (err) {
                if (!cancelled) {
                    setError(err?.message || "Gagal memuat data PPPK Kelurahan.");
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        }

        load();
        return () => {
            cancelled = true;
        };
    }, []);

    return (
        <div>
            <div className="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h2 className="text-[#172033] font-semibold text-lg">
                    PPPK Kelurahan
                </h2>

                <button
                    onClick={handleExport}
                    disabled={exporting || loading || !data}
                    className="flex items-center gap-2 bg-[#006A4E] text-white px-4 py-2 rounded text-sm font-semibold disabled:opacity-60 disabled:cursor-not-allowed hover:bg-[#005a41]"
                >
                    {exporting ? (
                        <LoaderCircle className="animate-spin" size={16} />
                    ) : (
                        <Download size={16} />
                    )}
                    Export Excel
                </button>
            </div>

            <KelurahanRekapTable
                title="Rekapitulasi PPPK Kelurahan"
                subtitle={
                    data
                        ? `Jumlah PPPK Kelurahan: ${data.jumlah_pppk_kelurahan?.toLocaleString("id-ID")}`
                        : "Data PPPK per Kelurahan, dikelompokkan menurut Kemantren dan jenis kelamin."
                }
                kemantrenKelurahanMap={KEMANTREN_KELURAHAN_MAP}
                data={data}
                jumlahKey="jumlah_pppk_kelurahan"
                rowLabelSuffix="PPPK"
                loading={loading}
                error={error}
            />
        </div>
    );
}

export default PppkKelurahanPanel;