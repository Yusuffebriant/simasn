import { useEffect, useState } from "react";
import { apiFetch } from "../../lib/api";
import KemantrenRekapTable from "./components/KemantrenRekapTable";
import ExportExcelButton from "./components/ExportExcelButton";

// PNS Kemantren Berdasarkan Golongan dan Jenis Kelamin (5.03.017).
// Scope: PNS aktif yang ber-UNIT salah satu dari 14 Kemantren Kota
// Yogyakarta — lihat statistikPnsKemantrenGolongan() di StatistikService
// & endpoint GET /statistik/pns-kemantren-golongan. Ditampilkan sebagai
// tabel rekap (baris Kemantren x kolom golongan ruang I/a s.d. IV/e,
// masing-masing L/P), mengikuti bentuk tabel di halaman Rekapitulasi
// ASN Admin (lihat RekapKategoriTable/RekapGolonganTable) —
// menggantikan tampilan card & grafik sebelumnya. Golongan TIDAK
// digabung jadi romawi I-IV; karena kolomnya jadi banyak, tabel
// digeser horizontal (lihat KemantrenRekapTable).
const KEMANTREN_LIST = [
    "TEGALREJO", "JETIS", "GONDOKUSUMAN", "DANUREJAN", "GEDONGTENGEN",
    "NGAMPILAN", "WIROBRAJAN", "MANTRIJERON", "KRATON", "GONDOMANAN",
    "PAKUALAMAN", "MERGANGSAN", "UMBULHARJO", "KOTAGEDE",
];

// Kolom golongan ruang PNS, urut & selengkap GolonganRuangSeeder.
// Key harus sama persis dengan key di $d['laki_laki']/$d['perempuan']
// yang dikembalikan statistikPnsKemantrenGolongan() ('III/a' ->
// 'golongan_III_a').
const GOLONGAN_LIST = [
    { key: "golongan_I_a", label: "I/a" },
    { key: "golongan_I_b", label: "I/b" },
    { key: "golongan_I_c", label: "I/c" },
    { key: "golongan_I_d", label: "I/d" },
    { key: "golongan_II_a", label: "II/a" },
    { key: "golongan_II_b", label: "II/b" },
    { key: "golongan_II_c", label: "II/c" },
    { key: "golongan_II_d", label: "II/d" },
    { key: "golongan_III_a", label: "III/a" },
    { key: "golongan_III_b", label: "III/b" },
    { key: "golongan_III_c", label: "III/c" },
    { key: "golongan_III_d", label: "III/d" },
    { key: "golongan_IV_a", label: "IV/a" },
    { key: "golongan_IV_b", label: "IV/b" },
    { key: "golongan_IV_c", label: "IV/c" },
    { key: "golongan_IV_d", label: "IV/d" },
    { key: "golongan_IV_e", label: "IV/e" },
];

// Judul rapi ("TEGALREJO" -> "Tegalrejo") untuk label baris tabel.
function toTitleCase(text) {
    return text
        .toLowerCase()
        .replace(/(^|\s)\S/g, (c) => c.toUpperCase());
}

function PnsKemantrenGolonganPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pns-kemantren-golongan");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data PNS Kemantren berdasarkan golongan dan jenis kelamin."
                    );
                }

                const json = await res.json();
                // StatistikPnsKemantrenGolonganController mengembalikan hasil
                // service apa adanya (tanpa pembungkus { success, data }) —
                // dibaca toleran supaya tetap jalan kalau nanti controller-nya
                // diseragamkan.
                if (!cancelled) setData(json.data ?? json);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data PNS Kemantren berdasarkan golongan dan jenis kelamin."
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
    // Jumlah L/P/Total — pola sama seperti row RekapKategoriTable.
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
            <KemantrenRekapTable
                title="PNS Kemantren Berdasarkan Golongan dan Jenis Kelamin"
                subtitle={
                    data
                        ? `Data PNS Kemantren aktif per golongan ruang, dipecah menurut jenis kelamin. Jumlah PNS Kemantren: ${data.jumlah_pns_kemantren?.toLocaleString("id-ID")}.`
                        : "Data PNS Kemantren aktif per golongan ruang, dipecah menurut jenis kelamin."
                }
                categories={GOLONGAN_LIST}
                rows={rows}
                loading={loading}
                error={error}
                exportButton={
                    <ExportExcelButton
                        path="/statistik/pns-kemantren-golongan/export"
                        filename="pns-kemantren-golongan.xlsx"
                        errorMessage="Gagal mengekspor data PNS Kemantren berdasarkan golongan dan jenis kelamin."
                        disabled={loading || !data}
                        onError={setError}
                    />
                }
            />
        </div>
    );
}

export default PnsKemantrenGolonganPanel;