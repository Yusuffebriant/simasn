import { CheckCircle2, XCircle } from "lucide-react";

function ImportResult({ result, onRestart }) {

    const total = result?.total_baris ?? 0;
    const berhasil = result?.berhasil ?? 0;
    const gagal = result?.gagal ?? 0;
    const gagalTotal = result?.status === "gagal";

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
