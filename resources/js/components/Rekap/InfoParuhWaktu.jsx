import { useEffect, useState } from "react";
import { Info } from "lucide-react";
import { apiFetch } from "../../lib/api";

const fmt = (n) => Number(n || 0).toLocaleString("id-ID");

/**
 * Catatan informasi jumlah PPPK Paruh Waktu di halaman JF.
 * Hanya informasi: angkanya TIDAK masuk tabel maupun total JF.
 *
 * kategori : label dari RekapService::rekapParuhWaktuJenisJabatan()
 *            -> "Jabatan Fungsional" atau "Jabatan Pelaksana"
 * label    : nama halaman untuk kalimat, mis. "JF Tertentu"
 */
function InfoParuhWaktu({ periode, kategori, label }) {
    const [info, setInfo] = useState(null);

    useEffect(() => {
        let cancelled = false;

        async function load() {
            try {
                const res = await apiFetch(
                    `/rekap/paruh-waktu?periode=${encodeURIComponent(periode)}`
                );
                if (!res.ok) return;

                const data = await res.json();
                const list = Array.isArray(data?.per_jenis_jabatan)
                    ? data.per_jenis_jabatan
                    : [];
                const baris = list.find((r) => r.kategori === kategori);

                if (!cancelled) setInfo(baris || null);
            } catch {
                if (!cancelled) setInfo(null);
            }
        }

        load();
        return () => {
            cancelled = true;
        };
    }, [periode, kategori]);

    if (!info || !info.jumlah) return null;

    return (
        <div className="flex items-start gap-2 bg-blue-50 text-blue-800 text-sm rounded-lg p-3 mt-4">
            <Info size={16} className="mt-0.5 shrink-0" />
            <p>
                Informasi: terdapat <b>{fmt(info.jumlah)}</b> PPPK Paruh Waktu
                ({fmt(info.pria)} pria, {fmt(info.wanita)} wanita) pada {label}.
                Jumlah ini tidak termasuk dalam tabel dan total di atas;
                rinciannya ada di menu PPPK Paruh Waktu.
            </p>
        </div>
    );
}

export default InfoParuhWaktu;