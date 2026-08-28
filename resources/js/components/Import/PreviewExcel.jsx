import { formatPeriodeLabel } from "../../lib/periode";

function PreviewExcel({
    headers,
    preview,
    periode,
    next,
    back
}) {

    return (
        <div>

            <div className="bg-white rounded-xl shadow p-8 mb-6">

                <h2 className="text-xl font-bold mb-2">
                    Periode Data
                </h2>

                <p className="text-gray-600 mb-4">
                    Data yang diimport akan otomatis tercatat untuk periode
                    berjalan berikut.
                </p>

                <div className="inline-block border rounded p-3 bg-gray-50 font-semibold text-gray-800">
                    {formatPeriodeLabel(periode)}
                </div>

            </div>

            <h2 className="text-2xl font-bold mb-5">
                Preview Data Excel
            </h2>

            <div className="overflow-x-auto border">
                <table className="min-w-max border-collapse">
                    <thead>
                        <tr>
                            {headers.map((header, index) => (
                                <th
                                    key={index}
                                    className="border px-4 py-3 bg-gray-100 font-bold whitespace-nowrap"
                                >
                                    {header || "-"}
                                </th>
                            ))}
                        </tr>
                    </thead>

                    <tbody>
                        {preview.map((row, rowIndex) => (
                            <tr key={rowIndex}>
                                {headers.map((_, colIndex) => (
                                    <td
                                        key={colIndex}
                                        className="border px-4 py-2 whitespace-nowrap"
                                    >
                                        {row[colIndex] ?? "-"}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
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
                    className="px-5 py-2 bg-[#006A4E] text-white rounded"
                >
                    Lanjut Import
                </button>
            </div>

        </div>
    )
}

export default PreviewExcel;