import { isLoggedIn, getUser, clearAuth, apiFetch } from "../lib/api";

// Sidebar dipakai di halaman publik (/) maupun halaman terproteksi
// (/dashboard, /admin). Link "Admin" selalu mengarah ke /admin — kalau
// user belum login, app.jsx yang akan redirect ke /login (lihat app.jsx).
function Sidebar() {
    const loggedIn = isLoggedIn();
    const user = getUser();

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
        <aside className="w-64 min-h-screen bg-[#006A4E] text-white flex flex-col">

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


            <nav className="mt-5 flex-1">

                <a
                    href="/"
                    className="block px-6 py-3 hover:bg-white/20"
                >
                    Dashboard
                </a>


                <a
                    href="/admin"
                    className="block px-6 py-3 hover:bg-white/20"
                >
                    Admin
                </a>

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
                    <a
                        href="/login"
                        className="block text-center w-full text-sm font-semibold border border-white/40 rounded-lg py-2 hover:bg-white/10"
                    >
                        Masuk sebagai Admin
                    </a>
                )}
            </div>

        </aside>
    )
}

export default Sidebar;
