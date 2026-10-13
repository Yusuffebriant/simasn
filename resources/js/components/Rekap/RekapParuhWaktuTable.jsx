import { useEffect, useState } from "react";
import { LoaderCircle, AlertTriangle, Download } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { getCurrentPeriode } from "../../lib/periode";
import PeriodeLabel from "./PeriodeLabel";

const JSON_PATH = "/rekap/paruh-waktu";
const EXPORT_PATH = "/rekap/paruh-waktu/export";
const FILENAME_PREFIX = "rekap-paruh-waktu";

const fmt = (n) => (n || 0).toLocaleString("id-ID");
const fmtPersen = (n) =>
    (n || 0).toLocaleString("id-ID", {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    }) + "%";

function filenameFromResponse(res, fallback) {
    const disposition = res.headers.get("Content-Disposition") || "";
    const match = disposition.match(/filename="?([^"]+)"?/i);
    return match ? match[1] : fallback;
}

// Tabel kecil kategori x Pria/Wanita/Jumlah. Dipakai untuk semua rincian
// (jenis jabatan, rincian JF, pendidikan, usia, pensiun, jabatan terbanyak).
// Datanya sudah dikirim backend di /rekap/paruh-waktu.
function TabelKategori({ judul, keterangan, kolomPertama, data }) {
    const total = data.reduce(
        (acc, r) => ({
            pria: acc.pria + (r.pria || 0),
            wanita: acc.wanita + (r.wanita || 0),
            jumlah: acc.jumlah + (r.jumlah || 0),
        }),
        { pria: 0, wanita: 0, jumlah: 0 }
    );

    return (
        <div>
            <h4 className="text-base font-bold mb-1">{judul}</h4>
            <p className="text-xs text-gray-500 mb-3">{keterangan}</p>

            <div className="overflow-x-auto">
                <table className="min-w-full text-sm border border-gray-200">
                    <thead>
                        <tr className="bg-gray-50">
                            <th className="border px-3 py-2 text-left">{kolomPertama}</th>
                            <th className="border px-2 py-2 text-center">Pria</th>
                            <th className="border px-2 py-2 text-center">Wanita</th>
                            <th className="border px-2 py-2 text-center">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((r) => (
                            <tr key={r.kategori} className="hover:bg-gray-50">
                                <td className="border px-3 py-2">{r.kategori}</td>
                                <td className="border px-2 py-2 text-center">{fmt(r.pria)}</td>
                                <td className="border px-2 py-2 text-center">{fmt(r.wanita)}</td>
                                <td className="border px-2 py-2 text-center font-bold">{fmt(r.jumlah)}</td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="bg-gray-100 font-bold">
                            <td className="border px-3 py-2">Total</td>
                            <td className="border px-2 py-2 text-center">{fmt(total.pria)}</td>
                            <td className="border px-2 py-2 text-center">{fmt(total.wanita)}</td>
                            <td className="border px-2 py-2 text-center">{fmt(total.jumlah)}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    );
}

function RekapParuhWaktuTable() {
    // Default awal bulan berjalan; PeriodeLabel akan menimpanya begitu
    // periode aktif (dari import terakhir yang berhasil) selesai dimuat.
    // Periode di sini tidak bisa dipilih bebas, lihat PeriodeLabel.jsx.
    const [periode, setPeriode] = useState(getCurrentPeriode());
    const [ringkasan, setRingkasan] = useState({});
    const [rincianLainnya, setRincianLainnya] = useState([]);
    const [rows, setRows] = useState([]);
    const [perJabatan, setPerJabatan] = useState([]);
    const [perPendidikan, setPerPendidikan] = useState([]);
    const [perUsia, setPerUsia] = useState([]);
    const [perPensiun, setPerPensiun] = useState([]);
    const [perJfRumpun, setPerJfRumpun] = useState([]);
    const [perJabatanTop, setPerJabatanTop] = useState([]);
    const [perPersen, setPerPersen] = useState([]);
    const [perTanpaPw, setPerTanpaPw] = useState([]);
    const [loading, setLoading] = useState(false);
    const [exporting, setExporting] = useState(false);
    const [error, setError] = useState("");

    useEffect(() => {
        let cancelled = false;

        // Rekap periode lama yang dibekukan sebelum fitur ini ada belum
        // punya kunci tambahan -> dianggap kosong, tabelnya tidak tampil.
        const arr = (v) => (Array.isArray(v) ? v : []);

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
                    setRincianLainnya(arr(data.rincian_lainnya));
                    setRows(arr(data.per_instansi));
                    setPerJabatan(arr(data.per_jenis_jabatan));
                    setPerPendidikan(arr(data.per_pendidikan));
                    setPerUsia(arr(data.per_usia));
                    setPerPensiun(arr(data.per_pensiun));
                    setPerJfRumpun(arr(data.per_jf_rumpun));
                    setPerJabatanTop(arr(data.per_jabatan));
                    setPerPersen(arr(data.per_persentase_instansi));
                    setPerTanpaPw(arr(data.per_instansi_tanpa_pw));
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err.message || "Gagal memuat data rekapitulasi.");
                    setRingkasan({});
                    setRincianLainnya([]);
                    setRows([]);
                    setPerJabatan([]);
                    setPerPendidikan([]);
                    setPerUsia([]);
                    setPerPensiun([]);
                    setPerJfRumpun([]);
                    setPerJabatanTop([]);
                    setPerPersen([]);
                    setPerTanpaPw([]);
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

    async function handleExport() {
        setExporting(true);
        setError("");

        try {
            const res = await apiFetch(
                `${EXPORT_PATH}?periode=${encodeURIComponent(periode)}`
            );

            if (!res.ok) {
                throw new Error("Gagal mengekspor rekapitulasi.");
            }

            const blob = await res.blob();
            const filename = filenameFromResponse(
                res,
                `${FILENAME_PREFIX}-${periode}.xlsx`
            );

            const url = window.URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            setError(err.message || "Gagal mengekspor rekapitulasi.");
        } finally {
            setExporting(false);
        }
    }

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

    // Total tabel persentase per instansi.
    let totalPw = 0;
    let totalPegawai = 0;

    perPersen.forEach((row) => {
        totalPw += row.paruh_waktu || 0;
        totalPegawai += row.total_pegawai || 0;
    });

    // Pegawai di instansi tanpa Paruh Waktu tidak masuk penyebut persentase.
    const totalTanpaPw = perTanpaPw.reduce((a, r) => a + (r.jumlah || 0), 0);
    const totalSeluruh = ringkasan.TOTAL ?? 0;
    const persenSeluruh =
        totalSeluruh > 0 ? (totalPw / totalSeluruh) * 100 : 0;

    const adaRincian =
        perJabatan.length > 0 ||
        perJfRumpun.length > 0 ||
        perPendidikan.length > 0 ||
        perUsia.length > 0 ||
        perPensiun.length > 0 ||
        perJabatanTop.length > 0;

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

                    <button
                        onClick={handleExport}
                        disabled={exporting || loading}
                        className="flex items-center gap-2 bg-[#006A4E] text-white px-4 py-2 rounded text-sm font-semibold disabled:opacity-60 disabled:cursor-not-allowed hover:bg-[#005a41]"
                    >
                        {exporting ? (
                            <LoaderCircle className="animate-spin" size={16} />
                        ) : (
                            <Download size={16} />
                        )}
                        Export
                    </button>
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

                            {/* Rincian status Lainnya: NABAN, Pegawai Titipan, dan status lain */}
                            {rincianLainnya.length > 0 && (
                                <div className="mt-3 pt-3 border-t border-gray-200 space-y-1 text-left">
                                    {rincianLainnya.map((r) => (
                                        <div
                                            key={r.label}
                                            className="flex items-center justify-between gap-2 text-xs"
                                        >
                                            <span className="text-gray-500">
                                                {r.label.startsWith("Lainnya (belum")
                                                    ? "Belum terklasifikasi"
                                                    : r.label}
                                            </span>
                                            <span className="font-semibold text-gray-800 tabular-nums">
                                                {fmt(r.total)}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
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
                                                <td className="border px-2 py-2 text-center">{fmt(row.pria)}</td>
                                                <td className="border px-2 py-2 text-center">{fmt(row.wanita)}</td>
                                                <td className="border px-2 py-2 text-center font-bold">{fmt(row.jumlah)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr className="bg-gray-100 font-bold">
                                            <td colSpan={2} className="border px-3 py-2">Total</td>
                                            <td className="border px-2 py-2 text-center">{fmt(totalPria)}</td>
                                            <td className="border px-2 py-2 text-center">{fmt(totalWanita)}</td>
                                            <td className="border px-2 py-2 text-center">{fmt(totalJumlah)}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <p className="mt-2 text-xs text-gray-400 italic">
                                Pegawai tanpa data instansi tidak tercantum dalam tabel ini.
                            </p>
                        </>
                    )}

                    {/* Rincian tambahan */}
                    {adaRincian && (
                        <div className="grid grid-cols-1 xl:grid-cols-2 gap-8 mt-8">
                            {perJabatan.length > 0 && (
                                <TabelKategori
                                    judul="Menurut Jenis Jabatan"
                                    keterangan="Jabatan Fungsional (JF), Pelaksana, dan Struktural. Baris 'Belum diisi / lainnya' adalah pegawai yang jenis jabatannya kosong di data."
                                    kolomPertama="Jenis Jabatan"
                                    data={perJabatan}
                                />
                            )}

                            {perJfRumpun.length > 0 && (
                                <TabelKategori
                                    judul="Rincian Jabatan Fungsional (JF)"
                                    keterangan="Khusus pegawai Paruh Waktu berjabatan fungsional, dikelompokkan Pendidikan, Kesehatan, dan Teknis."
                                    kolomPertama="Rumpun JF"
                                    data={perJfRumpun}
                                />
                            )}

                            {perPendidikan.length > 0 && (
                                <TabelKategori
                                    judul="Menurut Pendidikan"
                                    keterangan="Jumlah PPPK Paruh Waktu per tingkat pendidikan, dipecah menurut jenis kelamin."
                                    kolomPertama="Pendidikan"
                                    data={perPendidikan}
                                />
                            )}

                            {perUsia.length > 0 && (
                                <TabelKategori
                                    judul="Menurut Kelompok Usia"
                                    keterangan="Usia dihitung pada akhir bulan periode, dari tanggal lahir."
                                    kolomPertama="Kelompok Usia"
                                    data={perUsia}
                                />
                            )}

                            {perPensiun.length > 0 && (
                                <TabelKategori
                                    judul="Perkiraan Masa Pensiun"
                                    keterangan="Berdasarkan batas usia pensiun yang tersimpan di data, dikelompokkan per tahun."
                                    kolomPertama="Perkiraan Pensiun"
                                    data={perPensiun}
                                />
                            )}

                            {perJabatanTop.length > 0 && (
                                <TabelKategori
                                    judul="Jabatan Terbanyak"
                                    keterangan="10 jabatan dengan pegawai Paruh Waktu terbanyak; sisanya digabung ke 'Jabatan lainnya'."
                                    kolomPertama="Jabatan"
                                    data={perJabatanTop}
                                />
                            )}
                        </div>
                    )}

                    {/* Persentase per instansi */}
                    {perPersen.length > 0 && (
                        <div className="mt-8">
                            <h4 className="text-base font-bold mb-1">
                                Persentase Paruh Waktu per Instansi
                            </h4>
                            <p className="text-xs text-gray-500 mb-3">
                                Jumlah PPPK Paruh Waktu dibandingkan seluruh pegawai di instansi
                                tersebut. Persentase 50% ke atas ditandai.
                            </p>

                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm border border-gray-200">
                                    <thead>
                                        <tr className="bg-gray-50">
                                            <th className="border px-3 py-2 text-left">No</th>
                                            <th className="border px-3 py-2 text-left w-64 max-w-[16rem]">Instansi</th>
                                            <th className="border px-2 py-2 text-center">PPPK Paruh Waktu</th>
                                            <th className="border px-2 py-2 text-center">Total Pegawai</th>
                                            <th className="border px-2 py-2 text-center">Persentase</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {perPersen.map((row, i) => (
                                            <tr key={row.instansi} className="hover:bg-gray-50">
                                                <td className="border px-3 py-2">{i + 1}</td>
                                                <td
                                                    className="border px-3 py-2 w-64 max-w-[16rem] whitespace-normal break-words align-top"
                                                    title={row.instansi}
                                                >
                                                    {row.instansi}
                                                </td>
                                                <td className="border px-2 py-2 text-center">{fmt(row.paruh_waktu)}</td>
                                                <td className="border px-2 py-2 text-center">{fmt(row.total_pegawai)}</td>
                                                <td
                                                    className={
                                                        "border px-2 py-2 text-center font-bold " +
                                                        ((row.persen || 0) >= 50
                                                            ? "text-amber-700 bg-amber-50"
                                                            : "")
                                                    }
                                                >
                                                    {fmtPersen(row.persen)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr className="bg-gray-100 font-bold">
                                            <td colSpan={2} className="border px-3 py-2">Total</td>
                                            <td className="border px-2 py-2 text-center">{fmt(totalPw)}</td>
                                            <td className="border px-2 py-2 text-center">{fmt(totalPegawai)}</td>
                                            <td className="border px-2 py-2 text-center">
                                                {fmtPersen(totalPegawai > 0 ? (totalPw / totalPegawai) * 100 : 0)}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div className="mt-2 text-xs text-gray-500 space-y-1">
                                <p className="italic text-gray-400">
                                    Total Pegawai mencakup semua status (PNS, PPPK, PPPK Paruh Waktu,
                                    Lainnya) pada instansi yang memiliki PPPK Paruh Waktu.
                                </p>
                                <p>
                                    <b>{fmt(totalPegawai)}</b> adalah jumlah seluruh pegawai aktif
                                    (PNS, PPPK, PPPK Paruh Waktu, dan Lainnya) yang bekerja di{" "}
                                    <b>{fmt(perPersen.length)}</b> instansi yang punya PPPK Paruh Waktu.
                                    Angka itu menjadi penyebut untuk menghitung persentase tiap instansi.
                                </p>
                                {totalTanpaPw > 0 && (
                                    <>
                                        <p>
                                            Angkanya bukan {fmt(totalSeluruh)} karena{" "}
                                            <b>{fmt(totalTanpaPw)}</b> pegawai tidak ikut terhitung, yaitu
                                            pegawai di instansi yang tidak punya PPPK Paruh Waktu:
                                        </p>
                                        <ul className="list-disc ml-5">
                                            {perTanpaPw.map((r) => (
                                                <li key={r.instansi}>
                                                    {r.instansi}: {fmt(r.jumlah)} orang
                                                </li>
                                            ))}
                                        </ul>
                                    </>
                                )}
                                <p>
                                    {fmt(totalPegawai)} + {fmt(totalTanpaPw)} = {fmt(totalPegawai + totalTanpaPw)}
                                    {totalPegawai + totalTanpaPw === totalSeluruh
                                        ? ", sama dengan total pada kartu ringkasan di atas."
                                        : `, sedangkan total kartu ringkasan ${fmt(totalSeluruh)} (selisih ${fmt(Math.abs(totalSeluruh - totalPegawai - totalTanpaPw))} kemungkinan pegawai tanpa data instansi).`}
                                </p>
                                <p>
                                    Persentase pada baris Total ({fmtPersen(totalPegawai > 0 ? (totalPw / totalPegawai) * 100 : 0)})
                                    dihitung dari {fmt(totalPw)} dibagi {fmt(totalPegawai)}, bukan dibagi {fmt(totalSeluruh)}.
                                    Jika dibandingkan dengan seluruh pegawai ({fmt(totalSeluruh)}) hasilnya{" "}
                                    {fmtPersen(persenSeluruh)}.
                                </p>
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}

export default RekapParuhWaktuTable;