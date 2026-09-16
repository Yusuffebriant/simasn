import { Fragment } from "react";
import { LoaderCircle } from "lucide-react";
import { ErrorBox } from "./StatUi";

/**
 * Tabel rekap "baris Kemantren x kolom kategori (Laki-laki/Perempuan)",
 * dipakai oleh panel statistik PNS/PPPK Kemantren (Golongan & Pendidikan).
 * Bentuk & gaya tabel mengikuti persis RekapKategoriTable /
 * RekapGolonganTable di resources/js/components/Rekap (halaman
 * Rekapitulasi ASN Admin) dan StrukturalPanel (Pejabat Struktural):
 * kartu putih `p-6 rounded-xl shadow`, tabel bergaris `border-gray-200`,
 * header 2 baris `bg-gray-50` (kategori lalu L/P), kolom Jumlah
 * L/P/Total di kanan, dan baris Total `bg-gray-100 font-bold` di tfoot.
 * Kolom kategori bisa banyak (mis. golongan ruang I/a s.d. IV/e), jadi
 * tabel dibungkus overflow-x-auto dan kolom No + label baris dibuat
 * sticky supaya tetap kelihatan saat digeser — sama seperti
 * RekapGolonganTable.
 *
 * Props:
 * - title, subtitle: judul & keterangan di atas tabel
 * - categories: daftar kolom kategori { key, label }, urutan sesuai
 *   kolom yang ditampilkan
 * - rows: array baris { label, laki_laki: {kategori: n}, perempuan: {kategori: n},
 *   jumlah_laki_laki, jumlah_perempuan, jumlah_total }, satu baris per Kemantren
 * - rowHeaderLabel: teks header kolom label baris (default "Kemantren")
 * - loading, error: status pemuatan data
 */
function KemantrenRekapTable({
    title,
    subtitle,
    categories,
    rows,
    rowHeaderLabel = "Kemantren",
    loading,
    error,
}) {
    // Total per kolom kategori & jumlah keseluruhan, dijumlah dari semua
    // baris Kemantren (baris terakhir tabel) — sama seperti totalPria/
    // totalWanita di RekapKategoriTable.
    const totalLakiLaki = Object.fromEntries(categories.map((c) => [c.key, 0]));
    const totalPerempuan = Object.fromEntries(categories.map((c) => [c.key, 0]));
    let totalJumlahLakiLaki = 0;
    let totalJumlahPerempuan = 0;
    let totalJumlahTotal = 0;

    rows.forEach((row) => {
        categories.forEach((c) => {
            totalLakiLaki[c.key] += row.laki_laki?.[c.key] || 0;
            totalPerempuan[c.key] += row.perempuan?.[c.key] || 0;
        });
        totalJumlahLakiLaki += row.jumlah_laki_laki || 0;
        totalJumlahPerempuan += row.jumlah_perempuan || 0;
        totalJumlahTotal += row.jumlah_total || 0;
    });

    return (
        <div className="bg-white p-6 rounded-xl shadow mb-6">
            <div className="mb-5">
                <h3 className="text-lg font-bold text-[#172033]">{title}</h3>
                {subtitle && (
                    <p className="text-sm text-gray-500">{subtitle}</p>
                )}
            </div>

            {error && <ErrorBox message={error} />}

            {loading && rows.length === 0 && !error ? (
                <div className="flex items-center justify-center gap-2 text-gray-500 py-12 text-sm">
                    <LoaderCircle className="animate-spin" size={18} />
                    Memuat data...
                </div>
            ) : rows.length === 0 && !error ? (
                <div className="text-center text-gray-400 py-12 text-sm">
                    Tidak ada data untuk ditampilkan.
                </div>
            ) : rows.length > 0 ? (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm border border-gray-200">
                        <thead>
                            <tr className="bg-gray-50">
                                <th rowSpan={2} className="border px-3 py-2 text-left align-bottom sticky left-0 z-20 bg-gray-50 w-12">
                                    No
                                </th>
                                <th rowSpan={2} className="border px-3 py-2 text-left align-bottom sticky left-12 z-20 bg-gray-50 w-48 max-w-[12rem]">
                                    {rowHeaderLabel}
                                </th>
                                {categories.map((c) => (
                                    <th key={c.key} colSpan={2} className="border px-3 py-2 text-center whitespace-nowrap">
                                        {c.label}
                                    </th>
                                ))}
                                <th colSpan={3} className="border px-3 py-2 text-center">
                                    Jumlah
                                </th>
                            </tr>
                            <tr className="bg-gray-50">
                                {categories.map((c) => (
                                    <Fragment key={c.key}>
                                        <th className="border px-2 py-1.5 text-center font-normal">L</th>
                                        <th className="border px-2 py-1.5 text-center font-normal">P</th>
                                    </Fragment>
                                ))}
                                <th className="border px-2 py-1.5 text-center font-normal">L</th>
                                <th className="border px-2 py-1.5 text-center font-normal">P</th>
                                <th className="border px-2 py-1.5 text-center font-normal">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, i) => (
                                <tr key={row.label} className="hover:bg-gray-50">
                                    <td className="border px-3 py-2 sticky left-0 z-10 bg-white w-12">{i + 1}</td>
                                    <td
                                        className="border px-3 py-2 sticky left-12 z-10 bg-white w-48 max-w-[12rem] whitespace-normal break-words align-top"
                                        title={row.label}
                                    >
                                        {row.label}
                                    </td>
                                    {categories.map((c) => (
                                        <Fragment key={c.key}>
                                            <td className="border px-2 py-2 text-center">
                                                {row.laki_laki?.[c.key] || 0}
                                            </td>
                                            <td className="border px-2 py-2 text-center">
                                                {row.perempuan?.[c.key] || 0}
                                            </td>
                                        </Fragment>
                                    ))}
                                    <td className="border px-2 py-2 text-center font-medium">
                                        {row.jumlah_laki_laki || 0}
                                    </td>
                                    <td className="border px-2 py-2 text-center font-medium">
                                        {row.jumlah_perempuan || 0}
                                    </td>
                                    <td className="border px-2 py-2 text-center font-bold">
                                        {row.jumlah_total || 0}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-gray-100 font-bold">
                                <td colSpan={2} className="border px-3 py-2 sticky left-0 z-10 bg-gray-100">
                                    Total
                                </td>
                                {categories.map((c) => (
                                    <Fragment key={c.key}>
                                        <td className="border px-2 py-2 text-center">
                                            {totalLakiLaki[c.key]}
                                        </td>
                                        <td className="border px-2 py-2 text-center">
                                            {totalPerempuan[c.key]}
                                        </td>
                                    </Fragment>
                                ))}
                                <td className="border px-2 py-2 text-center">
                                    {totalJumlahLakiLaki}
                                </td>
                                <td className="border px-2 py-2 text-center">
                                    {totalJumlahPerempuan}
                                </td>
                                <td className="border px-2 py-2 text-center">
                                    {totalJumlahTotal}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            ) : null}
        </div>
    );
}

export default KemantrenRekapTable;