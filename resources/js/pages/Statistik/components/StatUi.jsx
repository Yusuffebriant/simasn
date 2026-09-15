import { X } from "lucide-react";
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

// onClick opsional: kalau diisi, kartu jadi bisa diklik (mis. untuk buka
// tabel preview rincian gabungan semua kategori) — kartu berubah jadi
// <button> beneran, dapat hover/focus ring, dan tetap bisa dinavigasi
// keyboard. Sama seperti pola di MiniStatCard.
export function TotalCard({ title, total, onClick }) {
    const clickableClass = onClick
        ? " w-full text-left cursor-pointer transition hover:shadow-md hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-[#0F6E6E] focus:ring-offset-2"
        : "";

    const Tag = onClick ? "button" : "div";

    return (
        <Tag
            type={onClick ? "button" : undefined}
            onClick={onClick}
            className={"bg-[#172033] text-white rounded-xl p-5 h-full" + clickableClass}
        >
            <div className="text-[13px] text-white/70 mb-2">{title}</div>
            <div className="text-4xl font-bold">{total.toLocaleString("id-ID")}</div>
        </Tag>
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
// onClick opsional: kalau diisi, kartu jadi bisa diklik (mis. untuk buka
// tabel preview rincian) — kartu berubah jadi <button> beneran, dapat
// hover/focus ring, dan tetap bisa dinavigasi keyboard.
export function MiniStatCard({ title, total, laki_laki, perempuan, variant = "light", onClick }) {
    const isDark = variant === "dark";
    const baseClass = isDark
        ? "bg-[#172033] text-white rounded-xl p-5"
        : "bg-[#E7F1FB] border border-[#D3E5F5] rounded-xl p-5";
    const clickableClass = onClick
        ? " w-full text-left cursor-pointer transition hover:shadow-md hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-[#0F6E6E] focus:ring-offset-2"
        : "";

    const Tag = onClick ? "button" : "div";

    return (
        <Tag
            type={onClick ? "button" : undefined}
            onClick={onClick}
            className={baseClass + clickableClass}
        >
            <div className={isDark ? "text-[13px] text-white/70 mb-2" : "text-[13px] text-[#3A5A78] mb-2"}>{title}</div>
            <div className={isDark ? "text-3xl font-bold text-white mb-3" : "text-3xl font-bold text-[#172033] mb-3"}>{total.toLocaleString("id-ID")}</div>
            <div className="flex gap-2">
                <GenderChip icon="♂" value={laki_laki} variant={variant} />
                <GenderChip icon="♀" value={perempuan} variant={variant} />
            </div>
        </Tag>
    );
}

// Modal tabel preview: menampilkan rincian angka resmi (mis. format
// pelaporan BKN/SIASN) yang dikelompokkan per kategori (`groups`), saat
// sebuah kartu statistik diklik. Tiap grup punya 1 baris "total" (tebal)
// dan opsional baris rincian laki-laki/perempuan (indentasi + ikon).
// `highlightGroup` menyorot & auto-scroll ke grup yang baru diklik.
export function PreviewTableModal({ title, subtitle, groups, highlightGroup, onClose }) {
    return (
        <div
            className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4"
            onClick={onClose}
        >
            <div
                className="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col overflow-hidden"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-start justify-between gap-4 p-5 border-b border-[#E1E5EA] bg-[#006A4E]">
                    <div>
                        <h3 className="text-lg font-bold text-white">{title}</h3>
                        {subtitle && (
                            <p className="text-sm text-white/80 mt-0.5">{subtitle}</p>
                        )}
                    </div>
                    <button
                        onClick={onClose}
                        className="shrink-0 p-2 -m-2 hover:bg-white/10 rounded-lg text-white/80 hover:text-white transition"
                        aria-label="Tutup"
                    >
                        <X size={20} />
                    </button>
                </div>

                <div className="overflow-y-auto p-4 flex-1 bg-[#F7F8FA]">
                    <div className="flex flex-col gap-3">
                        {groups.map((group) => {
                            const isActive = highlightGroup && group.key === highlightGroup;
                            return (
                                <div
                                    key={group.key}
                                    ref={isActive ? scrollIntoActive : undefined}
                                    className={
                                        "bg-white rounded-lg border overflow-hidden transition " +
                                        (isActive
                                            ? "border-[#D4A017] ring-2 ring-[#D4A017]/40"
                                            : "border-[#E1E5EA]")
                                    }
                                >
                                    <div
                                        className={
                                            "px-4 py-2.5 text-sm font-semibold " +
                                            (isActive
                                                ? "bg-[#FFF8E1] text-[#8A6D1A]"
                                                : "bg-[#F0F2F5] text-[#3A4658]")
                                        }
                                    >
                                        {group.label}
                                    </div>
                                    <div className="divide-y divide-[#F0F2F5]">
                                        {group.items.map((item, idx) => (
                                            <div
                                                key={idx}
                                                className="flex items-center justify-between px-4 py-2"
                                            >
                                                <span
                                                    className={
                                                        item.type === "total"
                                                            ? "text-sm text-[#172033] font-medium"
                                                            : "text-sm text-[#687386] pl-4 flex items-center gap-1.5"
                                                    }
                                                >
                                                    {item.type === "laki_laki" && <span aria-hidden>♂</span>}
                                                    {item.type === "perempuan" && <span aria-hidden>♀</span>}
                                                    {item.label}
                                                </span>
                                                <span
                                                    className={
                                                        item.type === "total"
                                                            ? "text-sm font-bold text-[#172033]"
                                                            : "text-sm font-semibold text-[#3A4658]"
                                                    }
                                                >
                                                    {Number(item.value || 0).toLocaleString("id-ID")}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </div>
    );
}

// Ref callback kecil supaya grup yang sedang aktif langsung ter-scroll
// ke area terlihat begitu modal dibuka (tanpa perlu useEffect terpisah
// di komponen pemanggil).
function scrollIntoActive(node) {
    if (node) {
        node.scrollIntoView({ block: "nearest" });
    }
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