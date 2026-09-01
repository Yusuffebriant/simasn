import React, { useEffect, useState } from "react";
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    Cell,
} from "recharts";

const API_BASE_URL = "/api";
const PRIMARY = "#006A4E";
const ACCENT = "#D4A017";
const LINE = "#E1E5EA";
const MUTED = "#687386";

export default function Dashboard() {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    const [stats, setStats] = useState({
        totalPegawai: null,
        pns: null,
        pppk: null,
        instansi: null,
    });
    const [golonganChart, setGolonganChart] = useState([]);
    const [pendidikanChart, setPendidikanChart] = useState([]);
    const [statsLoading, setStatsLoading] = useState(true);

    // Ambil data user yang sedang login
    useEffect(() => {
        const savedUser = localStorage.getItem("user");

        if (savedUser) {
            try {
                setUser(JSON.parse(savedUser));
            } catch (error) {
                console.error("Gagal membaca data user:", error);
            }
        }

        loadUser();
        loadStats();
    }, []);

    // Ambil & agregasi data rekap golongan + pendidikan dari seluruh instansi
    async function loadStats() {
        const token = localStorage.getItem("token");
        if (!token) return;

        try {
            const [golRes, pendRes] = await Promise.all([
                fetch(`${API_BASE_URL}/rekap/golongan`, {
                    headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
                }),
                fetch(`${API_BASE_URL}/rekap/pendidikan`, {
                    headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
                }),
            ]);

            if (golRes.ok) {
                const golData = await golRes.json();

                // Total pegawai, PNS, PPPK, jumlah instansi — dijumlahkan lintas instansi
                const totalPegawai = golData.reduce((sum, d) => sum + (d.jml_total || 0), 0);
                const pns = golData.reduce((sum, d) => sum + (d.pns_total || 0), 0);
                const pppk = golData.reduce((sum, d) => sum + (d.pppk_total || 0), 0);
                const instansi = golData.length;

                setStats({ totalPegawai, pns, pppk, instansi });

                // Grafik golongan: gabungkan pns_agg (I–IV) lintas semua instansi
                const golAgg = { I: 0, II: 0, III: 0, IV: 0 };
                golData.forEach((d) => {
                    Object.entries(d.pns_agg || {}).forEach(([k, v]) => {
                        golAgg[k] = (golAgg[k] || 0) + v;
                    });
                });
                setGolonganChart(
                    Object.entries(golAgg).map(([label, jumlah]) => ({ label, jumlah }))
                );
            }

            if (pendRes.ok) {
                const pendData = await pendRes.json();

                // Grafik pendidikan: gabungkan pria+wanita per jenjang, lintas semua instansi
                const pendAgg = {};
                pendData.forEach((d) => {
                    ["pria", "wanita"].forEach((kelompok) => {
                        Object.entries(d[kelompok] || {}).forEach(([jenjang, jumlah]) => {
                            pendAgg[jenjang] = (pendAgg[jenjang] || 0) + jumlah;
                        });
                    });
                });
                setPendidikanChart(
                    Object.entries(pendAgg)
                        .filter(([, jumlah]) => jumlah > 0)
                        .map(([label, jumlah]) => ({ label, jumlah }))
                );
            }
        } catch (error) {
            console.error("Gagal memuat statistik:", error);
        } finally {
            setStatsLoading(false);
        }
    }

    async function loadUser() {
        const token = localStorage.getItem("token");

        if (!token) {
            window.location.replace("/login");
            return;
        }

        try {
            const response = await fetch(`${API_BASE_URL}/me`, {
                method: "GET",
                headers: {
                    Accept: "application/json",
                    Authorization: `Bearer ${token}`,
                },
            });

            if (response.status === 401) {
                localStorage.removeItem("token");
                localStorage.removeItem("user");
                window.location.replace("/login");
                return;
            }

            if (response.ok) {
                const data = await response.json();

                // Menyesuaikan kemungkinan response:
                // { user: {...} } atau langsung {...}
                const currentUser = data.user || data;

                setUser(currentUser);

                localStorage.setItem(
                    "user",
                    JSON.stringify(currentUser)
                );
            }
        } catch (error) {
            console.error("Gagal mengambil data user:", error);
        } finally {
            setLoading(false);
        }
    }

    // Logout
    async function handleLogout() {
        const token = localStorage.getItem("token");

        try {
            if (token) {
                await fetch(`${API_BASE_URL}/logout`, {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        Authorization: `Bearer ${token}`,
                    },
                });
            }
        } catch (error) {
            console.error("Logout error:", error);
        } finally {
            // Tetap hapus token walaupun request logout gagal
            localStorage.removeItem("token");
            localStorage.removeItem("user");

            // Kembali ke halaman login
            window.location.replace("/login");
        }
    }

    return (
        <div
            style={{
                minHeight: "100vh",
                display: "flex",
                background: "#F5F7FA",
                fontFamily: "Inter, Arial, sans-serif",
            }}
        >
            {/* SIDEBAR */}
            <aside
                style={{
                    width: 260,
                    minHeight: "100vh",
                    background: "#006A4E",
                    color: "#fff",
                    display: "flex",
                    flexDirection: "column",
                    flexShrink: 0,
                }}
            >
                {/* Logo */}
                <div
                    style={{
                        padding: "24px 20px",
                        textAlign: "center",
                        borderBottom: "1px solid rgba(255,255,255,0.15)",
                    }}
                >
                    <img
                        src="/logo.png"
                        alt="Logo BKPSDM"
                        style={{
                            width: 80,
                            height: 80,
                            objectFit: "contain",
                            marginBottom: 12,
                        }}
                    />

                    <h2
                        style={{
                            margin: 0,
                            fontSize: 17,
                            fontWeight: 700,
                        }}
                    >
                        Pemerintah Kota Yogyakarta
                    </h2>

                    <p
                        style={{
                            margin: "6px 0 0",
                            fontSize: 13,
                            opacity: 0.9,
                        }}
                    >
                        Rekapitulasi Data Kepegawaian
                    </p>
                </div>

                {/* MENU */}
                <nav
                    style={{
                        padding: "20px 12px",
                        flex: 1,
                    }}
                >
                    <a
                        href="/dashboard"
                        style={{
                            display: "block",
                            padding: "13px 14px",
                            marginBottom: 6,
                            borderRadius: 8,
                            color: "#fff",
                            textDecoration: "none",
                            background: "rgba(255,255,255,0.12)",
                            fontSize: 15,
                        }}
                    >
                        Dashboard
                    </a>

                    <a
                        href="/admin"
                        style={{
                            display: "block",
                            padding: "13px 14px",
                            marginBottom: 6,
                            borderRadius: 8,
                            color: "#fff",
                            textDecoration: "none",
                            fontSize: 15,
                        }}
                    >
                        Admin
                    </a>
                </nav>

                {/* USER + LOGOUT */}
                <div
                    style={{
                        padding: 16,
                        borderTop: "1px solid rgba(255,255,255,0.15)",
                    }}
                >
                    <div
                        style={{
                            marginBottom: 12,
                            padding: "10px 12px",
                            background: "rgba(255,255,255,0.08)",
                            borderRadius: 8,
                        }}
                    >
                        <div
                            style={{
                                fontSize: 12,
                                opacity: 0.75,
                                marginBottom: 3,
                            }}
                        >
                            Login sebagai
                        </div>

                        <div
                            style={{
                                fontSize: 14,
                                fontWeight: 600,
                                wordBreak: "break-word",
                            }}
                        >
                            {loading
                                ? "Memuat..."
                                : user?.name || user?.email || "Pengguna"}
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={handleLogout}
                        style={{
                            width: "100%",
                            padding: "11px 14px",
                            borderRadius: 8,
                            border: "1px solid rgba(255,255,255,0.35)",
                            background: "transparent",
                            color: "#fff",
                            fontSize: 14,
                            fontWeight: 600,
                            cursor: "pointer",
                        }}
                    >
                        Keluar
                    </button>
                </div>
            </aside>

            {/* MAIN CONTENT */}
            <main
                style={{
                    flex: 1,
                    padding: "40px 48px",
                    overflowX: "auto",
                }}
            >
                {/* HEADER */}
                <div
                    style={{
                        marginBottom: 35,
                    }}
                >
                    <h1
                        style={{
                            margin: 0,
                            fontSize: 32,
                            color: "#172033",
                            fontWeight: 700,
                        }}
                    >
                        Statistik Pegawai
                    </h1>

                    <div
                        style={{
                            width: 80,
                            height: 4,
                            background: "#D4A017",
                            marginTop: 15,
                            marginBottom: 15,
                        }}
                    />

                    <p
                        style={{
                            margin: 0,
                            color: "#687386",
                            fontSize: 15,
                        }}
                    >
                        Rekapitulasi data kepegawaian Pemerintah Kota
                        Yogyakarta
                    </p>
                </div>

                {/* WELCOME CARD */}
                <div
                    style={{
                        background: "#fff",
                        borderRadius: 12,
                        padding: 24,
                        border: "1px solid #E1E5EA",
                        marginBottom: 24,
                    }}
                >
                    <h2
                        style={{
                            margin: "0 0 8px",
                            fontSize: 20,
                            color: "#172033",
                        }}
                    >
                        Selamat datang
                        {user?.name ? `, ${user.name}` : ""}
                    </h2>

                    <p
                        style={{
                            margin: 0,
                            color: "#687386",
                            fontSize: 14,
                        }}
                    >
                        Gunakan menu di sebelah kiri untuk mengakses
                        dashboard dan pengelolaan data kepegawaian.
                    </p>
                </div>

                {/* STATISTIK */}
                <div
                    style={{
                        display: "grid",
                        gridTemplateColumns:
                            "repeat(auto-fit, minmax(200px, 1fr))",
                        gap: 18,
                        marginBottom: 24,
                    }}
                >
                    <StatCard
                        title="Total Pegawai"
                        value={statsLoading ? "-" : stats.totalPegawai.toLocaleString("id-ID")}
                        description="Seluruh pegawai"
                    />

                    <StatCard
                        title="PNS"
                        value={statsLoading ? "-" : stats.pns.toLocaleString("id-ID")}
                        description="Pegawai Negeri Sipil"
                    />

                    <StatCard
                        title="PPPK"
                        value={statsLoading ? "-" : stats.pppk.toLocaleString("id-ID")}
                        description="Pegawai Pemerintah"
                    />

                    <StatCard
                        title="Instansi"
                        value={statsLoading ? "-" : stats.instansi.toLocaleString("id-ID")}
                        description="Jumlah instansi"
                    />
                </div>

                {/* GRAFIK GOLONGAN & PENDIDIKAN */}
                <div style={{ display: "flex", gap: 20, flexWrap: "wrap" }}>
                    <div
                        style={{
                            background: "#fff",
                            border: "1px solid #E1E5EA",
                            borderRadius: 12,
                            padding: 22,
                            flex: "1 1 380px",
                            minWidth: 320,
                        }}
                    >
                        <h3 style={{ fontSize: 15, fontWeight: 600, color: "#172033", margin: "0 0 2px" }}>
                            Pegawai per Golongan
                        </h3>
                        <p style={{ fontSize: 12, color: MUTED, margin: "0 0 16px" }}>
                            Jumlah PNS berdasarkan golongan kepangkatan (I–IV).
                        </p>
                        <div style={{ width: "100%", height: 260 }}>
                            {statsLoading ? (
                                <ChartLoading />
                            ) : (
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={golonganChart} margin={{ top: 4, right: 8, left: -18, bottom: 0 }}>
                                        <CartesianGrid strokeDasharray="3 3" stroke={LINE} vertical={false} />
                                        <XAxis dataKey="label" tick={{ fontSize: 12, fill: MUTED }} axisLine={{ stroke: LINE }} tickLine={false} />
                                        <YAxis tick={{ fontSize: 12, fill: MUTED }} axisLine={false} tickLine={false} allowDecimals={false} />
                                        <Tooltip cursor={{ fill: "rgba(0,106,78,0.06)" }} contentStyle={{ borderRadius: 8, border: `1px solid ${LINE}`, fontSize: 12.5 }} />
                                        <Bar dataKey="jumlah" radius={[6, 6, 0, 0]} maxBarSize={44}>
                                            {golonganChart.map((_, i) => (
                                                <Cell key={i} fill={PRIMARY} />
                                            ))}
                                        </Bar>
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </div>
                    </div>

                    <div
                        style={{
                            background: "#fff",
                            border: "1px solid #E1E5EA",
                            borderRadius: 12,
                            padding: 22,
                            flex: "1 1 380px",
                            minWidth: 320,
                        }}
                    >
                        <h3 style={{ fontSize: 15, fontWeight: 600, color: "#172033", margin: "0 0 2px" }}>
                            Pegawai per Pendidikan
                        </h3>
                        <p style={{ fontSize: 12, color: MUTED, margin: "0 0 16px" }}>
                            Jumlah pegawai berdasarkan jenjang pendidikan terakhir.
                        </p>
                        <div style={{ width: "100%", height: 260 }}>
                            {statsLoading ? (
                                <ChartLoading />
                            ) : (
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={pendidikanChart} margin={{ top: 4, right: 8, left: -18, bottom: 0 }}>
                                        <CartesianGrid strokeDasharray="3 3" stroke={LINE} vertical={false} />
                                        <XAxis dataKey="label" tick={{ fontSize: 11, fill: MUTED }} axisLine={{ stroke: LINE }} tickLine={false} />
                                        <YAxis tick={{ fontSize: 12, fill: MUTED }} axisLine={false} tickLine={false} allowDecimals={false} />
                                        <Tooltip cursor={{ fill: "rgba(212,160,23,0.08)" }} contentStyle={{ borderRadius: 8, border: `1px solid ${LINE}`, fontSize: 12.5 }} />
                                        <Bar dataKey="jumlah" radius={[6, 6, 0, 0]} maxBarSize={44}>
                                            {pendidikanChart.map((_, i) => (
                                                <Cell key={i} fill={ACCENT} />
                                            ))}
                                        </Bar>
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </div>
                    </div>
                </div>
            </main>
        </div>
    );
}

function ChartLoading() {
    return (
        <div style={{ width: "100%", height: "100%", display: "flex", alignItems: "center", justifyContent: "center", color: "#9CA3AF", fontSize: 12.5 }}>
            Memuat data...
        </div>
    );
}

function StatCard({ title, value, description }) {
    return (
        <div
            style={{
                background: "#fff",
                border: "1px solid #E1E5EA",
                borderRadius: 12,
                padding: 22,
            }}
        >
            <div
                style={{
                    fontSize: 13,
                    color: "#687386",
                    marginBottom: 10,
                }}
            >
                {title}
            </div>

            <div
                style={{
                    fontSize: 30,
                    fontWeight: 700,
                    color: "#006A4E",
                    marginBottom: 5,
                }}
            >
                {value}
            </div>

            <div
                style={{
                    fontSize: 12,
                    color: "#8A93A0",
                }}
            >
                {description}
            </div>
        </div>
    );
}