import { useEffect, useState } from "react";
import {
    LoaderCircle,
    AlertTriangle,
    Info,
    Search,
    UserPlus,
    UserMinus,
    RefreshCw,
} from "lucide-react";
import { apiFetch } from "../../lib/api";
import ExportExcelButton from "../Statistik/components/ExportExcelButton";

// Pengelompokan jenis perubahan untuk kartu ringkasan.
const KELOMPOK = {
    masuk: ["pegawai_baru"],
    keluar: [
        "pensiun",
        "kontrak_selesai",
        "meninggal",
        "mutasi_keluar",
        "keluar_lainnya",
    ],
    berubah: [
        "naik_pangkat",
        "ubah_golongan",
        "pindah_unit",
        "ganti_jabatan",
        "ganti_status",
        "ganti_pendidikan",
    ],
};

const PESAN_STATUS = {
    tanpa_pembanding:
        "Import bulan ini adalah import pertama (belum ada data sebelumnya), jadi tidak ada yang bisa dibandingkan. Perubahan mulai tercatat dari import bulan berikutnya.",
    gagal:
        "Catatan perubahan gagal dibuat untuk bulan ini. Data pegawai tetap tersimpan normal.",
};

function namaBulan(periode) {
    if (!periode) return "";
    const [tahun, bulan] = periode.split("-").map(Number);
    return new Date(tahun, bulan - 1, 1).toLocaleDateString("id-ID", {
        month: "long",
        year: "numeric",
    });
}

function namaBulanPendek(periode) {
    if (!periode) return "";
    const [tahun, bulan] = periode.split("-").map(Number);
    return new Date(tahun, bulan - 1, 1).toLocaleDateString("id-ID", {
        month: "short",
        year: "numeric",
    });
}

const angka = (n) => (n ?? 0).toLocaleString("id-ID");

function Kartu({ ikon: Ikon, judul, nilai, warna, keterangan }) {
    return (
        <div className="bg-white border rounded-xl p-5">
            <div className="flex items-center gap-2 text-sm text-gray-500">
                {Ikon && <Ikon size={16} className={warna} />}
                {judul}
            </div>
            <p className={`text-3xl font-bold mt-1 ${warna || ""}`}>{angka(nilai)}</p>
            {keterangan && (
                <p className="text-xs text-gray-500 mt-1">{keterangan}</p>
            )}
        </div>
    );
}

export default function PerubahanBulanan() {
    const [daftarPeriode, setDaftarPeriode] = useState(null);
    const [periode, setPeriode] = useState("");
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");
    const [jenis, setJenis] = useState("");
    const [cari, setCari] = useState("");
    const [q, setQ] = useState("");
    const [page, setPage] = useState(1);

    // Muat daftar bulan sekali; pilih bulan terbaru yang sudah ada catatannya.
    useEffect(() => {
        let batal = false;

        async function muat() {
            try {
                const res = await apiFetch("/perubahan-pegawai");
                if (!res.ok) throw new Error("Gagal memuat daftar bulan.");
                const json = await res.json();
                if (batal) return;

                const list = json.data ?? [];
                setDaftarPeriode(list);
                const awal = list.find((p) => p.status === "selesai") ?? list[0];
                if (awal) setPeriode(awal.periode);
            } catch (err) {
                if (!batal) {
                    setError(err.message);
                    setDaftarPeriode([]);
                }
            }
        }

        muat();
        return () => {
            batal = true;
        };
    }, []);

    // Pencarian nama/NIP: tunda sebentar supaya tidak request di tiap ketukan.
    useEffect(() => {
        const t = setTimeout(() => {
            setQ(cari.trim());
            setPage(1);
        }, 400);
        return () => clearTimeout(t);
    }, [cari]);

    // Muat catatan bulan terpilih.
    useEffect(() => {
        if (!periode) return;
        let batal = false;

        async function muat() {
            setLoading(true);
            setError("");
            try {
                const params = new URLSearchParams({ page });
                if (jenis) params.set("jenis", jenis);
                if (q) params.set("q", q);

                const res = await apiFetch(`/perubahan-pegawai/${periode}?${params}`);
                if (!res.ok) throw new Error("Gagal memuat catatan perubahan pegawai.");
                const json = await res.json();
                if (!batal) setData(json);
            } catch (err) {
                if (!batal) setError(err.message);
            } finally {
                if (!batal) setLoading(false);
            }
        }

        muat();
        return () => {
            batal = true;
        };
    }, [periode, jenis, q, page]);

    function pilihPeriode(nilai) {
        setPeriode(nilai);
        setData(null);
        setJenis("");
        setCari("");
        setQ("");
        setPage(1);
    }

    function pilihJenis(nilai) {
        setJenis(nilai);
        setPage(1);
    }

    if (daftarPeriode === null) {
        return (
            <div className="flex items-center gap-3 text-gray-600 text-sm">
                <LoaderCircle className="animate-spin" size={18} />
                Memuat...
            </div>
        );
    }

    if (daftarPeriode.length === 0) {
        return (
            <div className="bg-white rounded-xl shadow p-8">
                <h2 className="text-xl font-bold mb-2">Perubahan Pegawai</h2>
                {error ? (
                    <div className="flex items-center gap-3 text-red-700 bg-red-50 p-4 rounded-lg text-sm">
                        <AlertTriangle size={18} />
                        {error}
                    </div>
                ) : (
                    <div className="flex items-start gap-3 p-4 rounded-lg bg-gray-50 text-gray-700 text-sm">
                        <Info size={18} className="mt-0.5 shrink-0" />
                        Belum ada import yang selesai. Catatan perubahan pegawai
                        muncul di sini otomatis setelah import bulanan dijalankan.
                    </div>
                )}
            </div>
        );
    }

    const rows = data?.daftar?.data ?? [];
    const lastPage = data?.daftar?.last_page ?? 1;
    const aktif = data?.ringkasan?.filter((r) => r.jumlah > 0) ?? [];

    const jumlahKelompok = (kunci) =>
        (data?.ringkasan ?? [])
            .filter((r) => KELOMPOK[kunci].includes(r.jenis))
            .reduce((sum, r) => sum + r.jumlah, 0);

    return (
        <div className="bg-white rounded-xl shadow p-8">
            <div className="flex flex-wrap items-start justify-between gap-4 mb-6">
                <div>
                    <h2 className="text-xl font-bold">
                        Perubahan Pegawai
                        {periode && ` \u2014 ${namaBulan(periode)}`}
                    </h2>
                    <p className="text-sm text-gray-500 mt-1">
                        Dicatat otomatis setiap import bulanan, dibandingkan dengan
                        data sebelum import tersebut.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <label className="flex items-center gap-2 text-sm text-gray-600">
                        Bulan
                        <select
                            value={periode}
                            onChange={(e) => pilihPeriode(e.target.value)}
                            className="border rounded-lg px-3 py-2 text-sm bg-white"
                        >
                            {daftarPeriode.map((p) => (
                                <option key={p.periode} value={p.periode}>
                                    {namaBulan(p.periode)} &mdash;{" "}
                                    {p.status === "selesai"
                                        ? `${angka(p.jumlah_perubahan)} perubahan`
                                        : "tanpa catatan"}
                                </option>
                            ))}
                        </select>
                    </label>

                    <ExportExcelButton
                        path={`/perubahan-pegawai/${periode}/export`}
                        filename={`perubahan-pegawai-${periode}.xlsx`}
                        errorMessage="Gagal mengunduh daftar perubahan."
                        onError={(m) => m && setError(m)}
                        disabled={!data || data.total === 0}
                    />
                </div>
            </div>

            {error && (
                <div className="flex items-center gap-3 text-red-700 bg-red-50 p-4 rounded-lg text-sm mb-4">
                    <AlertTriangle size={18} />
                    {error}
                </div>
            )}

            {!data && !error && (
                <div className="flex items-center gap-3 text-gray-600 text-sm">
                    <LoaderCircle className="animate-spin" size={18} />
                    Memuat catatan perubahan...
                </div>
            )}

            {data && data.status !== "selesai" && (
                <div className="flex items-start gap-3 p-4 rounded-lg bg-gray-50 text-gray-700 text-sm">
                    <Info size={18} className="mt-0.5 shrink-0" />
                    {PESAN_STATUS[data.status] ||
                        "Import bulan ini dilakukan sebelum fitur catatan perubahan ada, jadi tidak ada catatannya."}
                </div>
            )}

            {data && data.status === "selesai" && (
                <>
                    <div className="flex flex-wrap items-center gap-2 mb-4 px-4 py-3 rounded-lg bg-blue-50 border border-blue-200 text-sm text-blue-900">
                        <Info size={16} className="shrink-0" />
                        {data.pembanding ? (
                            <span>
                                Perubahan ini adalah selisih data{" "}
                                <strong>{namaBulan(data.pembanding.periode)}</strong>
                                {" \u2192 "}
                                <strong>{namaBulan(data.periode)}</strong>. Pegawai
                                yang sudah tidak aktif sejak{" "}
                                {namaBulan(data.pembanding.periode)} tidak dihitung
                                lagi.
                            </span>
                        ) : (
                            <span>
                                Data bulan <strong>{namaBulan(data.periode)}</strong>.
                            </span>
                        )}
                    </div>

                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <Kartu
                            judul={`Total perubahan ${namaBulan(data.periode)}`}
                            nilai={data.total}
                            keterangan={
                                data.sebelumnya
                                    ? `${namaBulan(data.sebelumnya.periode)}: ${angka(data.sebelumnya.total)}`
                                    : null
                            }
                        />
                        <Kartu
                            ikon={UserPlus}
                            judul="Pegawai masuk"
                            nilai={jumlahKelompok("masuk")}
                            warna="text-[#006A4E]"
                        />
                        <Kartu
                            ikon={UserMinus}
                            judul="Pegawai keluar"
                            nilai={jumlahKelompok("keluar")}
                            warna="text-red-600"
                            keterangan="Pensiun, kontrak selesai, meninggal, mutasi, dll."
                        />
                        <Kartu
                            ikon={RefreshCw}
                            judul="Data berubah"
                            nilai={jumlahKelompok("berubah")}
                            warna="text-amber-600"
                            keterangan="Pangkat, unit, jabatan, status, pendidikan"
                        />
                    </div>

                    {data.jumlah_import > 1 && (
                        <p className="flex items-start gap-2 text-xs text-gray-500 mb-4">
                            <Info size={14} className="mt-0.5 shrink-0" />
                            Bulan ini diimport {data.jumlah_import} kali; catatan dari
                            semua import digabung.
                        </p>
                    )}

                    {data.total === 0 ? (
                        <p className="text-sm text-gray-600 p-4 bg-gray-50 rounded-lg">
                            Tidak ada perubahan: data pegawai sama dengan sebelum
                            import bulan ini.
                        </p>
                    ) : (
                        <>
                            <div className="flex flex-wrap gap-2 mb-4">
                                <button
                                    onClick={() => pilihJenis("")}
                                    className={`px-3 py-1 rounded-full border text-xs font-semibold ${jenis === "" ? "bg-[#006A4E] text-white border-[#006A4E]" : "bg-white hover:bg-gray-100"}`}
                                >
                                    Semua: {angka(data.total)}
                                </button>
                                {aktif.map((r) => (
                                    <button
                                        key={r.jenis}
                                        onClick={() => pilihJenis(r.jenis)}
                                        className={`px-3 py-1 rounded-full border text-xs font-semibold ${jenis === r.jenis ? "bg-[#006A4E] text-white border-[#006A4E]" : "bg-white hover:bg-gray-100"}`}
                                    >
                                        {r.label}: {angka(r.jumlah)}
                                    </button>
                                ))}
                            </div>

                            <div className="relative mb-3 max-w-sm">
                                <Search
                                    size={16}
                                    className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                                />
                                <input
                                    value={cari}
                                    onChange={(e) => setCari(e.target.value)}
                                    placeholder="Cari nama atau NIP"
                                    className="w-full pl-9 pr-3 py-2 border rounded-lg text-sm"
                                />
                            </div>

                            <div
                                className={`overflow-x-auto border rounded-lg ${loading ? "opacity-60" : ""}`}
                            >
                                <table className="w-full text-sm border-collapse">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="p-2 text-left">Jenis</th>
                                            <th className="p-2 text-left">Nama</th>
                                            <th className="p-2 text-left">NIP</th>
                                            <th className="p-2 text-left">Unit</th>
                                            <th className="p-2 text-left">
                                                Dari
                                                {data.pembanding && (
                                                    <span className="block text-xs font-normal text-gray-500">
                                                        ({namaBulanPendek(data.pembanding.periode)})
                                                    </span>
                                                )}
                                            </th>
                                            <th className="p-2 text-left">
                                                Menjadi
                                                <span className="block text-xs font-normal text-gray-500">
                                                    ({namaBulanPendek(data.periode)})
                                                </span>
                                            </th>
                                            <th className="p-2 text-left">Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.length === 0 && (
                                            <tr>
                                                <td colSpan={7} className="p-3 text-gray-500">
                                                    Tidak ada data yang cocok.
                                                </td>
                                            </tr>
                                        )}
                                        {rows.map((r, i) => (
                                            <tr
                                                key={`${r.nip}-${r.jenis}-${i}`}
                                                className="border-t align-top"
                                            >
                                                <td className="p-2 whitespace-nowrap font-semibold">
                                                    {r.jenis_label}
                                                </td>
                                                <td className="p-2">{r.nama}</td>
                                                <td className="p-2 whitespace-nowrap">
                                                    {r.nip || "-"}
                                                </td>
                                                <td className="p-2">{r.unit || "-"}</td>
                                                <td className="p-2">{r.dari || "-"}</td>
                                                <td className="p-2">{r.ke || "-"}</td>
                                                <td className="p-2 text-gray-600">
                                                    {r.keterangan || ""}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {lastPage > 1 && (
                                <div className="flex items-center gap-3 mt-3">
                                    <button
                                        disabled={page <= 1}
                                        onClick={() => setPage((p) => p - 1)}
                                        className="px-3 py-1.5 bg-gray-200 rounded text-sm disabled:opacity-40"
                                    >
                                        Sebelumnya
                                    </button>
                                    <span className="text-xs text-gray-600">
                                        Halaman {page} dari {lastPage}
                                    </span>
                                    <button
                                        disabled={page >= lastPage}
                                        onClick={() => setPage((p) => p + 1)}
                                        className="px-3 py-1.5 bg-gray-200 rounded text-sm disabled:opacity-40"
                                    >
                                        Berikutnya
                                    </button>
                                </div>
                            )}
                        </>
                    )}
                </>
            )}
        </div>
    );
}