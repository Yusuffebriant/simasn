import { ResponsiveContainer } from "recharts";

export function SkeletonCard() {
    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5">
            <div className="h-3 w-24 bg-[#EEF1F5] rounded mb-3" />
            <div className="h-8 w-16 bg-[#EEF1F5] rounded" />
        </div>
    );
}

export function ErrorBox({ message }) {
    return (
        <div className="bg-white border border-[#F3C6C6] text-[#B42318] rounded-xl p-5 mb-6">
            {message}
        </div>
    );
}

export function GenderChip({ icon, value, variant = "light" }) {
    const bgClass = variant === "dark" ? "bg-white/10" : "bg-black/10";
    return (
        <span className={`inline-flex items-center gap-1 ${bgClass} rounded-md px-2.5 py-1 text-xs font-semibold`}>
            <span aria-hidden>{icon}</span>
            {value.toLocaleString("id-ID")}
        </span>
    );
}

export function TotalCard({ title, total }) {
    return (
        <div className="bg-[#172033] text-white rounded-xl p-5 h-full">
            <div className="text-[13px] text-white/70 mb-2">{title}</div>
            <div className="text-4xl font-bold">{total.toLocaleString("id-ID")}</div>
        </div>
    );
}

// Kartu angka tunggal untuk rincian statistik (mis. jumlah per
// dinas/Kemantren) yang TIDAK punya rincian gender (beda dari
// MiniStatCard). Warna latar biru muda (#E7F1FB) disamakan dengan
// MiniStatCard variant="light" supaya kartu rincian di seluruh halaman
// Statistik konsisten satu skema warna — StatCard cuma beda karena
// tanpa baris gender laki-laki/perempuan. variant="dark" memakai warna
// latar & teks yang sama seperti TotalCard (mis. untuk kartu ringkasan
// "Jumlah Pejabat Struktural" pada bagian lain).
export function StatCard({ title, value, variant = "light" }) {
    const isDark = variant === "dark";
    return (
        <div className={isDark ? "bg-[#172033] text-white rounded-xl p-5" : "bg-[#E7F1FB] border border-[#D3E5F5] rounded-xl p-5"}>
            <div className={isDark ? "text-[13px] text-white/70 mb-2" : "text-[13px] text-[#3A5A78] mb-2"}>{title}</div>
            <div className={isDark ? "text-2xl font-bold text-white" : "text-2xl font-bold text-[#172033]"}>
                {Number(value || 0).toLocaleString("id-ID")}
            </div>
        </div>
    );
}

// variant="dark" memakai warna latar & teks yang sama seperti TotalCard.
export function MiniStatCard({ title, total, laki_laki, perempuan, variant = "light" }) {
    const isDark = variant === "dark";
    return (
        <div className={isDark ? "bg-[#172033] text-white rounded-xl p-5" : "bg-[#E7F1FB] border border-[#D3E5F5] rounded-xl p-5"}>
            <div className={isDark ? "text-[13px] text-white/70 mb-2" : "text-[13px] text-[#3A5A78] mb-2"}>{title}</div>
            <div className={isDark ? "text-3xl font-bold text-white mb-3" : "text-3xl font-bold text-[#172033] mb-3"}>{total.toLocaleString("id-ID")}</div>
            <div className="flex gap-2">
                <GenderChip icon="♂" value={laki_laki} variant={variant} />
                <GenderChip icon="♀" value={perempuan} variant={variant} />
            </div>
        </div>
    );
}

export function ChartCard({ title, height = 300, children }) {
    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5">
            <h3 className="text-[#172033] font-semibold mb-4">{title}</h3>
            <div style={{ width: "100%", height }}>
                <ResponsiveContainer>{children}</ResponsiveContainer>
            </div>
        </div>
    );
}

// Placeholder loading untuk grafik — 3 titik animasi + keterangan
// "Memuat data...", dipakai selagi data grafik masih diambil dari server
// (menggantikan area kosong sebelum grafik sungguhan bisa dirender).
export function ChartCardLoading({ title, height = 300, label = "Memuat data..." }) {
    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5">
            <h3 className="text-[#172033] font-semibold mb-4">{title}</h3>
            <div
                style={{ width: "100%", height }}
                className="flex flex-col items-center justify-center gap-3"
            >
                <div className="flex gap-1.5">
                    <span className="w-2.5 h-2.5 rounded-full bg-[#006A4E] animate-bounce [animation-delay:-0.3s]" />
                    <span className="w-2.5 h-2.5 rounded-full bg-[#006A4E] animate-bounce [animation-delay:-0.15s]" />
                    <span className="w-2.5 h-2.5 rounded-full bg-[#006A4E] animate-bounce" />
                </div>
                <span className="text-sm text-[#8A93A0]">{label}</span>
            </div>
        </div>
    );
}