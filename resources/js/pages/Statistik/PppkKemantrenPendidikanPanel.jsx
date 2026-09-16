import { useEffect, useState } from "react";
import { apiFetch } from "../../lib/api";
import KemantrenRekapTable from "./components/KemantrenRekapTable";
import { PENDIDIKAN_LIST } from "./pendidikanList";

// PPPK Kemantren Berdasarkan Tingkat Pendidikan (5.03.016).
// Scope: PPPK aktif (status_kepegawaian = 'PPPK') yang ber-UNIT salah satu
// dari 14 Kemantren Kota Yogyakarta — lihat statistikPppkKemantrenPendidikan()
// di StatistikService & endpoint GET /statistik/pppk-kemantren-pendidikan.
// Ditampilkan sebagai tabel rekap (baris Kemantren x kolom tingkat
// pendidikan SD-S3, masing-masing L/P), mengikuti bentuk tabel di halaman
// Rekapitulasi ASN Admin (lihat RekapKategoriTable) — sama seperti
// PnsKemantrenPendidikanPanel.jsx — menggantikan tampilan card & grafik
// sebelumnya.
const KEMANTREN_LIST = [
    "TEGALREJO", "JETIS", "GONDOKUSUMAN", "DANUREJAN", "GEDONGTENGEN",
    "NGAMPILAN", "WIROBRAJAN", "MANTRIJERON", "KRATON", "GONDOMANAN",
    "PAKUALAMAN", "MERGANGSAN", "UMBULHARJO", "KOTAGEDE",
];

// Judul rapi ("TEGALREJO" -> "Tegalrejo") untuk label baris tabel.
function toTitleCase(text) {
    return text
        .toLowerCase()
        .replace(/(^|\s)\S/g, (c) => c.toUpperCase());
}

const PENDIDIKAN_CATEGORIES = PENDIDIKAN_LIST.map((p) => ({
    key: p.key,
    label: p.chartLabel,
}));

function PppkKemantrenPendidikanPanel() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/statistik/pppk-kemantren-pendidikan");

                if (!res.ok) {
                    throw new Error(
                        "Gagal memuat data PPPK Kemantren berdasarkan tingkat pendidikan."
                    );
                }

                const json = await res.json();
                // StatistikPppkKemantrenPendidikanController mengembalikan hasil
                // service apa adanya (tanpa pembungkus { success, data }) — beda
                // dengan controller ASN Kemantren. Dibaca toleran supaya panel
                // tetap jalan kalau nanti controller-nya diseragamkan.
                if (!cancelled) setData(json.data ?? json);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message ||
                            "Gagal memuat data PPPK Kemantren berdasarkan tingkat pendidikan."
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

    // Susun baris tabel: satu baris per Kemantren. Data mentah dari
    // service dipecah per jenjang -> gender -> Kemantren
    // (data.pendidikan[key].laki_laki.kemantren[nama]), jadi di sini
    // dibalik ("pivot") menjadi per Kemantren -> jenjang -> gender supaya
    // cocok dengan bentuk KemantrenRekapTable (baris Kemantren, kolom
    // jenjang pendidikan) — sama seperti PnsKemantrenPendidikanPanel.jsx.
    const rows = data
        ? KEMANTREN_LIST.map((nama) => {
              const laki_laki = {};
              const perempuan = {};
              let jumlah_laki_laki = 0;
              let jumlah_perempuan = 0;

              PENDIDIKAN_LIST.forEach((p) => {
                  const jenjang = data.pendidikan[p.key];
                  const l = jenjang?.laki_laki?.kemantren?.[nama] || 0;
                  const pr = jenjang?.perempuan?.kemantren?.[nama] || 0;

                  laki_laki[p.key] = l;
                  perempuan[p.key] = pr;
                  jumlah_laki_laki += l;
                  jumlah_perempuan += pr;
              });

              return {
                  label: toTitleCase(nama),
                  laki_laki,
                  perempuan,
                  jumlah_laki_laki,
                  jumlah_perempuan,
                  jumlah_total: jumlah_laki_laki + jumlah_perempuan,
              };
          })
        : [];

    return (
        <div>
            <h2 className="text-[#172033] font-semibold mb-3 text-lg">
                PPPK Kemantren Berdasarkan Tingkat Pendidikan
            </h2>

            <KemantrenRekapTable
                title="Rekapitulasi PPPK Kemantren Berdasarkan Tingkat Pendidikan"
                subtitle={
                    data
                        ? `Jumlah PPPK Kemantren: ${data.jumlah_pppk_kemantren?.toLocaleString("id-ID")}`
                        : "Data PPPK Kemantren per tingkat pendidikan, dipecah menurut jenis kelamin."
                }
                categories={PENDIDIKAN_CATEGORIES}
                rows={rows}
                loading={loading}
                error={error}
            />
        </div>
    );
}

export default PppkKemantrenPendidikanPanel;