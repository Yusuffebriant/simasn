import { useEffect, useState } from "react";
import { LoaderCircle } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { ErrorBox } from "./components/StatUi";
import ExportExcelButton from "./components/ExportExcelButton";

// Urutan & key harus persis sama dengan key yang dikembalikan
// StatistikService::statistikPenjabatPerangkatDaerahJenisKelamin() di
// backend (lihat response controller StatistikPenjabatPerangkatDaerahController).
const PENJABAT_LIST = [
    { key: "kepala_daerah", label: "Kepala Daerah" },
    { key: "mantri_pamong_praja", label: "Jumlah Mantri Pamong Praja" },
    { key: "lurah", label: "Jumlah Lurah" },
    { key: "kepala_opd", label: "Jumlah Kepala OPD" },
    { key: "pejabat_asn_struktural", label: "Jumlah Pejabat ASN Struktural" },
    { key: "pejabat_asn_pelaksana", label: "Jumlah Pejabat ASN Pelaksana" },
    {
        key: "anggota_tim_baperjakat",
        label: "Jumlah Anggota Tim Badan Pertimbangan dan Kepangkatan",
    },
];

function PenjabatPerangkatDaerahPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/penjabat-perangkat-daerah");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data Penjabat Perangkat Daerah berdasarkan jenis kelamin."
                    );
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data Penjabat Perangkat Daerah berdasarkan jenis kelamin."
                    );
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

    // Baris tabel: satu baris per jenis penjabat, tiap baris punya
    // total, laki_laki, perempuan (langsung dari respons API). Tidak ada
    // baris Total gabungan karena tiap kategori berbeda jenis (bukan
    // subset yang saling lepas satu sama lain) — sama seperti versi
    // MiniStatCard sebelumnya yang juga tidak punya kartu total gabungan.
    const rows = data
        ? PENJABAT_LIST.map((p) => ({
              key: p.key,
              label: p.label,
              total: data[p.key]?.total || 0,
              laki_laki: data[p.key]?.laki_laki || 0,
              perempuan: data[p.key]?.perempuan || 0,
          }))
        : [];

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-start justify-between gap-3 mb-5">
                <div>
                    <h3 className="text-lg font-bold text-[#172033]">
                        Penjabat Perangkat Daerah Berdasarkan Jenis Kelamin
                    </h3>
                    <p className="text-sm text-gray-500">
                        Data penjabat perangkat daerah, dipecah menurut jenis kelamin.
                    </p>
                </div>

                <ExportExcelButton
                    path="/statistik/penjabat-perangkat-daerah/export"
                    filename="penjabat-perangkat-daerah.xlsx"
                    errorMessage="Gagal mengekspor data Penjabat Perangkat Daerah berdasarkan jenis kelamin."
                    disabled={loading || !data}
                    onError={setError}
                />
            </div>

            {error && <ErrorBox message={error} />}

            {loading && !data ? (
                <div className="flex items-center justify-center gap-2 text-gray-500 py-12 text-sm">
                    <LoaderCircle className="animate-spin" size={18} />
                    Memuat data...
                </div>
            ) : data ? (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm border border-gray-200">
                        <thead>
                            <tr className="bg-gray-50">
                                <th className="border px-3 py-2 text-left">No</th>
                                <th className="border px-3 py-2 text-left">Jenis Penjabat</th>
                                <th className="border px-2 py-2 text-center">L</th>
                                <th className="border px-2 py-2 text-center">P</th>
                                <th className="border px-2 py-2 text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, i) => (
                                <tr key={row.key} className="hover:bg-gray-50">
                                    <td className="border px-3 py-2">{i + 1}</td>
                                    <td className="border px-3 py-2">{row.label}</td>
                                    <td className="border px-2 py-2 text-center">{row.laki_laki}</td>
                                    <td className="border px-2 py-2 text-center">{row.perempuan}</td>
                                    <td className="border px-2 py-2 text-center font-medium">{row.total}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div className="text-center text-gray-400 py-12 text-sm">
                    Tidak ada data untuk ditampilkan.
                </div>
            )}
        </div>
    );
}

export default PenjabatPerangkatDaerahPanel;