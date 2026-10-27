import { useEffect, useState } from "react";
import { CheckCircle2, XCircle, AlertTriangle, Info } from "lucide-react";
import { apiFetch } from "../../lib/api";
import KurangPerInstansi from "../Admin/KurangPerInstansi";

// Peringatan NIP / NIK dari /imports/{id}/kelengkapan (kunci "identitas").
// "merah" = pegawai tanpa identitas sama sekali; "kuning" = perlu dicek.
const KATEGORI_IDENTITAS = [
    {
        kunci: "tanpa_keduanya",
        judul: "tidak punya NIP maupun NIK",
        tingkat: "merah",
    },
    {
        kunci: "tanpa_nip",
        judul: "belum punya NIP (NIK terisi)",
        tingkat: "kuning",
    },
    {
        kunci: "tanpa_nik",
        judul: "belum punya NIK (NIP terisi)",
        tingkat: "kuning",
    },
    {
        kunci: "format_tidak_valid",
        judul: "NIP atau NIK formatnya tidak valid",
        tingkat: "kuning",
    },
];

// Sebab isian kosong (dari AlasanImport di backend).
const SEBAB_LABEL = {
    kosong_di_file: "kosong di Excel",
    kolom_tidak_ada: "kolom tidak ada di file",
    tidak_dikenali: "isi tidak dikenali",
};

// Jenis sel berisi rumus Excel (dari AlasanImport::jenisRumus di backend).
const JENIS_RUMUS_LABEL = {
    teks_dibungkus:
        "berbentuk =\"angka\", cara Excel menyimpan angka sebagai teks. Sistem membaca rumusnya, bukan angkanya, sehingga isi yang tersimpan bisa ikut membawa tanda =\"...\" (mis. NIP menjadi 21 karakter).",
    rumus:
        "rumus yang mengambil data dari tempat lain (mis. VLOOKUP ke file Excel lain). Sistem tidak bisa menghitungnya, jadi yang terbaca teks rumusnya, bukan isi sebenarnya. Jika teksnya terlalu panjang untuk kolom tujuan, barisnya gagal disimpan.",
    campuran: "campuran rumus =\"angka\" dan rumus lain.",
};

const CARA_PERBAIKI_RUMUS =
    "Di Excel: tekan Ctrl+A (pilih semua sel), Ctrl+C (salin), klik kanan lalu pilih Paste Special > Values (Tempel Nilai), simpan file, lalu import ulang.";

function ImportResult({ result, onRestart }) {

    const total = result?.total_baris ?? 0;
    const berhasil = result?.berhasil ?? 0;
    const gagal = result?.gagal ?? 0;
    const gagalTotal = result?.status === "gagal";

    // Hasil sinkron "pegawai sisa" (yang tidak ada di file ini).
    // dinonaktifkan | dilewati | tidak_ada_sisa
    const sinkronStatus = result?.sinkron_status;
    const sinkronDilewati = sinkronStatus === "dilewati";

    // Pengecekan otomatis (rekonsiliasi): isi file dibandingkan dengan hasil
    // import periode ini.
    // cocok | selisih | tidak_tersedia
    const rekon = result?.rekonsiliasi;
    const rekonStatus = result?.rekonsiliasi_status;
    const rekonSelisih = rekon?.selisih ?? [];
    const periodeLabel = result?.periode ? `import periode ${result.periode}` : "import ini";
    const kosongRingkas = rekon?.kosong_ringkas ?? [];
    const kosongDetail = rekon?.kosong_detail ?? [];
    const rumusRingkas = rekon?.rumus_ringkas ?? [];

    // NIK sama tetapi nama berbeda: pegawai di baris sebelumnya tertimpa dan
    // hilang dari sistem. Jumlah akurat dari backend; daftar nama dari rincian.
    const nikBedaNama = rekon?.ganda_nik_beda_nama ?? 0;
    const nikBedaNamaDetail = (rekon?.ganda_detail ?? []).filter((g) => g.nik_nama_beda);

    // Seleksi kelengkapan: pegawai dari import ini yang isiannya masih kosong.
    const idBatch = result?.id;
    const [kelengkapan, setKelengkapan] = useState(null);

    useEffect(() => {
        if (!idBatch || gagalTotal) return;

        let batal = false;

        apiFetch(`/imports/${idBatch}/kelengkapan`)
            .then((res) => (res.ok ? res.json() : null))
            .then((json) => {
                if (!batal) setKelengkapan(json);
            })
            .catch(() => { });

        return () => {
            batal = true;
        };
    }, [idBatch, gagalTotal]);

    // Baris yang gagal diimport, lengkap dengan penyebab dan isian yang kurang.
    const [barisGagal, setBarisGagal] = useState(null);

    useEffect(() => {
        if (!idBatch || gagal <= 0) return;

        let batal = false;

        apiFetch(`/imports/${idBatch}/errors`)
            .then((res) => (res.ok ? res.json() : null))
            .then((json) => {
                if (!batal && json) setBarisGagal(json);
            })
            .catch(() => { });

        return () => {
            batal = true;
        };
    }, [idBatch, gagal]);

    // Saran perbaikan Excel: baris gagal dikelompokkan per kolom/masalah.
    const [saranExcel, setSaranExcel] = useState(null);

    useEffect(() => {
        if (!idBatch || gagal <= 0) return;

        let batal = false;

        apiFetch(`/imports/${idBatch}/saran-perbaikan`)
            .then((res) => (res.ok ? res.json() : null))
            .then((json) => {
                if (!batal && json) setSaranExcel(json.saran ?? []);
            })
            .catch(() => { });

        return () => {
            batal = true;
        };
    }, [idBatch, gagal]);

    const linkLengkapi = `/admin?tab=pegawai&kelengkapan=belum&batch=${idBatch}`;

    // Peringatan NIP / NIK: hanya kategori yang ada isinya yang ditampilkan.
    const identitas = kelengkapan?.identitas;
    const masalahIdentitas = identitas
        ? KATEGORI_IDENTITAS.filter((k) => (identitas[k.kunci]?.jumlah ?? 0) > 0)
        : [];
    const identitasMerah = masalahIdentitas.some((k) => k.tingkat === "merah");

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

            {nikBedaNama > 0 && !gagalTotal && (
                <div className="p-4 rounded-lg text-sm mb-6 bg-red-50 text-red-800 border border-red-200">
                    <div className="flex items-start gap-3">
                        <XCircle size={18} className="mt-0.5 shrink-0" />
                        <div className="w-full">
                            <p className="font-semibold">
                                {nikBedaNama} pegawai hilang karena NIK sama dengan nama berbeda
                            </p>
                            <p className="mt-1">
                                Di file ada baris yang NIK-nya sama dengan baris sebelumnya,
                                tetapi namanya berbeda. Sistem mengira keduanya satu orang,
                                sehingga data baris sebelumnya tertimpa dan orangnya tidak
                                ada lagi di sistem. Periksa NIK-nya di file: bisa salah
                                ketik, atau memang dua orang berbeda.
                            </p>

                            {nikBedaNamaDetail.length > 0 && (
                                <ul className="mt-2 list-disc pl-5 space-y-0.5">
                                    {nikBedaNamaDetail.slice(0, 10).map((g, i) => (
                                        <li key={i}>
                                            <b>{g.nama_sebelumnya}</b> (baris {g.baris_sebelumnya}) hilang,
                                            tertimpa <b>{g.nama}</b> (baris {g.baris}). NIK {g.nik}.
                                        </li>
                                    ))}
                                    {nikBedaNama > 10 && (
                                        <li>...dan {nikBedaNama - 10} pegawai lainnya</li>
                                    )}
                                </ul>
                            )}

                            <p className="mt-2">
                                Daftar lengkapnya bisa dilihat di <b>Riwayat Import</b> (tombol
                                &ldquo;Lihat Ganda&rdquo;).
                            </p>
                        </div>
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
                                    `Pengecekan otomatis: data hasil ${periodeLabel} sama dengan isi file`}
                                {rekonStatus === "selisih" &&
                                    `Pengecekan otomatis: ada selisih antara isi file dan hasil ${periodeLabel}`}
                                {rekonStatus === "tidak_tersedia" &&
                                    "Pengecekan otomatis tidak bisa dihitung untuk import ini"}
                            </p>

                            {rekon?.total && (
                                <p>
                                    Pegawai dibaca dari file: {rekon.total.file} &middot;
                                    tercatat di {periodeLabel}: {rekon.total.db}
                                </p>
                            )}

                            {rekon?.ganda_dalam_file > 0 && (
                                <p className="mt-1">
                                    {rekon.ganda_dalam_file} baris di file saling menimpa
                                    (NIP/NIK sama muncul lebih dari sekali). Baris yang
                                    muncul belakangan menimpa baris sebelumnya, sehingga
                                    keduanya hanya tersimpan sebagai 1 pegawai.
                                </p>
                            )}

                            {rekon?.ganda_detail?.length > 0 && (
                                <ul className="mt-2 list-disc pl-5 space-y-0.5">
                                    {rekon.ganda_detail.slice(0, 10).map((g, i) => (
                                        <li key={i}>
                                            Baris {g.baris} ({g.nama}) {g.alasan} dengan
                                            baris {g.baris_sebelumnya}: data baris {g.baris}{" "}
                                            menimpa baris {g.baris_sebelumnya}.
                                        </li>
                                    ))}
                                    {rekon.ganda_detail.length > 10 && (
                                        <li>
                                            ...dan {rekon.ganda_detail.length - 10} baris ganda lainnya
                                        </li>
                                    )}
                                </ul>
                            )}

                            {rekonSelisih.length > 0 && (
                                <ul className="mt-2 list-disc pl-5 space-y-0.5">
                                    {rekonSelisih.slice(0, 10).map((r, i) => (
                                        <li key={i}>
                                            {r.kategori} &ndash; {r.nilai}: file {r.file},
                                            hasil import {r.db}
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

            {rekon?.tidak_aktif_jumlah > 0 && !gagalTotal && (
                <div className="p-4 rounded-lg bg-blue-50 text-blue-800 text-sm mb-6">
                    <div className="flex items-start gap-3">
                        <Info size={18} className="mt-0.5 shrink-0" />
                        <div className="w-full">
                            <p className="font-semibold">
                                {rekon.tidak_aktif_jumlah} dari{" "}
                                {rekon.total?.db ?? total} pegawai tidak berstatus aktif
                            </p>
                            <p className="mt-1">
                                Semua baris sudah tersimpan, tetapi rekap dan dashboard
                                hanya menghitung pegawai berstatus aktif. Itu sebabnya
                                jumlah di sistem bisa lebih sedikit daripada jumlah baris
                                di Excel. Status ditentukan dari kolom
                                KEDUDUKAN_KEPEGAWAIAN (AKTIF, PENSIUN, MUTASI KELUAR,
                                MENINGGAL).
                            </p>

                            <ul className="mt-2 list-disc pl-5 space-y-0.5">
                                {(rekon.tidak_aktif_detail ?? []).slice(0, 10).map((p, i) => (
                                    <li key={i}>
                                        Baris {p.baris}: {p.nama}
                                        {p.status_kepegawaian ? ` (${p.status_kepegawaian}` : " ("}
                                        {p.unit ? `, ${p.unit}` : ""}) &ndash;{" "}
                                        {{
                                            meninggal: "Meninggal",
                                            mutasi_keluar: "Mutasi keluar",
                                            pensiun: "Pensiun",
                                        }[p.status_aktif] ?? p.status_aktif}
                                    </li>
                                ))}
                                {(rekon.tidak_aktif_detail?.length ?? 0) > 10 && (
                                    <li>
                                        ...dan {rekon.tidak_aktif_detail.length - 10} pegawai lainnya
                                    </li>
                                )}
                            </ul>
                        </div>
                    </div>
                </div>
            )}

            {rumusRingkas.length > 0 && !gagalTotal && (
                <div className="p-4 rounded-lg bg-amber-50 text-amber-800 text-sm mb-6">
                    <div className="flex items-start gap-3">
                        <AlertTriangle size={18} className="mt-0.5 shrink-0" />
                        <div className="w-full">
                            <p className="font-semibold">
                                {(rekon?.rumus_baris ?? 0).toLocaleString("id-ID")} baris di file
                                berisi rumus Excel, bukan isi sebenarnya
                            </p>
                            <p className="mt-1">
                                Sistem membaca teks rumusnya, bukan nilai hasilnya. Akibatnya
                                isian bisa gagal disimpan (baris masuk daftar gagal) atau
                                tersimpan salah (mis. NIP/NIK dianggap tidak valid). Kolom yang
                                berisi rumus:
                            </p>

                            <ul className="mt-2 space-y-2">
                                {rumusRingkas.map((r) => (
                                    <li key={r.kolom} className="bg-white/70 rounded p-3">
                                        <p className="font-semibold">
                                            Kolom {r.kolom}: {r.jumlah.toLocaleString("id-ID")} sel
                                        </p>
                                        <p className="mt-0.5">
                                            Isinya {JENIS_RUMUS_LABEL[r.jenis] ?? JENIS_RUMUS_LABEL.rumus}
                                        </p>
                                        <p className="mt-0.5 text-xs">
                                            Contoh isi: {r.contoh_isi} &middot; contoh baris:{" "}
                                            {r.contoh_baris.join(", ")}
                                            {r.jumlah > r.contoh_baris.length ? ", ..." : ""}
                                        </p>
                                    </li>
                                ))}
                            </ul>

                            <p className="mt-3">
                                <span className="font-medium">Cara memperbaiki:</span>{" "}
                                {CARA_PERBAIKI_RUMUS}
                            </p>
                        </div>
                    </div>
                </div>
            )}

            {rekon?.kosong_jumlah > 0 && !gagalTotal && (
                <div className="p-4 rounded-lg bg-amber-50 text-amber-800 text-sm mb-6">
                    <div className="flex items-start gap-3">
                        <AlertTriangle size={18} className="mt-0.5 shrink-0" />
                        <div className="w-full">
                            <p className="font-semibold">
                                {rekon.kosong_jumlah.toLocaleString("id-ID")} pegawai tersimpan
                                tetapi ada isian yang kosong
                            </p>
                            <p className="mt-1">
                                Pegawai ini tetap masuk, tetapi isian di bawah belum terisi.
                                Tiap isian diberi sebabnya: kosong di Excel (tinggal diisi),
                                kolom tidak ada di file (periksa judul kolom), atau isi tidak
                                dikenali (perbaiki tulisannya).
                            </p>

                            {kosongRingkas.length > 0 && (
                                <ul className="mt-3 space-y-1">
                                    {kosongRingkas.map((k) => (
                                        <li key={k.label}>
                                            <span className="font-semibold">{k.label}</span>:{" "}
                                            {[
                                                k.kosong_di_file > 0 && `${k.kosong_di_file.toLocaleString("id-ID")} kosong di Excel`,
                                                k.kolom_tidak_ada > 0 && `${k.kolom_tidak_ada.toLocaleString("id-ID")} karena kolom tidak ada di file`,
                                                k.tidak_dikenali > 0 && `${k.tidak_dikenali.toLocaleString("id-ID")} isinya tidak dikenali`,
                                            ].filter(Boolean).join(", ")}
                                            {k.nilai_tidak_dikenali?.length > 0 && (
                                                <span>
                                                    {" "}(isi yang tidak dikenali:{" "}
                                                    {k.nilai_tidak_dikenali
                                                        .map((n) => `"${n.nilai}" ${n.jumlah}x`)
                                                        .join(", ")}
                                                    )
                                                </span>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}

                            {kosongDetail.length > 0 && (
                                <>
                                    <p className="mt-3 font-semibold">Contoh pegawainya</p>
                                    <ul className="mt-1 list-disc pl-5 space-y-1">
                                        {kosongDetail.slice(0, 10).map((p, i) => (
                                            <li key={i}>
                                                Baris {p.baris} &ndash; {p.nama}
                                                {p.nip ? ` (NIP ${p.nip})` : p.nik ? ` (NIK ${p.nik})` : ""}:{" "}
                                                {p.kurang
                                                    .map((k) => `${k.label} (${k.keterangan})`)
                                                    .join("; ")}
                                            </li>
                                        ))}
                                        {rekon.kosong_jumlah > Math.min(kosongDetail.length, 10) && (
                                            <li>
                                                ...dan{" "}
                                                {(rekon.kosong_jumlah - Math.min(kosongDetail.length, 10)).toLocaleString("id-ID")}{" "}
                                                pegawai lainnya (lihat daftar lengkap lewat tombol
                                                "Lengkapi di Daftar Pegawai" di bawah)
                                            </li>
                                        )}
                                    </ul>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {masalahIdentitas.length > 0 && !gagalTotal && (
                <div
                    className={`p-4 rounded-lg text-sm mb-6 ${identitasMerah
                        ? "bg-red-50 text-red-800"
                        : "bg-amber-50 text-amber-800"
                        }`}
                >
                    <div className="flex items-start gap-3">
                        <AlertTriangle size={18} className="mt-0.5 shrink-0" />
                        <div className="w-full">
                            <p className="font-semibold">
                                Ada pegawai dengan NIP / NIK yang perlu dicek
                            </p>
                            <p className="mt-1">
                                NIP kosong biasanya wajar untuk pegawai non-PNS asalkan
                                NIK terisi. Pegawai tanpa NIP dan NIK sama sekali sebaiknya
                                segera dilengkapi di file sumber atau lewat Daftar Pegawai.
                            </p>

                            {masalahIdentitas.map((k) => {
                                const data = identitas[k.kunci];

                                return (
                                    <div key={k.kunci} className="mt-3">
                                        <p className="font-semibold">
                                            {data.jumlah.toLocaleString("id-ID")} pegawai{" "}
                                            {k.judul}
                                        </p>
                                        <ul className="mt-1 list-disc pl-5 space-y-0.5">
                                            {data.daftar.map((p, i) => (
                                                <li key={i}>
                                                    {p.nama}
                                                    {p.keterangan ? ` – ${p.keterangan}` : ""}
                                                </li>
                                            ))}
                                            {data.jumlah > data.daftar.length && (
                                                <li>
                                                    ...dan{" "}
                                                    {(data.jumlah - data.daftar.length).toLocaleString("id-ID")}{" "}
                                                    pegawai lainnya
                                                </li>
                                            )}
                                        </ul>
                                    </div>
                                );
                            })}

                            {identitas?.nik_diperiksa === false && (
                                <p className="mt-3 text-xs">
                                    Catatan: kolom NIK tidak ditemukan pada data hasil import, jadi
                                    pemeriksaan hanya mencakup NIP.
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {kelengkapan && !gagalTotal && (
                kelengkapan.total_belum_lengkap > 0 ? (
                    <div className="p-4 rounded-lg bg-amber-50 text-amber-800 text-sm mb-6">
                        <div className="flex items-start gap-3">
                            <AlertTriangle size={18} className="mt-0.5 shrink-0" />
                            <div className="flex-1">
                                <p className="font-semibold">
                                    {kelengkapan.total_belum_lengkap.toLocaleString("id-ID")} dari{" "}
                                    {kelengkapan.total_pegawai.toLocaleString("id-ID")} pegawai
                                    datanya belum lengkap
                                </p>
                                <p className="mt-1">
                                    Isian berikut kosong di file Excel (atau isinya tidak
                                    dikenali). Klik salah satu untuk melihat pegawainya, lalu
                                    lengkapi lewat tombol edit di Daftar Pegawai.
                                </p>

                                <div className="flex flex-wrap gap-2 mt-3">
                                    {kelengkapan.per_kolom.map((k) => (
                                        <a
                                            key={k.kunci}
                                            href={`${linkLengkapi}&kosong=${k.kunci}`}
                                            className="px-3 py-1 rounded-full bg-white border border-amber-300 text-xs font-semibold hover:bg-amber-100"
                                        >
                                            {k.label}: {k.jumlah.toLocaleString("id-ID")}
                                        </a>
                                    ))}
                                </div>

                                <KurangPerInstansi
                                    daftar={kelengkapan.per_instansi}
                                    linkDasar={linkLengkapi}
                                />

                                <a
                                    href={linkLengkapi}
                                    className="inline-block mt-4 px-4 py-2 bg-[#006A4E] text-white rounded-lg text-sm font-semibold"
                                >
                                    Lengkapi di Daftar Pegawai
                                </a>
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="flex items-center gap-3 p-4 rounded-lg bg-green-50 text-[#006A4E] text-sm mb-6">
                        <CheckCircle2 size={18} className="shrink-0" />
                        Semua data pegawai pada import ini sudah lengkap.
                    </div>
                )
            )}

            {saranExcel?.length > 0 && (
                <div className="p-4 rounded-lg bg-blue-50 text-blue-900 text-sm mb-6">
                    <div className="flex items-start gap-3">
                        <Info size={18} className="mt-0.5 shrink-0" />
                        <div className="w-full">
                            <p className="font-semibold">Yang perlu diperbaiki di file Excel</p>
                            <p className="mt-1">
                                Perbaiki bagian berikut, simpan file, lalu import ulang. Diurutkan
                                dari yang paling banyak.
                            </p>
                            <p className="mt-1">
                                Rincian {gagal.toLocaleString("id-ID")} baris gagal:{" "}
                                {saranExcel
                                    .map((s) => `${s.judul} (${s.jumlah.toLocaleString("id-ID")} baris)`)
                                    .join("; ")}
                                . Satu baris bisa punya lebih dari satu masalah, jadi jumlah per
                                masalah bisa lebih besar dari total.
                            </p>

                            <ul className="mt-3 space-y-3">
                                {saranExcel.map((s) => (
                                    <li key={s.jenis} className="bg-white/70 rounded p-3">
                                        <p className="font-semibold">
                                            Kolom {s.kolom}: {s.judul} ({s.jumlah.toLocaleString("id-ID")} baris)
                                        </p>
                                        <p className="mt-0.5">
                                            <span className="font-medium">Cara memperbaiki:</span> {s.saran}
                                        </p>
                                        <p className="mt-0.5 text-xs">
                                            Contoh baris:{" "}
                                            {s.contoh.map((c) => (c.nama ? `${c.baris} (${c.nama})` : c.baris)).join(", ")}
                                            {s.jumlah > s.contoh.length ? ", ..." : ""}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </div>
            )}

            {gagal > 0 && barisGagal?.data?.length > 0 && (
                <div className="p-4 rounded-lg bg-red-50 text-red-800 text-sm mb-6">
                    <div className="flex items-start gap-3">
                        <XCircle size={18} className="mt-0.5 shrink-0" />
                        <div className="w-full">
                            <p className="font-semibold">
                                {gagal.toLocaleString("id-ID")} baris gagal diimport
                            </p>
                            <p className="mt-1">
                                Baris ini belum masuk ke data. Berikut penyebab dan yang
                                perlu diperbaiki di file Excel, lalu import ulang.
                            </p>

                            <ul className="mt-3 space-y-3">
                                {barisGagal.data.slice(0, 10).map((b, i) => (
                                    <li key={i} className="bg-white/70 rounded p-3">
                                        <p className="font-semibold">
                                            Baris {b.baris_ke}
                                            {b.data_mentah?.NAMA ? ` – ${b.data_mentah.NAMA}` : ""}
                                        </p>
                                        <p className="mt-0.5">
                                            <span className="font-medium">Penyebab:</span>{" "}
                                            {b.penyebab ?? b.pesan}
                                        </p>
                                        {b.kurang?.length > 0 && (
                                            <p className="mt-0.5">
                                                <span className="font-medium">Yang kurang:</span>{" "}
                                                {b.kurang.join(", ")}
                                            </p>
                                        )}
                                        {b.saran && (
                                            <p className="mt-0.5">
                                                <span className="font-medium">Cara memperbaiki:</span>{" "}
                                                {b.saran}
                                            </p>
                                        )}
                                        {b.data_lama_dipertahankan && (
                                            <p className="mt-0.5 text-xs text-red-700">
                                                Data lama pegawai ini tetap dipertahankan.
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>

                            {gagal > Math.min(barisGagal.data.length, 10) && (
                                <p className="mt-3">
                                    ...dan {(gagal - Math.min(barisGagal.data.length, 10)).toLocaleString("id-ID")} baris
                                    lainnya. Daftar lengkap ada di Riwayat Import &rarr; batch
                                    #{result?.id}.
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {gagal > 0 && !(barisGagal?.data?.length > 0) && (
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