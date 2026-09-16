import { useEffect, useState } from "react";
import { LoaderCircle } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { ErrorBox } from "./components/StatUi";
import ExportExcelButton from "./components/ExportExcelButton";

// Pegawai Berdasarkan Tingkat Pendidikan dan SKPD (18 Dinas).
// Beda dari panel Pendidikan lainnya (ASN/PNS/PPPK): scope-nya cuma
// staf/pejabat pada 18 Dinas, datanya TIDAK dipecah per gender
// (lihat statistikStafDinasPendidikan/Golongan, statistikPejabatStruktural
// Dinas, statistikPejabatFungsionalDinas, statistikPensiunanDinas di
// StatistikService — semua sudah agregat se-18 dinas). Jadi tiap bagian
// ditampilkan sebagai tabel No/Kategori/Jumlah (tanpa kolom L/P) dengan
// pola yang sama seperti tabel di StrukturalPanel/FungsionalPanel &
// Rekapitulasi ASN di halaman Admin, cuma tanpa pemisahan gender.
const PENDIDIKAN_STAF_LIST = [
    { key: "sd", label: "Tamat SD atau sederajat" },
    { key: "smp", label: "SMP dan sederajat" },
    { key: "sma", label: "SMA dan sederajat" },
    { key: "diploma", label: "Diploma" },
    { key: "strata_1", label: "Strata I" },
    { key: "strata_2", label: "Strata 2" },
    { key: "strata_3", label: "Strata 3" },
];

const GOLONGAN_LIST = ["I", "II", "III", "IV"];
const ESELON_LIST = ["I", "II", "III", "IV"];

// Tabel generik satu kolom nilai (No / label / Jumlah), dipakai berulang
// untuk bagian Pendidikan, Golongan, dan Pejabat Struktural. `totalLabel`
// + `total` opsional menambahkan baris Total di tfoot (dilewati kalau
// tidak diisi, mis. bagian ringkasan yang kategorinya tidak sejenis).
function DinasTable({ title, description, columnLabel, rows, totalLabel, total }) {
    return (
        <div className="bg-white p-6 rounded-xl shadow mb-5">
            <div className="mb-5">
                <h3 className="text-lg font-bold text-[#172033]">{title}</h3>
                {description && (
                    <p className="text-sm text-gray-500">{description}</p>
                )}
            </div>
            <div className="overflow-x-auto">
                <table className="min-w-full text-sm border border-gray-200">
                    <thead>
                        <tr className="bg-gray-50">
                            <th className="border px-3 py-2 text-left">No</th>
                            <th className="border px-3 py-2 text-left">{columnLabel}</th>
                            <th className="border px-2 py-2 text-center">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, i) => (
                            <tr key={row.label} className="hover:bg-gray-50">
                                <td className="border px-3 py-2">{i + 1}</td>
                                <td className="border px-3 py-2">{row.label}</td>
                                <td className="border px-2 py-2 text-center font-medium">{row.value || 0}</td>
                            </tr>
                        ))}
                    </tbody>
                    {total !== undefined && (
                        <tfoot>
                            <tr className="bg-gray-100 font-bold">
                                <td colSpan={2} className="border px-3 py-2">{totalLabel || "Total"}</td>
                                <td className="border px-2 py-2 text-center">{total}</td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        </div>
    );
}

function SkpdDinasPanel() {
    const [pendidikan, setPendidikan] = useState(null);
    const [golongan, setGolongan] = useState(null);
    const [struktural, setStruktural] = useState(null);
    const [fungsional, setFungsional] = useState(null);
    const [pensiunan, setPensiunan] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const [resPendidikan, resGolongan, resStruktural, resFungsional, resPensiunan] =
                    await Promise.all([
                        apiFetch("/statistik/staf-dinas/pendidikan"),
                        apiFetch("/statistik/staf-dinas/golongan"),
                        apiFetch("/statistik/pejabat-struktural-dinas"),
                        apiFetch("/statistik/pejabat-fungsional-dinas"),
                        apiFetch("/statistik/pensiunan-dinas"),
                    ]);

                if (
                    !resPendidikan.ok ||
                    !resGolongan.ok ||
                    !resStruktural.ok ||
                    !resFungsional.ok ||
                    !resPensiunan.ok
                ) {
                    throw new Error(
                        "Gagal memuat data pegawai berdasarkan tingkat pendidikan dan SKPD."
                    );
                }

                const [
                    jsonPendidikan,
                    jsonGolongan,
                    jsonStruktural,
                    jsonFungsional,
                    jsonPensiunan,
                ] = await Promise.all([
                    resPendidikan.json(),
                    resGolongan.json(),
                    resStruktural.json(),
                    resFungsional.json(),
                    resPensiunan.json(),
                ]);

                if (!cancelled) {
                    setPendidikan(jsonPendidikan);
                    setGolongan(jsonGolongan);
                    setStruktural(jsonStruktural);
                    setFungsional(jsonFungsional);
                    setPensiunan(jsonPensiunan);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data pegawai berdasarkan tingkat pendidikan dan SKPD."
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

    const data = pendidikan && golongan && struktural && fungsional && pensiunan;

    const pendidikanRows = pendidikan
        ? PENDIDIKAN_STAF_LIST.map((p) => ({ label: p.label, value: pendidikan[p.key] }))
        : [];

    const golonganRows = golongan
        ? GOLONGAN_LIST.map((g) => ({ label: `Golongan ${g}`, value: golongan.golongan[g] }))
        : [];

    const eselonRows = struktural
        ? ESELON_LIST.map((e) => ({ label: `Eselon ${e}`, value: struktural.eselon[e] }))
        : [];

    const ringkasanRows = data
        ? [
              { label: "Pejabat Fungsional Kantor Dinas Daerah", value: fungsional.jumlah_pejabat_fungsional },
              { label: "Pensiunan Kantor Dinas Daerah", value: pensiunan.jumlah_pensiunan },
          ]
        : [];

    return (
        <div>
            {/* Panel ini terdiri dari beberapa tabel sekaligus, jadi
                tombol Export-nya cuma satu di header — satu file berisi
                semua tabel di bawah. */}
            <div className="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h2 className="text-[#172033] font-semibold text-lg">
                    Pegawai Berdasarkan Tingkat Pendidikan dan SKPD (18 Dinas)
                </h2>

                <ExportExcelButton
                    path="/statistik/staf-dinas/export"
                    filename="pegawai-pendidikan-skpd.xlsx"
                    errorMessage="Gagal mengekspor data pegawai berdasarkan tingkat pendidikan dan SKPD."
                    disabled={loading || !data}
                    onError={setError}
                />
            </div>

            {error && <ErrorBox message={error} />}

            {loading && !data && (
                <div className="flex items-center justify-center gap-2 text-gray-500 py-12 text-sm">
                    <LoaderCircle className="animate-spin" size={18} />
                    Memuat data...
                </div>
            )}

            {data && (
                <>
                    <DinasTable
                        title="Staf Kantor Dinas Daerah Berdasarkan Tingkat Pendidikan"
                        description="Data staf aktif di 18 Kantor Dinas Daerah, dipecah menurut tingkat pendidikan."
                        columnLabel="Tingkat Pendidikan"
                        rows={pendidikanRows}
                        total={pendidikan.jumlah_staf_dinas}
                    />

                    <DinasTable
                        title="Staf Kantor Dinas Daerah Berdasarkan Golongan"
                        description="Data staf aktif di 18 Kantor Dinas Daerah, dipecah menurut golongan ruang."
                        columnLabel="Golongan"
                        rows={golonganRows}
                        total={golongan.jumlah_staf_dinas}
                    />

                    <DinasTable
                        title="Pejabat Struktural Kantor Dinas Daerah Berdasarkan Eselon"
                        description="Data pejabat struktural aktif di 18 Kantor Dinas Daerah, dipecah menurut eselon."
                        columnLabel="Eselon"
                        rows={eselonRows}
                        total={struktural.jumlah_pejabat_struktural}
                    />

                    <DinasTable
                        title="Ringkasan Lainnya"
                        description="Jumlah pejabat fungsional & pensiunan di 18 Kantor Dinas Daerah (dua kategori berbeda, tidak dijumlahkan)."
                        columnLabel="Kategori"
                        rows={ringkasanRows}
                    />
                </>
            )}
        </div>
    );
}

export default SkpdDinasPanel;