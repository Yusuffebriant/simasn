import { CheckCircle2, XCircle, AlertTriangle, Info } from "lucide-react";

function ImportResult({ result, onRestart }) {

    const total = result?.total_baris ?? 0;
    const berhasil = result?.berhasil ?? 0;
    const gagal = result?.gagal ?? 0;
    const gagalTotal = result?.status === "gagal";

    // Hasil sinkron "pegawai sisa" (yang tidak ada di file ini).
    // dinonaktifkan | dilewati | tidak_ada_sisa
    const sinkronStatus = result?.sinkron_status;
    const sinkronDilewati = sinkronStatus === "dilewati";

    // Rekonsiliasi: isi file dibandingkan dengan isi database.
    // cocok | selisih | tidak_tersedia
    const rekon = result?.rekonsiliasi;
    const rekonStatus = result?.rekonsiliasi_status;
    const rekonSelisih = rekon?.selisih ?? [];

    return (
        <div className="bg-white rounded-xl shadow p-8">

            <div className="flex items-center gap-3 mb-6">
                {gagalTotal ? (
                    <XCircle className="text-red-600" size={32} />
                ) : (
                    <CheckCircle2 className="text-[#006A4E]" size={32} />
                )}
                <h2 className="text-2xl font-bold">
                    {gagalTotal ? "Import Gagal Diproses" : "Import Selesai"}
                </h2>
            </div>

            <div className="grid grid-cols-3 gap-5 mb-6">
                <div className="bg-gray-100 p-5 rounded-xl">
                    <p className="text-gray-500 text-sm">Total Baris</p>
                    <h3 className="text-3xl font-bold">{total}</h3>
                </div>

                <div className="bg-green-50 p-5 rounded-xl">
                    <p className="text-gray-500 text-sm">Berhasil</p>
                    <h3 className="text-3xl font-bold text-[#006A4E]">{berhasil}</h3>
                </div>

                <div className="bg-red-50 p-5 rounded-xl">
                    <p className="text-gray-500 text-sm">Gagal</p>
                    <h3 className="text-3xl font-bold text-red-600">{gagal}</h3>
                </div>
            </div>

            {sinkronStatus && !gagalTotal && (
                <div
                    className={`flex items-start gap-3 p-4 rounded-lg text-sm mb-6 ${sinkronDilewati
                            ? "bg-amber-50 text-amber-800"
                            : "bg-blue-50 text-blue-800"
                        }`}
                >
                    {sinkronDilewati ? (
                        <AlertTriangle size={18} className="mt-0.5 shrink-0" />
                    ) : (
                        <Info size={18} className="mt-0.5 shrink-0" />
                    )}
                    <div>
                        <p className="font-semibold">
                            {sinkronDilewati
                                ? "Penonaktifan pegawai lama dilewati"
                                : "Sinkron pegawai lama"}
                        </p>
                        <p>{result?.sinkron_catatan}</p>
                    </div>
                </div>
            )}

            {rekonStatus && !gagalTotal && (
                <div
                    className={`p-4 rounded-lg text-sm mb-6 ${rekonStatus === "cocok"
                            ? "bg-green-50 text-green-800"
                            : "bg-amber-50 text-amber-800"
                        }`}
                >
                    <div className="flex items-start gap-3">
                        {rekonStatus === "cocok" ? (
                            <CheckCircle2 size={18} className="mt-0.5 shrink-0" />
                        ) : (
                            <AlertTriangle size={18} className="mt-0.5 shrink-0" />
                        )}
                        <div className="w-full">
                            <p className="font-semibold">
                                {rekonStatus === "cocok" &&
                                    "Rekonsiliasi: data di database sama dengan isi file"}
                                {rekonStatus === "selisih" &&
                                    "Rekonsiliasi: ada selisih antara file dan database"}
                                {rekonStatus === "tidak_tersedia" &&
                                    "Rekonsiliasi tidak bisa dihitung untuk import ini"}
                            </p>

                            {rekon?.total && (
                                <p>
                                    Pegawai dibaca dari file: {rekon.total.file} &middot;
                                    tersimpan di database: {rekon.total.db}
                                </p>
                            )}

                            {rekon?.ganda_dalam_file > 0 && (
                                <p className="mt-1">
                                    {rekon.ganda_dalam_file} baris di file saling menimpa
                                    (NIP/NIK sama muncul lebih dari sekali).
                                </p>
                            )}

                            {rekonSelisih.length > 0 && (
                                <ul className="mt-2 list-disc pl-5 space-y-0.5">
                                    {rekonSelisih.slice(0, 10).map((r, i) => (
                                        <li key={i}>
                                            {r.kategori} &ndash; {r.nilai}: file {r.file},
                                            database {r.db}
                                        </li>
                                    ))}
                                    {rekonSelisih.length > 10 && (
                                        <li>
                                            ...dan {rekonSelisih.length - 10} selisih lainnya
                                        </li>
                                    )}
                                </ul>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {gagal > 0 && (
                <p className="text-gray-600 text-sm mb-6">
                    Ada {gagal} baris yang gagal diimport. Lihat detail di halaman
                    Riwayat Import &rarr; batch #{result?.id}.
                </p>
            )}

            <button
                onClick={onRestart}
                className="px-5 py-3 bg-[#006A4E] text-white rounded-lg"
            >
                Import File Lain
            </button>

        </div>
    );
}

export default ImportResult;