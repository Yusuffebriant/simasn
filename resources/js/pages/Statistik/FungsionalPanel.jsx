import { useEffect, useState } from "react";
import { LoaderCircle } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { ErrorBox } from "./components/StatUi";

// Label rumpun jabatan fungsional tertentu, urutan sesuai tampilan lama
// (kartu Dosen/Guru/Medis/Teknis/Auditor/P2UPD).
const RUMPUN_LIST = [
    { key: "dosen", label: "Dosen" },
    { key: "guru", label: "Guru" },
    { key: "medis", label: "Medis" },
    { key: "teknis", label: "Teknis" },
    { key: "auditor", label: "Auditor" },
    { key: "p2upd", label: "P2UPD" },
];

function FungsionalPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pejabat-fungsional");

                if (!res.ok) {
                    throw new Error("Gagal memuat data pejabat fungsional.");
                }

                const json = await res.json();
                if (!cancelled) setData(json.data);
            } catch (err) {
                if (!cancelled) {
                    setError(err?.message || "Gagal memuat data pejabat fungsional.");
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

    // Total per rumpun dihitung dari data yang sudah ada di API
    // (fungsional_tertentu_laki_laki / fungsional_tertentu_perempuan).
    // Rumpun ini adalah rincian di dalam Fungsional Tertentu (JFT), jadi
    // TIDAK dijumlah lagi ke total bawah supaya tidak dobel hitung.
    const rumpun = data
        ? RUMPUN_LIST.map(({ key, label }) => {
              const laki_laki = data.fungsional_tertentu_laki_laki?.[key] || 0;
              const perempuan = data.fungsional_tertentu_perempuan?.[key] || 0;
              return { key, label, laki_laki, perempuan, total: laki_laki + perempuan };
          })
        : [];

    const totalLakiLaki = data
        ? (data.fungsional_umum.laki_laki || 0) + (data.fungsional_tertentu.laki_laki || 0)
        : 0;
    const totalPerempuan = data
        ? (data.fungsional_umum.perempuan || 0) + (data.fungsional_tertentu.perempuan || 0)
        : 0;
    const totalJumlah = data
        ? (data.fungsional_umum.total || 0) + (data.fungsional_tertentu.total || 0)
        : 0;

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="mb-5">
                <h3 className="text-lg font-bold text-[#172033]">
                    Pejabat Fungsional
                </h3>
                <p className="text-sm text-gray-500">
                    Data pejabat fungsional aktif, dipecah menurut jenis kelamin.
                    Baris "Rumpun ..." adalah rincian di dalam Fungsional Tertentu
                    (JFT) dan tidak dijumlah lagi ke baris Total.
                </p>
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
                                <th className="border px-3 py-2 text-left">Kategori</th>
                                <th className="border px-2 py-2 text-center">L</th>
                                <th className="border px-2 py-2 text-center">P</th>
                                <th className="border px-2 py-2 text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr className="hover:bg-gray-50">
                                <td className="border px-3 py-2">1</td>
                                <td className="border px-3 py-2">Fungsional Umum (JFU)</td>
                                <td className="border px-2 py-2 text-center">{data.fungsional_umum.laki_laki || 0}</td>
                                <td className="border px-2 py-2 text-center">{data.fungsional_umum.perempuan || 0}</td>
                                <td className="border px-2 py-2 text-center font-medium">{data.fungsional_umum.total || 0}</td>
                            </tr>
                            <tr className="hover:bg-gray-50">
                                <td className="border px-3 py-2">2</td>
                                <td className="border px-3 py-2">Fungsional Tertentu (JFT)</td>
                                <td className="border px-2 py-2 text-center">{data.fungsional_tertentu.laki_laki || 0}</td>
                                <td className="border px-2 py-2 text-center">{data.fungsional_tertentu.perempuan || 0}</td>
                                <td className="border px-2 py-2 text-center font-medium">{data.fungsional_tertentu.total || 0}</td>
                            </tr>
                            {rumpun.map((r) => (
                                <tr key={r.key} className="hover:bg-gray-50 text-gray-600">
                                    <td className="border px-3 py-2">&ndash;</td>
                                    <td className="border px-3 py-2 pl-8 italic">Rumpun {r.label}</td>
                                    <td className="border px-2 py-2 text-center">{r.laki_laki}</td>
                                    <td className="border px-2 py-2 text-center">{r.perempuan}</td>
                                    <td className="border px-2 py-2 text-center">{r.total}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-gray-100 font-bold">
                                <td colSpan={2} className="border px-3 py-2">Total (JFU + JFT)</td>
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

export default FungsionalPanel;