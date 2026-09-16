import { useEffect, useState } from "react";
import { apiFetch } from "../../lib/api";
import KemantrenRekapTable from "./components/KemantrenRekapTable";

// PPPK Kemantren Berdasarkan Golongan dan Jenis Kelamin (5.03.018).
// Scope: PPPK aktif yang ber-instansi salah satu dari 14 Kemantren Kota
// Yogyakarta — lihat statistikPppkKemantrenGolongan() di StatistikService
// & endpoint GET /statistik/pppk-kemantren-golongan. BEDA dengan
// PnsKemantrenGolonganPanel.jsx: golongan PPPK memakai kode romawi POLOS
// (I, III, V, VII, IX, X, XI — bukan 'III/a'). Golongan ditampilkan per
// kode, TIDAK digabung jadi rentang I-IV/V-VIII/IX-XII/XIII-XVII seperti
// versi sebelumnya. Ditampilkan sebagai tabel rekap (baris Kemantren x
// kolom golongan, masing-masing L/P), mengikuti bentuk tabel di halaman
// Rekapitulasi ASN Admin (lihat RekapKategoriTable/RekapGolonganTable) —
// sama seperti PnsKemantrenGolonganPanel.jsx &
// PppkKemantrenPendidikanPanel.jsx — menggantikan tampilan card & grafik
// sebelumnya. Kolomnya banyak, jadi tabel digeser horizontal.
const KEMANTREN_LIST = [
    "TEGALREJO", "JETIS", "GONDOKUSUMAN", "DANUREJAN", "GEDONGTENGEN",
    "NGAMPILAN", "WIROBRAJAN", "MANTRIJERON", "KRATON", "GONDOMANAN",
    "PAKUALAMAN", "MERGANGSAN", "UMBULHARJO", "KOTAGEDE",
];

// Kolom golongan PPPK per kode, urut & selengkap GolonganRuangSeeder
// (sama dengan PPPK_LIST di RekapGolonganTable halaman Admin). Key harus
// sama persis dengan key yang dikembalikan statistikPppkKemantrenGolongan()
// di $d['laki_laki'] / $d['perempuan'] ('VII' -> 'golongan_VII').
const GOLONGAN_LIST = [
    { key: "golongan_I", label: "I" },
    { key: "golongan_III", label: "III" },
    { key: "golongan_V", label: "V" },
    { key: "golongan_VII", label: "VII" },
    { key: "golongan_IX", label: "IX" },
    { key: "golongan_X", label: "X" },
    { key: "golongan_XI", label: "XI" },
];

// Judul rapi ("TEGALREJO" -> "Tegalrejo") untuk label baris tabel.
function toTitleCase(text) {
    return text
        .toLowerCase()
        .replace(/(^|\s)\S/g, (c) => c.toUpperCase());
}

function PppkKemantrenGolonganPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pppk-kemantren-golongan");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data PPPK Kemantren berdasarkan golongan dan jenis kelamin."
                    );
                }

                const json = await res.json();
                // StatistikPppkKemantrenGolonganController mengembalikan
                // hasil service apa adanya (tanpa pembungkus { success,
                // data }) — sama seperti StatistikPnsKemantrenGolonganController.
                // Dibaca toleran (json.data ?? json) supaya panel tidak
                // pernah tampil kosong walau controllernya belum/tidak
                // dibungkus { data }.
                if (!cancelled) setData(json.data ?? json);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data PPPK Kemantren berdasarkan golongan dan jenis kelamin."
                    );
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        }

        load();
        return () => {
            cancelled = true;
        };
    }, []);

    // Susun baris tabel: satu baris per Kemantren, kolom sesuai
    // GOLONGAN_LIST (masing-masing pecahan laki-laki/perempuan) + kolom
    // Jumlah L/P/Total — pola sama seperti row di PnsKemantrenGolonganPanel.jsx.
    const rows = data
        ? KEMANTREN_LIST.map((nama) => {
              const kemantren = data.kemantren[nama] || {
                  total: 0,
                  laki_laki: {},
                  perempuan: {},
              };

              return {
                  label: toTitleCase(nama),
                  laki_laki: kemantren.laki_laki,
                  perempuan: kemantren.perempuan,
                  jumlah_laki_laki: kemantren.laki_laki?.total || 0,
                  jumlah_perempuan: kemantren.perempuan?.total || 0,
                  jumlah_total: kemantren.total || 0,
              };
          })
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                PPPK Kemantren Berdasarkan Golongan dan Jenis Kelamin
            </h2>

            <KemantrenRekapTable
                title="Rekapitulasi PPPK Kemantren Berdasarkan Golongan"
                subtitle={
                    data
                        ? `Jumlah PPPK Kemantren: ${data.jumlah_pppk_kemantren?.toLocaleString("id-ID")}`
                        : "Data PPPK Kemantren per golongan, dipecah menurut jenis kelamin."
                }
                categories={GOLONGAN_LIST}
                rows={rows}
                loading={loading}
                error={error}
            />
        </div>
    );
}

export default PppkKemantrenGolonganPanel;