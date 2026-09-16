import { Fragment } from "react";
import { LoaderCircle } from "lucide-react";
import { ErrorBox } from "./StatUi";

/**
 * Tabel rekap "satu baris per Kelurahan, dikelompokkan per Kemantren",
 * dipakai oleh panel statistik PNS Kelurahan & PPPK Kelurahan. Layout
 * kolom (No, Kemantren, Kelurahan, L, P, Jumlah) serta pengelompokan
 * per Kemantren dengan baris subtotal "Jumlah Kemantren ..." mengikuti
 * persis PnsKelurahanExport.php / PppkKelurahanExport.php, sedangkan
 * gaya visualnya mengikuti RekapGolonganTable di halaman Rekapitulasi
 * ASN Admin dan StrukturalPanel (Pejabat Struktural): kartu putih
 * `p-6 rounded-xl shadow`, tabel bergaris `border-gray-200`, header
 * `bg-gray-50`, baris subtotal/Total `bg-gray-100`, plus sticky kolom
 * kiri & overflow-x-auto.
 *
 * Props:
 * - title, subtitle: judul & keterangan di atas tabel
 * - kemantrenKelurahanMap: { [namaKemantren]: [namaKelurahan, ...] },
 *   urutan sesuai peta wilayah di StatistikService
 * - data: hasil apa adanya dari statistikPns/PppkKelurahan(), yaitu
 *   { jumlah_..._kelurahan, kemantren: { [nama]: { total, kelurahan,
 *   kelurahan_gender } }, tidak_dikenali }
 * - jumlahKey: nama key jumlah total pada data (mis.
 *   "jumlah_pns_kelurahan" / "jumlah_pppk_kelurahan")
 * - rowLabelSuffix: label jenis pegawai untuk baris "Jumlah <suffix>"
 *   (mis. "PNS" / "PPPK")
 * - loading, error: status pemuatan data
 */
function KelurahanRekapTable({
    title,
    subtitle,
    kemantrenKelurahanMap,
    data,
    jumlahKey,
    rowLabelSuffix = "",
    loading,
    error,
}) {
    const kemantrenNames = Object.keys(kemantrenKelurahanMap);
    const belumAdaData = !data;

    // Judul rapi ("TEGALREJO" -> "Tegalrejo").
    function toTitleCase(text) {
        return text
            .toLowerCase()
            .replace(/(^|\s)\S/g, (c) => c.toUpperCase());
    }

    // Susun baris tabel: tiap Kemantren menyumbang N baris Kelurahan +
    // 1 baris subtotal "Jumlah Kemantren ..." — sama seperti buildRows()
    // di PnsKelurahanExport.php.
    const groups = kemantrenNames.map((nama) => {
        const detail = data?.kemantren?.[nama] || { total: 0, kelurahan: {}, kelurahan_gender: {} };
        const daftarKelurahan = kemantrenKelurahanMap[nama];

        let totalLaki = 0;
        let totalPerempuan = 0;

        const baris = daftarKelurahan.map((kel) => {
            const laki = detail.kelurahan_gender?.[kel]?.laki_laki || 0;
            const perempuan = detail.kelurahan_gender?.[kel]?.perempuan || 0;
            const jumlah = detail.kelurahan?.[kel] || 0;

            totalLaki += laki;
            totalPerempuan += perempuan;

            return { label: toTitleCase(kel), laki, perempuan, jumlah };
        });

        return {
            nama,
            label: toTitleCase(nama),
            baris,
            totalLaki,
            totalPerempuan,
            total: detail.total || 0,
        };
    });

    const grandLaki = groups.reduce((acc, g) => acc + g.totalLaki, 0);
    const grandPerempuan = groups.reduce((acc, g) => acc + g.totalPerempuan, 0);
    const grandTotal = data?.[jumlahKey] || 0;

    let nomor = 0;

    return (
        <div className="bg-white p-6 rounded-xl shadow mb-6">
            <div className="mb-5">
                <h3 className="text-lg font-bold text-[#172033]">{title}</h3>
                {subtitle && (
                    <p className="text-sm text-gray-500">{subtitle}</p>
                )}
            </div>

            {error && <ErrorBox message={error} />}

            {loading && belumAdaData && !error ? (
                <div className="flex items-center justify-center gap-2 text-gray-500 py-12 text-sm">
                    <LoaderCircle className="animate-spin" size={18} />
                    Memuat data...
                </div>
            ) : belumAdaData && !error ? (
                <div className="text-center text-gray-400 py-12 text-sm">
                    Tidak ada data untuk ditampilkan.
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm border border-gray-200">
                        <thead>
                            <tr className="bg-gray-50">
                                <th className="border px-3 py-2 text-left sticky left-0 z-20 bg-gray-50 w-12">
                                    No
                                </th>
                                <th className="border px-3 py-2 text-left sticky left-12 z-20 bg-gray-50 w-40 max-w-[10rem]">
                                    Kemantren
                                </th>
                                <th className="border px-3 py-2 text-left sticky left-[13rem] z-20 bg-gray-50 w-40 max-w-[10rem]">
                                    Kelurahan
                                </th>
                                <th className="border px-2 py-2 text-center whitespace-nowrap">
                                    L
                                </th>
                                <th className="border px-2 py-2 text-center whitespace-nowrap">
                                    P
                                </th>
                                <th className="border px-2 py-2 text-center whitespace-nowrap">
                                    Jumlah{rowLabelSuffix ? ` ${rowLabelSuffix}` : ""}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {groups.map((g) => (
                                <Fragment key={g.nama}>
                                    {g.baris.map((row) => {
                                        nomor += 1;
                                        return (
                                            <tr key={`${g.nama}-${row.label}`} className="hover:bg-gray-50">
                                                <td className="border px-3 py-2 sticky left-0 z-10 bg-white w-12">
                                                    {nomor}
                                                </td>
                                                <td
                                                    className="border px-3 py-2 sticky left-12 z-10 bg-white w-40 max-w-[10rem] whitespace-normal break-words align-top"
                                                    title={g.label}
                                                >
                                                    {g.label}
                                                </td>
                                                <td
                                                    className="border px-3 py-2 sticky left-[13rem] z-10 bg-white w-40 max-w-[10rem] whitespace-normal break-words align-top"
                                                    title={row.label}
                                                >
                                                    {row.label}
                                                </td>
                                                <td className="border px-2 py-2 text-center">
                                                    {row.laki}
                                                </td>
                                                <td className="border px-2 py-2 text-center">
                                                    {row.perempuan}
                                                </td>
                                                <td className="border px-2 py-2 text-center font-medium">
                                                    {row.jumlah}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    <tr key={`${g.nama}-subtotal`} className="bg-gray-100 font-semibold">
                                        <td className="border px-3 py-2 sticky left-0 z-10 bg-gray-100" />
                                        <td
                                            colSpan={2}
                                            className="border px-3 py-2 sticky left-12 z-10 bg-gray-100"
                                        >
                                            Jumlah Kemantren {g.label}
                                        </td>
                                        <td className="border px-2 py-2 text-center">
                                            {g.totalLaki}
                                        </td>
                                        <td className="border px-2 py-2 text-center">
                                            {g.totalPerempuan}
                                        </td>
                                        <td className="border px-2 py-2 text-center">
                                            {g.total}
                                        </td>
                                    </tr>
                                </Fragment>
                            ))}
                            {(data?.tidak_dikenali || 0) > 0 && (
                                <tr className="bg-[#FFF8E1]">
                                    <td className="border px-3 py-2 sticky left-0 z-10 bg-[#FFF8E1]" />
                                    <td
                                        colSpan={4}
                                        className="border px-3 py-2 sticky left-12 z-10 bg-[#FFF8E1]"
                                    >
                                        Tidak Dikenali
                                    </td>
                                    <td className="border px-2 py-2 text-center">
                                        {data.tidak_dikenali}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                        <tfoot>
                            <tr className="bg-gray-100 font-bold">
                                <td colSpan={3} className="border px-3 py-2 sticky left-0 z-10 bg-gray-100">
                                    Total
                                </td>
                                <td className="border px-2 py-2 text-center">
                                    {grandLaki}
                                </td>
                                <td className="border px-2 py-2 text-center">
                                    {grandPerempuan}
                                </td>
                                <td className="border px-2 py-2 text-center">
                                    {grandTotal}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}
        </div>
    );
}

export default KelurahanRekapTable;