/**
 * Daftar perangkat daerah yang datanya belum lengkap.
 *
 * Dipakai di banner peringatan (halaman Admin) dan di hasil import, supaya
 * admin cukup mengecek perangkat daerah yang kurang saja, tidak perlu
 * menyisir seluruh Daftar Pegawai satu per satu.
 *
 * Props:
 *  - daftar   : hasil KelengkapanPegawai::perInstansi() dari backend, yaitu
 *               [{ instansi_id, instansi, total_pegawai, belum_lengkap,
 *                  rincian: [{ kunci, label, jumlah }] }]
 *  - linkDasar: tautan Daftar Pegawai dengan filter "belum lengkap"
 *               (di hasil import ditambah &batch=ID).
 */
function KurangPerInstansi({ daftar, linkDasar = "/admin?tab=pegawai&kelengkapan=belum" }) {
    if (!daftar || daftar.length === 0) {
        return null;
    }

    // Pegawai tanpa perangkat daerah tidak bisa difilter lewat instansi_id,
    // jadi dicari lewat isian "Perangkat daerah" yang kosong.
    const hrefUntuk = (d) =>
        d.instansi_id
            ? `${linkDasar}&instansi_id=${d.instansi_id}`
            : `${linkDasar}&kosong=instansi`;

    return (
        <details className="mt-3" open={daftar.length <= 8}>
            <summary className="cursor-pointer font-semibold">
                Perangkat daerah yang perlu dilengkapi ({daftar.length})
            </summary>

            <p className="mt-1 text-xs opacity-80">
                Cukup cek perangkat daerah di bawah ini. Klik &quot;Lihat pegawai&quot;
                untuk membuka daftar pegawainya saja.
            </p>

            <div className="mt-2 max-h-80 overflow-auto rounded border border-gray-200 bg-white text-gray-800">
                <table className="w-full text-xs">
                    <thead className="sticky top-0 bg-gray-50 text-left">
                        <tr>
                            <th className="px-3 py-2">Perangkat daerah</th>
                            <th className="px-3 py-2 text-right whitespace-nowrap">
                                Belum lengkap
                            </th>
                            <th className="px-3 py-2">Isian yang kosong</th>
                            <th className="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        {daftar.map((d) => (
                            <tr
                                key={d.instansi_id ?? "tanpa-instansi"}
                                className="border-t border-gray-100 align-top"
                            >
                                <td className="px-3 py-2 font-medium">{d.instansi}</td>
                                <td className="px-3 py-2 text-right whitespace-nowrap">
                                    {d.belum_lengkap.toLocaleString("id-ID")}
                                    <span className="text-gray-500">
                                        {" "}
                                        / {d.total_pegawai.toLocaleString("id-ID")}
                                    </span>
                                </td>
                                <td className="px-3 py-2">
                                    {d.rincian
                                        .map((r) => `${r.label} (${r.jumlah.toLocaleString("id-ID")})`)
                                        .join(", ")}
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap">
                                    <a
                                        href={hrefUntuk(d)}
                                        className="font-semibold underline"
                                    >
                                        Lihat pegawai
                                    </a>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </details>
    );
}

export default KurangPerInstansi;