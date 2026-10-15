import { useEffect, useState } from "react";
import { AlertTriangle, Info } from "lucide-react";
import { apiFetch } from "../../lib/api";
import {
    getCurrentPeriode,
    formatPeriodeLabel,
    BULAN_NAMA,
} from "../../lib/periode";

function PreviewExcel({
    headers,
    preview,
    periode,
    setPeriode,
    next,
    back
}) {

    const sekarang = getCurrentPeriode();
    const [tahunIni, bulanIni] = sekarang.split("-");

    const [bulan, setBulan] = useState(periode ? periode.split("-")[1] : "");
    const [tahun, setTahun] = useState(periode ? periode.split("-")[0] : "");
    const [aktif, setAktif] = useState(null);

    // Tahun yang bisa dipilih: 5 tahun terakhir sampai tahun ini.
    const daftarTahun = Array.from({ length: 6 }, (_, i) => String(Number(tahunIni) - i));

    // Periode data yang sedang berlaku (import terakhir yang selesai),
    // dipakai hanya untuk keterangan/peringatan di bawah pilihan.
    useEffect(() => {
        let batal = false;

        apiFetch("/periode/aktif")
            .then((res) => (res.ok ? res.json() : null))
            .then((json) => {
                if (!batal) setAktif(json?.periode || null);
            })
            .catch(() => { });

        return () => { batal = true; };
    }, []);

    function pilih(b, t) {
        // Pindah ke tahun ini: bulan yang belum terjadi dikosongkan.
        if (t === tahunIni && b > bulanIni) {
            b = "";
        }

        setBulan(b);
        setTahun(t);
        setPeriode(b && t ? `${t}-${b}` : "");
    }

    return (
        <div>

            <div className="bg-white rounded-xl shadow p-8 mb-6">

                <h2 className="text-xl font-bold mb-2">
                    Periode Data
                </h2>

                <p className="text-gray-600 mb-4">
                    Pilih bulan dan tahun data pegawai yang ada di file Excel
                    ini. Periode tidak terisi otomatis, jadi pastikan sesuai
                    dengan isi file sebelum lanjut import.
                </p>

                <div className="flex flex-wrap gap-3">
                    <select
                        value={bulan}
                        onChange={(e) => pilih(e.target.value, tahun)}
                        aria-label="Bulan"
                        className="border p-3 rounded bg-white font-semibold text-gray-800 cursor-pointer min-w-[10rem]"
                    >
                        <option value="">Pilih bulan</option>
                        {BULAN_NAMA.map((nama, i) => {
                            const kode = String(i + 1).padStart(2, "0");
                            const lewat = tahun === tahunIni && kode > bulanIni;
                            return (
                                <option key={kode} value={kode} disabled={lewat}>
                                    {nama}
                                </option>
                            );
                        })}
                    </select>

                    <select
                        value={tahun}
                        onChange={(e) => pilih(bulan, e.target.value)}
                        aria-label="Tahun"
                        className="border p-3 rounded bg-white font-semibold text-gray-800 cursor-pointer min-w-[8rem]"
                    >
                        <option value="">Pilih tahun</option>
                        {daftarTahun.map((t) => (
                            <option key={t} value={t}>
                                {t}
                            </option>
                        ))}
                    </select>
                </div>

                {periode ? (
                    <p className="text-sm text-gray-600 mt-3">
                        Data akan tercatat sebagai periode{" "}
                        <span className="font-semibold">
                            {formatPeriodeLabel(periode)}
                        </span>
                        .
                    </p>
                ) : (
                    <p className="text-sm text-amber-700 mt-3">
                        Pilih bulan dan tahun dulu untuk melanjutkan.
                    </p>
                )}

                {aktif && (
                    <p className="text-xs text-gray-500 mt-2">
                        Data yang sedang berlaku sekarang: periode{" "}
                        <span className="font-semibold">
                            {formatPeriodeLabel(aktif)}
                        </span>
                        .
                    </p>
                )}

                {periode && aktif && periode < aktif && (
                    <div className="mt-3 flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                        <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                        <span>
                            Periode yang dipilih lebih lama dari data yang sedang
                            berlaku. Import ini akan menggantikan data sekarang
                            dengan data lama, dan catatan perubahannya akan
                            terbaca terbalik. Lanjutkan hanya jika memang
                            disengaja.
                        </span>
                    </div>
                )}

                {periode && aktif && periode === aktif && (
                    <div className="mt-3 flex items-start gap-2 rounded-lg bg-blue-50 border border-blue-200 p-3 text-sm text-blue-800">
                        <Info size={16} className="mt-0.5 shrink-0" />
                        <span>
                            Periode ini sama dengan data yang sedang berlaku.
                            Import akan memperbarui data tersebut.
                        </span>
                    </div>
                )}

            </div>

            <div className="bg-white rounded-xl shadow overflow-hidden">

                <div className="px-6 py-4 border-b border-gray-100">
                    <h2 className="text-xl font-bold text-gray-800">
                        Preview Data Excel
                    </h2>
                    <p className="text-sm text-gray-500 mt-1">
                        Geser ke samping untuk melihat kolom lainnya.
                    </p>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-max w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                {headers.map((header, index) => (
                                    <th
                                        key={index}
                                        className="px-4 py-3 bg-[#006A4E] text-white text-left font-semibold whitespace-nowrap"
                                    >
                                        {header || "-"}
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        <tbody>
                            {preview.map((row, rowIndex) => (
                                <tr
                                    key={rowIndex}
                                    className="border-b border-gray-100 last:border-b-0 even:bg-gray-50 hover:bg-[#006A4E]/5"
                                >
                                    {headers.map((_, colIndex) => (
                                        <td
                                            key={colIndex}
                                            className="px-4 py-3 text-gray-800 whitespace-nowrap"
                                        >
                                            {row[colIndex] ?? "-"}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

            </div>

            <div className="mt-6 flex gap-3">
                <button
                    onClick={back}
                    className="px-5 py-2 bg-gray-300 rounded"
                >
                    Kembali
                </button>

                <button
                    onClick={next}
                    disabled={!periode}
                    title={periode ? "" : "Pilih bulan dan tahun dulu"}
                    className="px-5 py-2 bg-[#006A4E] text-white rounded disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    Lanjut Import
                </button>
            </div>

        </div>
    )
}

export default PreviewExcel;