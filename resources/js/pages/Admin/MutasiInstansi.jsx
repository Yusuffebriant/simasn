import { useEffect, useMemo, useState } from "react";
import { LoaderCircle, AlertTriangle } from "lucide-react";
import { apiFetch } from "../../lib/api";
import { BULAN_NAMA, formatPeriodeLabel } from "../../lib/periode";

const fmt = (n) => (n === null || n === undefined ? "–" : Number(n).toLocaleString("id-ID"));
const fmtSel = (n) => (n > 0 ? `+${fmt(n)}` : n < 0 ? `−${fmt(Math.abs(n))}` : "0");
const warnaSel = (n) => (n > 0 ? "text-green-700" : n < 0 ? "text-red-600" : "text-gray-400");

/** Kalimat awam: "bertambah 3", "berkurang 3", atau "tetap". */
function kataPerubahan(awal, akhir) {
    const d = akhir - awal;
    if (d > 0) return `bertambah ${fmt(d)}`;
    if (d < 0) return `berkurang ${fmt(Math.abs(d))}`;
    return "tetap";
}

function SelJumlah({ jumlah, mutasi, tebal }) {
    if (jumlah === null || jumlah === undefined) {
        return <td className="px-3 py-2 text-right text-gray-300">–</td>;
    }
    const d = mutasi ? mutasi.akhir - mutasi.awal : null;
    return (
        <td className={`px-3 py-2 text-right tabular-nums ${tebal ? "font-bold" : ""}`}>
            <div>{fmt(jumlah)}</div>
            {d !== null && d !== 0 && (
                <div className={`text-[11px] font-semibold ${warnaSel(d)}`}>{fmtSel(d)}</div>
            )}
        </td>
    );
}

function Baris({ warna, children }) {
    return <li className={`text-sm ${warna}`}>{children}</li>;
}

/** Penjelasan satu bulan untuk satu instansi, atau untuk total semua instansi. */
function KartuBulan({ periode, m, untukTotal, rincian, terpotong, nama }) {
    const [buka, setBuka] = useState(false);

    const daftarNama = useMemo(() => {
        const semua = rincian ?? [];
        if (untukTotal) return semua;
        return semua.filter((r) => r.dari === nama || r.ke === nama);
    }, [rincian, untukTotal, nama]);

    const masuk = untukTotal ? [] : m.masuk;
    const keluar = untukTotal ? [] : m.keluar;
    const baris = [];

    if (untukTotal && m.pindah > 0) {
        baris.push(
            <Baris key="pindah" warna="text-gray-700">
                Pindah antar instansi: <b>{fmt(m.pindah)}</b> orang (hanya berpindah tempat, tidak
                mengubah total)
            </Baris>
        );
    }
    masuk.forEach((x) =>
        baris.push(
            <Baris key={`m-${x.dari}`} warna="text-green-700">
                Pindah <b>masuk</b> dari {x.dari}: <b>{fmt(x.jumlah)}</b> orang
            </Baris>
        )
    );
    keluar.forEach((x) =>
        baris.push(
            <Baris key={`k-${x.ke}`} warna="text-red-600">
                Pindah <b>keluar</b> ke {x.ke}: <b>{fmt(x.jumlah)}</b> orang
            </Baris>
        )
    );
    if (m.baru > 0) {
        baris.push(
            <Baris key="baru" warna="text-green-700">
                Pegawai <b>baru</b> (baru muncul di data atau baru berstatus ASN aktif):{" "}
                <b>{fmt(m.baru)}</b> orang
            </Baris>
        );
    }
    m.berhenti.forEach((x) =>
        baris.push(
            <Baris key={`b-${x.alasan}`} warna="text-red-600">
                <b>Berhenti</b> — {x.alasan}: <b>{fmt(x.jumlah)}</b> orang
            </Baris>
        )
    );
    if (m.tak_terlacak !== 0) {
        baris.push(
            <Baris key="tak" warna="text-amber-700">
                Selisih yang tidak bisa ditelusuri per orang (mis. NIP kosong atau ganda):{" "}
                <b>{fmtSel(m.tak_terlacak)}</b>
            </Baris>
        );
    }

    return (
        <div className="border rounded-lg p-4 bg-white">
            <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <div className="font-semibold text-gray-800">{formatPeriodeLabel(periode)}</div>
                <div className="text-sm text-gray-700">
                    {fmt(m.awal)} → <b>{fmt(m.akhir)}</b>{" "}
                    <span className={warnaSel(m.akhir - m.awal)}>
                        ({kataPerubahan(m.awal, m.akhir)})
                    </span>
                </div>
            </div>
            <div className="text-xs text-gray-500 mb-2">
                Dibanding {formatPeriodeLabel(m.sejak)}
                {m.bulan_terlewati > 0 ? " (bulan di antaranya belum ada datanya)" : ""}
            </div>

            {baris.length === 0 ? (
                <p className="text-sm text-gray-500">Tidak ada perubahan.</p>
            ) : (
                <ul className="list-disc pl-5 space-y-0.5">{baris}</ul>
            )}

            {daftarNama.length > 0 && (
                <div className="mt-3">
                    <button
                        onClick={() => setBuka(!buka)}
                        className="text-xs font-semibold text-[#006A4E] hover:underline"
                    >
                        {buka ? "▾ Sembunyikan nama pegawai" : `▸ Lihat nama pegawai (${fmt(daftarNama.length)})`}
                    </button>

                    {buka && (
                        <div className="mt-2 overflow-x-auto border rounded">
                            <table className="w-full text-xs">
                                <thead className="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th className="px-2 py-1.5 text-left font-semibold">Nama</th>
                                        <th className="px-2 py-1.5 text-left font-semibold">NIP</th>
                                        <th className="px-2 py-1.5 text-left font-semibold">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {daftarNama.map((r, i) => (
                                        <tr key={`${r.nip ?? r.nama}-${i}`}>
                                            <td className="px-2 py-1.5">{r.nama}</td>
                                            <td className="px-2 py-1.5 tabular-nums">{r.nip ?? "–"}</td>
                                            <td className="px-2 py-1.5">
                                                {r.jenis === "pindah" && (
                                                    <span className="text-gray-700">
                                                        Pindah dari <b>{r.dari}</b> ke <b>{r.ke}</b>
                                                    </span>
                                                )}
                                                {r.jenis === "baru" && (
                                                    <span className="text-green-700">
                                                        Baru di <b>{r.ke}</b> — {r.alasan}
                                                    </span>
                                                )}
                                                {r.jenis === "berhenti" && (
                                                    <span className="text-red-600">
                                                        Dari <b>{r.dari}</b> — {r.alasan}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {terpotong && (
                                <p className="text-xs text-amber-700 p-2">
                                    Daftar nama dipotong karena terlalu banyak. Angka di atas tetap lengkap.
                                </p>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export default function MutasiInstansi({ periodeOpsi }) {
    const opsi = useMemo(() => periodeOpsi.filter((p) => p.snapshot_individual), [periodeOpsi]);

    const [awal, setAwal] = useState("");
    const [akhir, setAkhir] = useState("");
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const [terpilih, setTerpilih] = useState(null); // null = semua instansi (total)

    // Bawaan: enam bulan terakhir yang punya data individual.
    useEffect(() => {
        if (awal || akhir || opsi.length === 0) return;
        setAwal(opsi[Math.max(0, opsi.length - 6)].periode);
        setAkhir(opsi[opsi.length - 1].periode);
    }, [opsi, awal, akhir]);

    const rentangSah = awal && akhir && akhir >= awal;

    useEffect(() => {
        if (!rentangSah) return undefined;
        let batal = false;

        async function muat() {
            setLoading(true);
            setError("");
            try {
                const res = await apiFetch(
                    `/indeks-kumulatif/instansi?awal=${encodeURIComponent(awal)}&akhir=${encodeURIComponent(akhir)}`
                );
                if (!res.ok) {
                    let pesan = "Gagal memuat data perpindahan per instansi.";
                    try {
                        const j = await res.json();
                        pesan = j.message || Object.values(j.errors ?? {})[0]?.[0] || pesan;
                    } catch {
                        // respons bukan JSON, pakai pesan bawaan
                    }
                    throw new Error(pesan);
                }
                const json = await res.json();
                if (!batal) {
                    setData(json);
                    setTerpilih(null);
                }
            } catch (err) {
                if (!batal) {
                    setError(err.message || "Gagal memuat data perpindahan per instansi.");
                    setData(null);
                }
            } finally {
                if (!batal) setLoading(false);
            }
        }

        muat();
        return () => {
            batal = true;
        };
    }, [awal, akhir, rentangSah]);

    const bulan = data?.bulan ?? [];
    const lintasTahun = new Set(bulan.map((b) => b.periode.slice(0, 4))).size > 1;
    const labelBulan = (periode) =>
        BULAN_NAMA[parseInt(periode.slice(5, 7), 10) - 1].slice(0, 3) +
        (lintasTahun ? ` ${periode.slice(2, 4)}` : "");
    const bulanTanpaData = bulan.filter((b) => !b.tersedia);

    const instansiTerpilih = terpilih === null ? null : data?.instansi?.find((x) => x.nama === terpilih);
    const sumberMutasi = terpilih === null ? data?.total?.mutasi : instansiTerpilih?.mutasi;

    return (
        <div>
            <p className="text-sm text-gray-600 mb-4">
                Tab ini menjawab dua pertanyaan: <b>berapa jumlah pegawai di tiap instansi setiap bulan</b>, dan{" "}
                <b>kenapa jumlahnya berubah</b> (pindah ke/dari instansi mana, pegawai baru, atau
                berhenti). Yang dihitung: ASN aktif (PNS + PPPK).
            </p>

            <div className="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">Dari bulan</label>
                    <select
                        value={awal}
                        onChange={(e) => setAwal(e.target.value)}
                        className="border p-2 rounded text-sm bg-white text-gray-700 min-w-[9rem]"
                        aria-label="Dari bulan"
                    >
                        {opsi.map((p) => (
                            <option key={p.periode} value={p.periode}>
                                {formatPeriodeLabel(p.periode)}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-700 mb-1">Sampai bulan</label>
                    <select
                        value={akhir}
                        onChange={(e) => setAkhir(e.target.value)}
                        className="border p-2 rounded text-sm bg-white text-gray-700 min-w-[9rem]"
                        aria-label="Sampai bulan"
                    >
                        {opsi.map((p) => (
                            <option key={p.periode} value={p.periode}>
                                {formatPeriodeLabel(p.periode)}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {opsi.length === 0 && (
                <div className="flex items-start gap-3 text-amber-800 bg-amber-50 p-3 rounded-lg text-sm mb-4">
                    <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                    <span>
                        Belum ada data individual pegawai per bulan. Data ini tersimpan otomatis setiap kali
                        data bulanan diupload, jadi perpindahan baru bisa terlihat mulai upload berikutnya.
                    </span>
                </div>
            )}

            {awal && akhir && akhir < awal && (
                <div className="flex items-start gap-3 text-amber-800 bg-amber-50 p-3 rounded-lg text-sm mb-4">
                    <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                    <span>“Sampai bulan” tidak boleh sebelum “Dari bulan”.</span>
                </div>
            )}

            {error && (
                <div className="flex items-center gap-3 text-red-700 bg-red-50 p-3 rounded-lg text-sm mb-4">
                    <AlertTriangle size={16} />
                    {error}
                </div>
            )}

            {bulanTanpaData.length > 0 && (
                <div className="flex items-start gap-3 text-amber-800 bg-amber-50 p-3 rounded-lg text-sm mb-4">
                    <AlertTriangle size={16} className="mt-0.5 shrink-0" />
                    <span>
                        Data pegawai per orang belum ada untuk {bulanTanpaData.map((b) => formatPeriodeLabel(b.periode)).join(", ")}
                        . Bulan itu ditulis “–” (bukan 0) dan dilewati; perubahan dihitung terhadap bulan
                        sebelumnya yang ada datanya.
                    </span>
                </div>
            )}

            {loading ? (
                <div className="flex items-center justify-center gap-2 text-gray-500 py-12 text-sm">
                    <LoaderCircle className="animate-spin" size={18} />
                    Memuat data...
                </div>
            ) : data && !data.ada_data ? (
                <p className="text-sm text-gray-500 py-8 text-center">
                    Belum ada data pegawai per orang pada rentang bulan ini.
                </p>
            ) : data ? (
                <>
                    <h3 className="font-semibold text-gray-800 mb-1">1. Jumlah pegawai per instansi</h3>
                    <p className="text-xs text-gray-500 mb-2">
                        Angka besar = jumlah pegawai bulan itu. Angka kecil berwarna di bawahnya = bertambah (+)
                        atau berkurang (−) dibanding bulan sebelumnya. Klik nama instansi untuk melihat
                        penjelasannya di bagian 2.
                    </p>

                    <div className="overflow-x-auto border rounded-lg mb-6">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 text-gray-600">
                                <tr>
                                    <th className="sticky left-0 bg-gray-50 px-3 py-2 text-left font-semibold min-w-[14rem]">
                                        Instansi
                                    </th>
                                    {bulan.map((b) => (
                                        <th
                                            key={b.periode}
                                            className="px-3 py-2 text-right font-semibold whitespace-nowrap"
                                            title={b.tersedia ? formatPeriodeLabel(b.periode) : "Data pegawai bulan ini belum ada"}
                                        >
                                            {labelBulan(b.periode)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {data.instansi.map((x) => {
                                    const aktif = terpilih === x.nama;
                                    return (
                                        <tr key={x.nama} className={aktif ? "bg-[#E7F1FB]" : "hover:bg-gray-50"}>
                                            <td className={`sticky left-0 px-3 py-2 font-medium ${aktif ? "bg-[#E7F1FB]" : "bg-white"}`}>
                                                <button
                                                    onClick={() => setTerpilih(aktif ? null : x.nama)}
                                                    className="text-left text-gray-700 hover:text-[#006A4E] hover:underline"
                                                >
                                                    {x.nama}
                                                </button>
                                            </td>
                                            {x.jumlah.map((j, i) => (
                                                <SelJumlah key={bulan[i].periode} jumlah={j} mutasi={x.mutasi[i]} />
                                            ))}
                                        </tr>
                                    );
                                })}
                                <tr className={terpilih === null ? "bg-[#E7F1FB]" : "bg-gray-50"}>
                                    <td className={`sticky left-0 px-3 py-2 font-bold ${terpilih === null ? "bg-[#E7F1FB]" : "bg-gray-50"}`}>
                                        <button
                                            onClick={() => setTerpilih(null)}
                                            className="text-left hover:text-[#006A4E] hover:underline"
                                        >
                                            Total semua instansi
                                        </button>
                                    </td>
                                    {data.total.jumlah.map((j, i) => (
                                        <SelJumlah key={bulan[i].periode} jumlah={j} mutasi={data.total.mutasi[i]} tebal />
                                    ))}
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h3 className="font-semibold text-gray-800 mb-1">
                        2. Penjelasan perubahan: {terpilih === null ? "seluruh instansi (total)" : terpilih}
                    </h3>
                    <p className="text-xs text-gray-500 mb-3">
                        {terpilih === null
                            ? "Pegawai yang pindah antar instansi hanya berpindah tempat, jadi tidak mengubah total. Yang mengubah total hanya pegawai baru dan yang berhenti."
                            : "Rumus: jumlah bulan ini = jumlah bulan lalu + pindah masuk − pindah keluar + pegawai baru − berhenti."}
                    </p>

                    {!sumberMutasi || sumberMutasi.every((m) => m === null) ? (
                        <p className="text-sm text-gray-500 mb-4">
                            Belum ada pasangan bulan yang bisa dibandingkan. Pilih rentang minimal dua bulan yang
                            sama-sama punya data pegawai per orang.
                        </p>
                    ) : (
                        <div className="space-y-3 mb-6">
                            {bulan.map((b, i) => {
                                const m = sumberMutasi[i];
                                if (!m) return null;
                                return (
                                    <KartuBulan
                                        key={`${terpilih}-${b.periode}`}
                                        periode={b.periode}
                                        m={m}
                                        untukTotal={terpilih === null}
                                        nama={terpilih}
                                        rincian={data.rincian?.[b.periode]}
                                        terpotong={!!data.rincian_terpotong?.[b.periode]}
                                    />
                                );
                            })}
                        </div>
                    )}
                </>
            ) : null}

            <details open className="mt-5 border rounded-lg bg-gray-50 p-4 text-sm text-gray-700">
                <summary className="font-semibold cursor-pointer">Keterangan: cara membaca tab ini</summary>
                <div className="mt-3 space-y-2">
                    <p>
                        <b>Contoh.</b> Januari, Dinas A punya 30 pegawai. Februari, 3 pegawai pindah ke Dinas B.
                        Maka Dinas A menjadi 27 (penjelasannya: “pindah keluar ke Dinas B: 3 orang”), Dinas B
                        bertambah 3 (“pindah masuk dari Dinas A: 3 orang”), dan <b>total semua instansi tidak
                            berubah</b>.
                    </p>
                    <p>
                        Pegawai dicocokkan antar bulan memakai NIP (atau NIK bila NIP tidak ada). Pegawai tanpa
                        NIP/NIK atau dengan NIP ganda tidak bisa ditelusuri per orang; selisihnya ditulis
                        terpisah sebagai “tidak bisa ditelusuri” supaya hitungan tetap pas.
                    </p>
                    <p>
                        Data per orang tersimpan otomatis setiap kali data bulanan diupload. Bulan yang diupload
                        sebelum fitur ini ada tidak punya data per orang, sehingga perpindahannya tidak bisa
                        dikarang dan ditampilkan “–”.
                    </p>
                </div>
            </details>
        </div>
    );
}