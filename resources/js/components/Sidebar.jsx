import { useLayoutEffect, useRef, useState } from "react";
import { isLoggedIn, getUser, clearAuth, apiFetch, hasRole } from "../lib/api";

// Sidebar dipakai di halaman publik (/) maupun halaman terproteksi
// (/dashboard, /admin). Link "Admin" selalu mengarah ke /admin — kalau
// user belum login, app.jsx yang akan redirect ke /login (lihat app.jsx).
//
// Perilaku sidebar (auto-hide dock):
// - Sidebar "mengambang" (position: fixed) di atas konten, jadi TIDAK
//   ikut ke-scroll dan TIDAK mendorong-dorong lebar konten utama.
// - Arahkan mouse ke tepi kiri layar -> sidebar otomatis muncul (slide-in).
// - Geser mouse menjauhi sidebar -> sidebar otomatis menutup sendiri
//   (slide-out).
//
// Menu navigasi punya kotak highlight statis untuk menu yang sedang
// aktif (sudut sedikit melengkung, tidak dibuat terlalu bulat). Saat
// mouse berada di atas sebuah menu, teksnya jadi tebal — tanpa kotak
// warna yang mengikuti mouse (dihapus karena terasa berat).
function Sidebar() {
    const loggedIn = isLoggedIn();
    const user = getUser();
    const [open, setOpen] = useState(false);
    const [hoveredHref, setHoveredHref] = useState(null);
    const [activeRect, setActiveRect] = useState(null);

    const navRef = useRef(null);
    const itemRefs = useRef({});

    const currentPath =
        typeof window !== "undefined" ? window.location.pathname : "";

    const navItems = [
        { href: "/", label: "Dashboard", show: true },
        {
            href: "/admin",
            label: "Admin",
            show: hasRole("super-admin", "admin-instansi"),
        },
        { href: "/statistik", label: "Statistik", show: loggedIn },
        {
            href: "/setting",
            label: "Settings",
            show: hasRole("super-admin", "admin-instansi"),
        },
    ].filter((item) => item.show);

    function measureActive() {
        const activeItem = navItems.find((item) => item.href === currentPath);
        const el = activeItem && itemRefs.current[activeItem.href];
        if (el) {
            setActiveRect({ top: el.offsetTop, height: el.offsetHeight });
        } else {
            setActiveRect(null);
        }
    }

    // Ukur posisi menu aktif setelah render pertama, dan ukur ulang kalau
    // ukuran layar berubah (posisi menu bisa bergeser).
    useLayoutEffect(() => {
        measureActive();
        window.addEventListener("resize", measureActive);
        return () => window.removeEventListener("resize", measureActive);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    async function handleLogout(e) {
        e.preventDefault();
        try {
            await apiFetch("/logout", { method: "POST" });
        } catch {
            // tetap lanjut hapus token walau request logout gagal
        } finally {
            clearAuth();
            window.location.assign("/");
        }
    }

    return (
        <>
            {/* Zona pemicu hover di tepi kiri layar. Geser mouse ke sini
                untuk memunculkan sidebar. Hanya berguna saat sidebar
                tertutup (saat terbuka, sidebar sendiri yang menutupi area
                ini). */}
            <div
                onMouseEnter={() => setOpen(true)}
                className="fixed top-0 left-0 h-screen w-3 z-40"
                aria-hidden="true"
            />

            <aside
                onMouseLeave={() => setOpen(false)}
                className={`fixed top-0 left-0 h-screen w-64 z-40 bg-[#006A4E] text-white flex flex-col overflow-y-auto overflow-x-hidden shadow-2xl transition-transform duration-300 ease-in-out ${
                    open ? "translate-x-0" : "-translate-x-full"
                }`}
            >

                <div className="p-5 border-b border-white/20">
                    <img
                        src="/logo.png"
                        className="w-16 mx-auto"
                    />

                    <a href="https://bkpsdm.jogjakota.go.id/" target="_blank">
                        <h2 className="text-center mt-3 font-bold">
                            Pemerintah Kota Yogyakarta
                        </h2>

                        <p className="text-center text-sm">
                            Rekapitulasi Data Kepegawaian
                        </p>
                    </a>
                </div>


                <nav
                    ref={navRef}
                    className="mt-5 flex-1 relative px-3"
                >
                    {/* Kotak statis penanda menu yang sedang aktif */}
                    <div
                        className="absolute left-3 right-3 rounded-lg bg-white/10 pointer-events-none transition-all duration-200 ease-out"
                        style={{
                            top: activeRect ? activeRect.top : 0,
                            height: activeRect ? activeRect.height : 0,
                            opacity: activeRect ? 1 : 0,
                        }}
                    />

                    {navItems.map((item) => {
                        const isActive = item.href === currentPath;
                        const isHovered = item.href === hoveredHref;
                        return (
                            <a
                                key={item.href}
                                href={item.href}
                                ref={(el) => (itemRefs.current[item.href] = el)}
                                onMouseEnter={() => setHoveredHref(item.href)}
                                onMouseLeave={() => setHoveredHref(null)}
                                className={`relative z-10 block px-3 py-3 rounded-lg ${
                                    isActive || isHovered ? "font-bold" : ""
                                }`}
                            >
                                {item.label}
                            </a>
                        );
                    })}

                </nav>

                <div className="p-4 border-t border-white/20">
                    {loggedIn ? (
                        <>
                            <div className="text-xs opacity-75 mb-2 px-2">
                                Login sebagai{" "}
                                <span className="font-semibold">
                                    {user?.name || user?.email || "Pengguna"}
                                </span>
                            </div>
                            <button
                                type="button"
                                onClick={handleLogout}
                                className="w-full text-sm font-semibold border border-white/40 rounded-lg py-2 hover:bg-white/10"
                            >
                                Keluar
                            </button>
                        </>
                    ) : (

                         <a   href="/login"
                            className="block text-center w-full text-sm font-semibold border border-white/40 rounded-lg py-2 hover:bg-white/10"
                        >
                            Masuk sebagai Admin
                        </a>
                    )}
                </div>

            </aside>
        </>
    )
}

export default Sidebar;