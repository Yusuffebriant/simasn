import { useEffect, useState } from "react";
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";
import Sidebar from "../../components/Sidebar";
import { isLoggedIn, getUser, apiFetch } from "../../lib/api";

function Home() {
    const loggedIn = isLoggedIn();
    const user = getUser();
    const displayName = user?.name || user?.email;

    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {

        let cancelled = false;

        async function loadDashboard() {
            setLoading(true);
            setError(null);

            try {
                const res = await apiFetch("/rekap/dashboard");

                if (!res.ok) {
                    throw new Error("Gagal memuat statistik pegawai.");
                }

                const json = await res.json();
                if (!cancelled) setData(json);
            } catch (err) {
                if (!cancelled) {
                    setError(
                        err?.message || "Gagal memuat statistik pegawai."
                    );
                }
            } finally {
                if (!cancelled) setLoading(false);
            }
        }

        loadDashboard();

        return () => {
            cancelled = true;
        };
    }, []);

    return (
        <div className="flex min-h-screen bg-[#F5F7FA]">
            <Sidebar />

            <main className="flex-1 p-10 overflow-x-auto">
                <div className="mb-9">
                    <h1 className="text-3xl font-bold text-[#172033]">
                        Statistik Pegawai
                    </h1>
                    <div className="w-20 h-1 bg-[#D4A017] mt-4 mb-4" />
                    <p className="text-[#687386] text-[15px]">
                        Pemerintah Kota Yogyakarta · Rekapitulasi Data
                        Kepegawaian
                    </p>
                </div>

                <div className="bg-white rounded-xl border border-[#E1E5EA] p-6 mb-6 flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h2 className="text-xl font-semibold text-[#172033] mb-1">
                            {loggedIn
                                ? `Selamat datang${displayName ? `, ${displayName}` : ""}`
                                : "Selamat datang"}
                        </h2>
                        <p className="text-[#687386] text-sm">
                            {loggedIn
                                ? "Berikut rekapitulasi data kepegawaian terbaru."
                                : "Masuk sebagai admin untuk melihat rekapitulasi data kepegawaian secara lengkap."}
                        </p>
                    </div>
                    <a
                        href="/admin"
                        className="bg-[#006A4E] text-white px-6 py-3 rounded-lg font-semibold hover:bg-[#00543E] whitespace-nowrap"
                    >
                        {loggedIn ? "Buka Panel Admin" : "Masuk sebagai Admin"}
                    </a>
                </div>

                {error && (
                    <div className="bg-white border border-[#F3C6C6] text-[#B42318] rounded-xl p-5 mb-6">
                        {error}
                    </div>
                )}

                {loading && !data && (
                    <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))" }}>
                        <LockedStatCard title="Total Pegawai" loading />
                        <LockedStatCard title="Jabatan Struktural" loading />
                        <LockedStatCard title="JFU" loading />
                        <LockedStatCard title="Generasi" loading />
                    </div>
                )}

                {data && (
                    <>
                        {/* Total Pegawai */}
                        <div className="grid gap-4 mb-6" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(240px, 1fr))" }}>
                            <TotalCard
                                title="Total Pegawai"
                                total={data.total.total}
                                pria={data.total.pria}
                                wanita={data.total.wanita}
                            />

                            <MiniStatCard
                                title="Jabatan Struktural"
                                total={data.jabatan.struktural.total}
                                pria={data.jabatan.struktural.pria}
                                wanita={data.jabatan.struktural.wanita}
                            />

                            <MiniStatCard title="JFU" total={data.jabatan.jfu} />
                            <MiniStatCard title="JFT" total={data.jabatan.jft} />
                        </div>

                        {/* Generasi */}
                        <h3 className="text-[#172033] font-semibold mb-3">
                            Generasi
                        </h3>
                        <div className="grid gap-4 mb-6" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))" }}>
                            {data.generasi.map((g) => (
                                <GenerasiCard key={g.label} {...g} />
                            ))}
                        </div>

                        {/* Charts */}
                        <div className="grid gap-4 items-stretch" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(340px, 1fr))" }}>
                            <div className="flex flex-col gap-4">
                                <ChartCard
                                    title="Golongan"
                                    total={data.golongan.reduce((s, r) => s + r.jumlah, 0)}
                                >
                                    <BarChart data={data.golongan} barCategoryGap="28%">
                                        <defs>
                                            <linearGradient id="golonganBar" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stopColor="#17908A" />
                                                <stop offset="100%" stopColor="#0B4F4B" />
                                            </linearGradient>
                                        </defs>
                                        <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E1E5EA" />
                                        <XAxis dataKey="label" tick={{ fontSize: 12, fill: "#687386" }} axisLine={{ stroke: "#E1E5EA" }} tickLine={false} />
                                        <YAxis allowDecimals={false} tick={{ fontSize: 12, fill: "#687386" }} axisLine={false} tickLine={false} />
                                        <Tooltip
                                            cursor={{ fill: "#0F6E6E", opacity: 0.06 }}
                                            formatter={(value) => [value.toLocaleString("id-ID"), "Jumlah Pegawai"]}
                                            contentStyle={{ borderRadius: 10, border: "1px solid #E1E5EA", boxShadow: "0 4px 12px rgba(23,32,51,0.08)" }}
                                        />
                                        <Bar dataKey="jumlah" name="Jumlah Pegawai" fill="url(#golonganBar)" radius={[6, 6, 0, 0]} maxBarSize={56} />
                                    </BarChart>
                                </ChartCard>

                                {data.usia && (
                                    <CategoryStatTable
                                        title="Usia"
                                        labelHeader="Usia"
                                        data={data.usia}
                                    />
                                )}
                            </div>

                            <PendidikanChartCard data={data.pendidikan} />
                        </div>

                        {/* Masa Kerja Pangkat, Agama */}
                        <div
                            className="grid gap-4 mt-4"
                            style={{ gridTemplateColumns: "repeat(auto-fit, minmax(340px, 1fr))" }}
                        >
                            {data.masa_kerja_pangkat && (
                                <CategoryStatTable
                                    title="Masa Kerja Pangkat"
                                    labelHeader="Masa Kerja"
                                    data={data.masa_kerja_pangkat}
                                />
                            )}

                            {data.agama && (
                                <CategoryStatTable
                                    title="Agama"
                                    labelHeader="Agama"
                                    data={data.agama}
                                />
                            )}
                        </div>

                        {/* Unit Kerja */}
                        {data.unit_kerja && data.unit_kerja.length > 0 && (
                            <div className="mt-4">
                                <UnitKerjaTable data={data.unit_kerja} />
                            </div>
                        )}
                    </>
                )}
            </main>
        </div>
    );
}

function LockedStatCard({ title, loading }) {
    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5">
            <div className="text-[13px] text-[#687386] mb-2">{title}</div>
            <div className="text-3xl font-bold text-[#B8BFC9] mb-1">{loading ? "…" : "••"}</div>
            <div className="text-xs text-[#8A93A0]">{loading ? "Memuat…" : "Masuk untuk melihat data"}</div>
        </div>
    );
}

function GenderChip({ icon, value, size = "default", tone = "default" }) {
    const sizeClass =
        size === "sm"
            ? "gap-1 px-2 py-0.5 text-[11px]"
            : "gap-1 px-2.5 py-1 text-xs";

    const toneClass =
        tone === "onDark"
            ? "bg-white/10 text-white"
            : icon === "♂"
                ? "bg-[#0F6E6E]/10 text-[#0B5A54]"
                : "bg-[#4FA6A6]/15 text-[#2F7A78]";

    return (
        <span
            className={`inline-flex items-center rounded-md font-semibold tabular-nums ${sizeClass} ${toneClass}`}
        >
            <span aria-hidden>{icon}</span>
            {value.toLocaleString("id-ID")}
        </span>
    );
}

function TotalCard({ title, total, pria, wanita }) {
    return (
        <div className="bg-gradient-to-br from-[#1B2740] to-[#131B2C] text-white rounded-xl p-5 shadow-sm">
            <div className="text-[13px] text-white/70 mb-2">{title}</div>
            <div className="text-4xl font-bold mb-4">{total.toLocaleString("id-ID")}</div>
            <div className="flex gap-2">
                <GenderChip icon="♂" value={pria} tone="onDark" />
                <GenderChip icon="♀" value={wanita} tone="onDark" />
            </div>
        </div>
    );
}

function MiniStatCard({ title, total, pria, wanita }) {
    const hasGender = typeof pria === "number" && typeof wanita === "number";
    return (
        <div className="bg-[#E7F1FB] border border-[#D3E5F5] rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
            <div className="text-[13px] text-[#3A5A78] mb-2">{title}</div>
            <div className="text-3xl font-bold text-[#172033] mb-3">{total.toLocaleString("id-ID")}</div>
            {hasGender && (
                <div className="flex gap-2">
                    <GenderChip icon="♂" value={pria} />
                    <GenderChip icon="♀" value={wanita} />
                </div>
            )}
        </div>
    );
}

function GenerasiCard({ label, total, pria, wanita }) {
    return (
        <div className="bg-[#CBE7F0] border border-[#B5DBE8] rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
            <div className="text-[13px] text-[#215A6B] mb-2 font-medium">{label}</div>
            <div className="text-3xl font-bold text-[#172033] mb-3">{total.toLocaleString("id-ID")}</div>
            <div className="flex gap-2">
                <GenderChip icon="♂" value={pria} />
                <GenderChip icon="♀" value={wanita} />
            </div>
        </div>
    );
}

function CategoryStatTable({ title, labelHeader, data }) {
    const totalPria = data.reduce((sum, row) => sum + row.pria, 0);
    const totalWanita = data.reduce((sum, row) => sum + row.wanita, 0);
    const totalAll = totalPria + totalWanita;

    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
            <div className="flex items-baseline justify-between mb-4">
                <h3 className="text-[#172033] font-semibold">{title}</h3>
                <span className="text-xs text-[#8A93A0] tabular-nums">
                    {totalAll.toLocaleString("id-ID")} pegawai
                </span>
            </div>
            <table
                className="border-collapse table-fixed w-full"
            >
                <colgroup>
                    <col style={{ width: "34%" }} />
                    <col style={{ width: "22%" }} />
                    <col style={{ width: "22%" }} />
                    <col style={{ width: "22%" }} />
                </colgroup>
                <thead>
                    <tr className="text-left text-[#687386] text-[11px] font-normal border-b border-[#E1E5EA]">
                        <th className="py-2 pr-2">{labelHeader}</th>
                        <th className="py-2 px-1 text-center">Pria</th>
                        <th className="py-2 px-1 text-center">Wanita</th>
                        <th className="py-2 pl-1 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {data.map((row, i) => (
                        <tr
                            key={row.label}
                            className={`border-b border-[#F0F2F5] hover:bg-[#F5F7FA] transition-colors ${
                                i % 2 === 1 ? "bg-[#FAFBFC]" : ""
                            }`}
                        >
                            <td className="py-2 pr-2 text-[#172033] text-xs font-normal whitespace-nowrap">
                                {row.label}
                            </td>
                            <td className="py-2 px-1">
                                <div className="flex justify-center">
                                    <GenderChip icon="♂" value={row.pria} size="sm" />
                                </div>
                            </td>
                            <td className="py-2 px-1">
                                <div className="flex justify-center">
                                    <GenderChip icon="♀" value={row.wanita} size="sm" />
                                </div>
                            </td>
                            <td className="py-2 pl-1 text-right text-xs font-normal text-[#172033] tabular-nums">
                                {row.total.toLocaleString("id-ID")}
                            </td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr className="border-t-2 border-[#E1E5EA] bg-[#F5F7FA]">
                        <td className="py-2.5 pr-2 text-xs font-semibold text-[#172033] rounded-l-lg">
                            Total
                        </td>
                        <td className="py-2.5 px-1 text-center text-xs font-semibold text-[#172033] tabular-nums">
                            {totalPria.toLocaleString("id-ID")}
                        </td>
                        <td className="py-2.5 px-1 text-center text-xs font-semibold text-[#172033] tabular-nums">
                            {totalWanita.toLocaleString("id-ID")}
                        </td>
                        <td className="py-2.5 pl-1 text-right text-xs font-semibold text-[#172033] tabular-nums rounded-r-lg">
                            {totalAll.toLocaleString("id-ID")}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    );
}

function UnitKerjaTable({ data }) {
    const totalPria = data.reduce((sum, row) => sum + row.pria, 0);
    const totalWanita = data.reduce((sum, row) => sum + row.wanita, 0);
    const totalAll = totalPria + totalWanita;

    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
            <h3 className="text-[#172033] font-semibold mb-4">Unit Kerja</h3>
            <div
                className="overflow-y-auto overflow-x-auto"
                style={{ maxHeight: 480 }}
            >
                <table className="w-full text-sm border-collapse">
                    <thead className="sticky top-0 bg-white z-10">
                        <tr className="text-left text-[#687386] text-xs uppercase tracking-wide border-b border-[#E1E5EA]">
                            <th className="py-2 pr-3 font-medium w-10">
                                No
                            </th>
                            <th className="py-2 pr-3 font-medium">
                                Unit Kerja
                            </th>
                            <th className="py-2 px-3 font-medium text-center">
                                Pria
                            </th>
                            <th className="py-2 px-3 font-medium text-center">
                                Wanita
                            </th>
                            <th className="py-2 pl-3 font-medium text-right">
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((row, i) => (
                            <tr
                                key={row.label}
                                className="border-b border-[#F0F2F5] hover:bg-[#F5F7FA]"
                            >
                                <td className="py-2.5 pr-3 text-[#687386]">
                                    {i + 1}
                                </td>
                                <td className="py-2.5 pr-3 text-[#172033] font-medium whitespace-nowrap">
                                    {row.label}
                                </td>
                                <td className="py-2.5 px-3">
                                    <div className="flex justify-center">
                                        <GenderChip icon="♂" value={row.pria} />
                                    </div>
                                </td>
                                <td className="py-2.5 px-3">
                                    <div className="flex justify-center">
                                        <GenderChip icon="♀" value={row.wanita} />
                                    </div>
                                </td>
                                <td className="py-2.5 pl-3 text-right font-bold text-[#172033] tabular-nums">
                                    {row.total.toLocaleString("id-ID")}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="border-t-2 border-[#E1E5EA]">
                            <td
                                colSpan={2}
                                className="py-3 pr-3 font-semibold text-[#172033]"
                            >
                                Total
                            </td>
                            <td className="py-3 px-3 text-center font-semibold text-[#172033] tabular-nums">
                                {totalPria.toLocaleString("id-ID")}
                            </td>
                            <td className="py-3 px-3 text-center font-semibold text-[#172033] tabular-nums">
                                {totalWanita.toLocaleString("id-ID")}
                            </td>
                            <td className="py-3 pl-3 text-right font-bold text-[#172033] tabular-nums">
                                {totalAll.toLocaleString("id-ID")}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    );
}

function ChartCard({ title, total, children }) {
    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
            <div className="flex items-baseline justify-between mb-4">
                <h3 className="text-[#172033] font-semibold">{title}</h3>
                {typeof total === "number" && (
                    <span className="text-xs text-[#8A93A0] tabular-nums">
                        {total.toLocaleString("id-ID")} pegawai
                    </span>
                )}
            </div>
            <div style={{ width: "100%", height: 280 }}>
                <ResponsiveContainer>{children}</ResponsiveContainer>
            </div>
        </div>
    );
}

function PendidikanChartCard({ data }) {
    const totalPria = data.reduce((sum, row) => sum + row.pria, 0);
    const totalWanita = data.reduce((sum, row) => sum + row.wanita, 0);
    const totalAll = totalPria + totalWanita;

    const minChartHeight = Math.max(280, data.length * 34);

    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow flex flex-col h-full">
            <div className="flex items-baseline justify-between mb-4">
                <h3 className="text-[#172033] font-semibold">Pendidikan</h3>
                <span className="text-xs text-[#8A93A0] tabular-nums">
                    {totalAll.toLocaleString("id-ID")} pegawai
                </span>
            </div>
            <div className="flex-1" style={{ width: "100%", minHeight: minChartHeight }}>
                <ResponsiveContainer>
                    <BarChart data={data} layout="vertical" margin={{ left: 10 }} barCategoryGap="30%">
                        <defs>
                            <linearGradient id="priaBar" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0%" stopColor="#0B4F4B" />
                                <stop offset="100%" stopColor="#17908A" />
                            </linearGradient>
                            <linearGradient id="wanitaBar" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0%" stopColor="#3D9494" />
                                <stop offset="100%" stopColor="#6FC1C1" />
                            </linearGradient>
                        </defs>
                        <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke="#E1E5EA" />
                        <XAxis
                            type="number"
                            allowDecimals={false}
                            tick={{ fontSize: 12, fill: "#687386" }}
                            axisLine={{ stroke: "#E1E5EA" }}
                            tickLine={false}
                        />
                        <YAxis
                            type="category"
                            dataKey="label"
                            width={80}
                            interval={0}
                            tick={{ fontSize: 12, fill: "#687386" }}
                            axisLine={false}
                            tickLine={false}
                        />
                        <Tooltip
                            cursor={{ fill: "#0F6E6E", opacity: 0.06 }}
                            formatter={(value, name) => [
                                value.toLocaleString("id-ID"),
                                name === "pria" ? "Pria" : "Wanita",
                            ]}
                            contentStyle={{
                                borderRadius: 10,
                                border: "1px solid #E1E5EA",
                                boxShadow: "0 4px 12px rgba(23,32,51,0.08)",
                            }}
                        />
                        <Legend
                            formatter={(value) =>
                                value === "pria" ? "Pria" : "Wanita"
                            }
                            iconType="circle"
                            wrapperStyle={{ fontSize: 12, color: "#687386", paddingTop: 8 }}
                        />
                        <Bar
                            dataKey="pria"
                            name="pria"
                            stackId="gender"
                            fill="url(#priaBar)"
                            maxBarSize={18}
                        />
                        <Bar
                            dataKey="wanita"
                            name="wanita"
                            stackId="gender"
                            fill="url(#wanitaBar)"
                            radius={[0, 4, 4, 0]}
                            maxBarSize={18}
                        />
                    </BarChart>
                </ResponsiveContainer>
            </div>

            <div className="flex items-center justify-center gap-6 mt-4 pt-4 border-t border-[#F0F2F5]">
                <GenderChip icon="♂" value={totalPria} size="sm" />
                <GenderChip icon="♀" value={totalWanita} size="sm" />
                <span className="text-sm font-semibold text-[#172033] tabular-nums">
                    Total: {totalAll.toLocaleString("id-ID")}
                </span>
            </div>
        </div>
    );
}

export default Home;