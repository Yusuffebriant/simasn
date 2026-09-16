import { useEffect, useState } from "react";
import { LoaderCircle } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { ErrorBox } from "./components/StatUi";
import ExportExcelButton from "./components/ExportExcelButton";
import { PENDIDIKAN_LIST } from "./pendidikanList";

// PPPK Berdasarkan Tingkat Pendidikan dan Jenis Kelamin (hanya PPPK
// aktif — lihat statistikPppkPendidikan() di StatistikService). Tabelnya
// mengikuti pola yang sama seperti PnsPendidikanPanel/StrukturalPanel &
// tabel Rekapitulasi ASN di halaman Admin: No / label / L / P / Total,
// dengan baris Total di tfoot.
function PppkPendidikanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pppk-pendidikan");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data PPPK berdasarkan tingkat pendidikan."
                    );
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data PPPK berdasarkan tingkat pendidikan."
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

    // Baris tabel: satu baris per jenjang pendidikan (SD s.d. S3), tiap
    // baris punya total, laki_laki, perempuan (langsung dari respons API).
    const rows = data
        ? PENDIDIKAN_LIST.map((p) => ({
              key: p.key,
              label: p.label,
              ...data[p.key],
          }))
        : [];

    const totalLakiLaki = rows.reduce((sum, r) => sum + (r.laki_laki || 0), 0);
    const totalPerempuan = rows.reduce((sum, r) => sum + (r.perempuan || 0), 0);
    const totalJumlah = data ? data.jumlah_pppk : 0;

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-start justify-between gap-3 mb-5">
                <div>
                    <h3 className="text-lg font-bold text-[#172033]">
                        PPPK Berdasarkan Tingkat Pendidikan dan Jenis Kelamin
                    </h3>
                    <p className="text-sm text-gray-500">
                        Data PPPK aktif per tingkat pendidikan, dipecah menurut jenis kelamin.
                    </p>
                </div>

                <ExportExcelButton
                    path="/statistik/pppk-pendidikan/export"
                    filename="pppk-pendidikan.xlsx"
                    errorMessage="Gagal mengekspor data PPPK berdasarkan tingkat pendidikan."
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
                                <th className="border px-3 py-2 text-left">Tingkat Pendidikan</th>
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
                                    <td className="border px-2 py-2 text-center">{row.laki_laki || 0}</td>
                                    <td className="border px-2 py-2 text-center">{row.perempuan || 0}</td>
                                    <td className="border px-2 py-2 text-center font-medium">{row.total || 0}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-gray-100 font-bold">
                                <td colSpan={2} className="border px-3 py-2">Total</td>
                                <td className="border px-2 py-2 text-center">{totalLakiLaki}</td>
                                <td className="border px-2 py-2 text-center">{totalPerempuan}</td>
                                <td className="border px-2 py-2 text-center">{totalJumlah}</td>
                            </tr>
                        </tfoot>
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

export default PppkPendidikanPanel;