import { useEffect, useState } from "react";
import {
    Bar,
    BarChart,
    CartesianGrid,
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
                        <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(340px, 1fr))" }}>
                            <ChartCard title="Golongan">
                                <BarChart data={data.golongan}>
                                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#E1E5EA" />
                                    <XAxis dataKey="label" tick={{ fontSize: 12, fill: "#687386" }} axisLine={{ stroke: "#E1E5EA" }} tickLine={false} />
                                    <YAxis allowDecimals={false} tick={{ fontSize: 12, fill: "#687386" }} axisLine={false} tickLine={false} />
                                    <Tooltip formatter={(value) => [value, "Jumlah Pegawai"]} contentStyle={{ borderRadius: 8, border: "1px solid #E1E5EA" }} />
                                    <Bar dataKey="jumlah" name="Jumlah Pegawai" fill="#0F6E6E" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ChartCard>

                            <ChartCard title="Pendidikan">
                                <BarChart data={data.pendidikan} layout="vertical" margin={{ left: 10 }}>
                                    <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke="#E1E5EA" />
                                    <XAxis type="number" allowDecimals={false} tick={{ fontSize: 12, fill: "#687386" }} axisLine={{ stroke: "#E1E5EA" }} tickLine={false} />
                                    <YAxis type="category" dataKey="label" width={80} tick={{ fontSize: 12, fill: "#687386" }} axisLine={false} tickLine={false} />
                                    <Tooltip formatter={(value) => [value, "Jumlah Pegawai"]} contentStyle={{ borderRadius: 8, border: "1px solid #E1E5EA" }} />
                                    <Bar dataKey="jumlah" name="Jumlah Pegawai" fill="#4FA6A6" radius={[0, 4, 4, 0]} />
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

function GenderChip({ icon, value, size = "default" }) {
    const sizeClass =
        size === "sm"
            ? "gap-1 px-2 py-0.5 text-[11px]"
            : "gap-1 px-2.5 py-1 text-xs";

    return (
        <span
            className={`inline-flex items-center bg-black/10 rounded-md font-semibold ${sizeClass}`}
        >
            <span aria-hidden>{icon}</span>
            {value.toLocaleString("id-ID")}
        </span>
    );
}

function TotalCard({ title, total, pria, wanita }) {
    return (
        <div className="bg-[#172033] text-white rounded-xl p-5">
            <div className="text-[13px] text-white/70 mb-2">{title}</div>
            <div className="text-4xl font-bold mb-4">{total.toLocaleString("id-ID")}</div>
            <div className="flex gap-2">
                <GenderChip icon="♂" value={pria} />
                <GenderChip icon="♀" value={wanita} />
            </div>
        </div>
    );
}

function MiniStatCard({ title, total, pria, wanita }) {
    const hasGender = typeof pria === "number" && typeof wanita === "number";
    return (
        <div className="bg-[#E7F1FB] border border-[#D3E5F5] rounded-xl p-5">
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
        <div className="bg-[#CBE7F0] border border-[#B5DBE8] rounded-xl p-5">
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
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5">
            <h3 className="text-[#172033] font-semibold mb-4">
                {title}
            </h3>
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
                    {data.map((row) => (
                        <tr
                            key={row.label}
                            className="border-b border-[#F0F2F5] hover:bg-[#F5F7FA]"
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
                    <tr className="border-t-2 border-[#E1E5EA]">
                        <td className="py-2.5 pr-2 text-xs font-normal text-[#172033]">
                            Total
                        </td>
                        <td className="py-2.5 px-1 text-center text-xs font-normal text-[#172033] tabular-nums">
                            {totalPria.toLocaleString("id-ID")}
                        </td>
                        <td className="py-2.5 px-1 text-center text-xs font-normal text-[#172033] tabular-nums">
                            {totalWanita.toLocaleString("id-ID")}
                        </td>
                        <td className="py-2.5 pl-1 text-right text-xs font-normal text-[#172033] tabular-nums">
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
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5">
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

function ChartCard({ title, children }) {
    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5">
            <h3 className="text-[#172033] font-semibold mb-4">{title}</h3>
            <div style={{ width: "100%", height: 280 }}>
                <ResponsiveContainer>{children}</ResponsiveContainer>
            </div>
        </div>
    );
}

export default Home;