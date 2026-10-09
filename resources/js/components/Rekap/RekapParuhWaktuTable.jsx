import { useEffect, useState } from "react";
import { LoaderCircle, AlertTriangle } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { getCurrentPeriode } from "../../lib/periode";
import PeriodeLabel from "./PeriodeLabel";

const JSON_PATH = "/rekap/paruh-waktu";

function RekapParuhWaktuTable() {
    // Default awal bulan berjalan; PeriodeLabel akan menimpanya begitu
    // periode aktif (dari import terakhir yang berhasil) selesai dimuat.
    // Periode di sini tidak bisa dipilih bebas, lihat PeriodeLabel.jsx.
    const [periode, setPeriode] = useState(getCurrentPeriode());
    const [ringkasan, setRingkasan] = useState({});
    const [rows, setRows] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError("");

            try {
                const res = await apiFetch(
                    `${JSON_PATH}?periode=${encodeURIComponent(periode)}`
                );

                if (!res.ok) {
                    throw new Error("Gagal memuat data rekapitulasi.");
                }

                const data = await res.json();
                if (!cancelled) {
                    setRingkasan(data.ringkasan_status ?? {});
                    setRows(Array.isArray(data.per_instansi) ? data.per_instansi : []);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err.message || "Gagal memuat data rekapitulasi.");
                    setRingkasan({});
                    setRows([]);
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        }

        load();

        return () => {
            cancelled = true;
        };
    }, [periode]);

    // Total per kolom dari per_instansi (bisa lebih kecil dari ringkasan_status.PPPK_PW
    // karena pegawai tanpa instansi_id tidak ikut inner join).
    let totalPria = 0;
    let totalWanita = 0;
    let totalJumlah = 0;

    rows.forEach((row) => {
        totalPria += row.pria || 0;
        totalWanita += row.wanita || 0;
        totalJumlah += row.jumlah || 0;
    });

    return (
        <div className="bg-white p-6 rounded-xl shadow">
            <div className="flex flex-wrap items-end justify-between gap-4 mb-5">
                <div>
                    <h3 className="text-lg font-bold">
                        Data PPPK Paruh Waktu
                    </h3>
                    <p className="text-sm text-gray-500">
                        Rekapitulasi pegawai PPPK Paruh Waktu per instansi,
                        dipecah menurut jenis kelamin.
                    </p>
                </div>

                <div className="flex items-end gap-3">
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">
                            Periode
                        </label>
                        <PeriodeLabel onLoaded={setPeriode} />
                    </div>
                </div>
            </div>

            {error && (
                <div className="flex items-center gap-3 text-red-700 bg-red-50 p-3 rounded-lg text-sm mb-4">
                    <AlertTriangle size={16} />
                    {error}
                </div>
            )}

            {loading ? (
                <div className="flex items-center justify-center gap-2 text-gray-500 py-12 text-sm">
                    <LoaderCircle className="animate-spin" size={18} />
                    Memuat data...
                </div>
            ) : (
                <>
                    {/* Kartu Ringkasan Status */}
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
                        <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                                PNS
                            </p>
                            <p className="text-2xl font-bold text-gray-800">
                                {(ringkasan.PNS ?? 0).toLocaleString("id-ID")}
                            </p>
                        </div>

                        <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                                PPPK
                            </p>
                            <p className="text-2xl font-bold text-gray-800">
                                {(ringkasan.PPPK ?? 0).toLocaleString("id-ID")}
                            </p>
                        </div>

                        {/* Kartu PPPK Paruh Waktu diberi penekanan visual karena itu fokus tab ini */}
                        <div className="bg-[#006A4E] border border-[#005a41] rounded-lg p-4 text-center col-span-2 sm:col-span-1">
                            <p className="text-xs font-semibold uppercase tracking-wide text-[#a7d7c9] mb-1">
                                PPPK Paruh Waktu
                            </p>
                            <p className="text-2xl font-bold text-white">
                                {(ringkasan.PPPK_PW ?? 0).toLocaleString("id-ID")}
                            </p>
                        </div>

                        <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                                Lainnya
                            </p>
                            <p className="text-2xl font-bold text-gray-800">
                                {(ringkasan.LAINNYA ?? 0).toLocaleString("id-ID")}
                            </p>
                        </div>

                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                            <p className="text-xs font-semibold uppercase tracking-wide text-blue-600 mb-1">
                                Total
                            </p>
                            <p className="text-2xl font-bold text-blue-800">
                                {(ringkasan.TOTAL ?? 0).toLocaleString("id-ID")}
                            </p>
                        </div>
                    </div>

                    {/* Tabel per Instansi */}
                    {rows.length === 0 ? (
                        <div className="text-center text-gray-400 py-12 text-sm">
                            Tidak ada data untuk periode ini.
                        </div>
                    ) : (
                        <>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm border border-gray-200">
                                    <thead>
                                        <tr className="bg-gray-50">
                                            <th className="border px-3 py-2 text-left align-bottom">No</th>
                                            <th className="border px-3 py-2 text-left align-bottom w-64 max-w-[16rem]">Instansi</th>
                                            <th className="border px-2 py-2 text-center align-bottom">Pria</th>
                                            <th className="border px-2 py-2 text-center align-bottom">Wanita</th>
                                            <th className="border px-2 py-2 text-center align-bottom">Jumlah</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row, i) => (
                                            <tr key={row.instansi} className="hover:bg-gray-50">
                                                <td className="border px-3 py-2">{i + 1}</td>
                                                <td
                                                    className="border px-3 py-2 w-64 max-w-[16rem] whitespace-normal break-words align-top"
                                                    title={row.instansi}
                                                >
                                                    {row.instansi}
                                                </td>
                                                <td className="border px-2 py-2 text-center">{(row.pria || 0).toLocaleString("id-ID")}</td>
                                                <td className="border px-2 py-2 text-center">{(row.wanita || 0).toLocaleString("id-ID")}</td>
                                                <td className="border px-2 py-2 text-center font-bold">{(row.jumlah || 0).toLocaleString("id-ID")}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr className="bg-gray-100 font-bold">
                                            <td colSpan={2} className="border px-3 py-2">Total</td>
                                            <td className="border px-2 py-2 text-center">{totalPria.toLocaleString("id-ID")}</td>
                                            <td className="border px-2 py-2 text-center">{totalWanita.toLocaleString("id-ID")}</td>
                                            <td className="border px-2 py-2 text-center">{totalJumlah.toLocaleString("id-ID")}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <p className="mt-2 text-xs text-gray-400 italic">
                                Pegawai tanpa data instansi tidak tercantum dalam tabel ini.
                            </p>
                        </>
                    )}
                </>
            )}
        </div>
    );
}

export default RekapParuhWaktuTable;
