import { useEffect, useState } from "react";
import { Info, AlertTriangle } from "lucide-react";
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
    const [tahunIni] = sekarang.split("-");

    const [bulan, setBulan] = useState(periode ? periode.split("-")[1] : "");
    const [tahun, setTahun] = useState(periode ? periode.split("-")[0] : "");
    const [aktif, setAktif] = useState(null);

    // Tahun yang bisa dipilih: tahun depan sampai 6 tahun sebelumnya.
    const daftarTahun = Array.from({ length: 8 }, (_, i) => String(Number(tahunIni) + 1 - i));

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

                <div className="mb-4 flex items-start gap-2 rounded-lg bg-blue-50 border border-blue-200 p-3 text-sm text-blue-800">
                    <Info size={16} className="mt-0.5 shrink-0" />
                    <div>
                        <p className="font-semibold">Upload sesuai urutan bulan</p>
                        <ul className="mt-1 list-disc pl-5 space-y-0.5">
                            <li>
                                Setiap upload <b>mengganti seluruh data pegawai</b> dengan
                                isi file. Data yang berlaku di sistem adalah file yang
                                diupload paling akhir.
                            </li>
                            <li>
                                Upload dari bulan paling lama ke paling baru (misalnya
                                Oktober dulu, lalu November). Arsip tiap bulan tersimpan,
                                dan perubahan antar bulan dihitung otomatis.
                            </li>
                            <li>
                                Jangan mengupload bulan lama setelah bulan baru, kecuali
                                untuk koreksi. Setelah koreksi, upload ulang file bulan
                                terbaru supaya data yang berlaku kembali benar.
                            </li>
                        </ul>
                    </div>
                </div>

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
                            return (
                                <option key={kode} value={kode}>
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
                    <div className="mt-3 flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-300 p-3 text-sm text-amber-900">
                        <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                        <div>
                            <p className="font-semibold">
                                Periode ini lebih lama dari data yang sedang berlaku
                            </p>
                            <p className="mt-1">
                                Import tetap berjalan, tetapi data pegawai di sistem akan
                                diganti dengan isi file {formatPeriodeLabel(periode)}, bukan
                                lagi {formatPeriodeLabel(aktif)}. Dashboard dan daftar pegawai
                                akan menampilkan data bulan lama. Di Riwayat Import, &ldquo;Lihat
                                Perubahan&rdquo; untuk upload ini dibandingkan dengan upload
                                sebelumnya sehingga bisa terbaca terbalik (yang baru tampak
                                hilang). Arsip dan menu Indeks Kumulatif tetap benar karena
                                disusun menurut bulan.
                            </p>
                            <p className="mt-1">
                                Kalau ini untuk koreksi, setelah selesai upload ulang file{" "}
                                {formatPeriodeLabel(aktif)} supaya data yang berlaku kembali
                                ke bulan terbaru.
                            </p>
                        </div>
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
                    title={!periode ? "Pilih bulan dan tahun dulu" : ""}
                    className="px-5 py-2 bg-[#006A4E] text-white rounded disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    Lanjut Import
                </button>
            </div>

        </div>
    )
}

export default PreviewExcel;