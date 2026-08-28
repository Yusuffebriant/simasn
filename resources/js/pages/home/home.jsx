import Sidebar from "../../components/Sidebar";
import { isLoggedIn, getUser } from "../../lib/api";

// Halaman ini dirender di "/" — bisa diakses SIAPA SAJA tanpa login.
// Semua endpoint data (rekap, pegawai, dll) di backend wajib Bearer token
// (lihat dokumentasi bagian 2 & 6), jadi di sini kartu statistik sengaja
// ditampilkan dalam kondisi "terkunci" dan mengarahkan ke /admin untuk
// login, bukan memanggil endpoint terproteksi tanpa token.
//
// Kalau user SUDAH login (token ada di localStorage), sapaan diganti
// menampilkan nama user, dan tombol jadi "Buka Panel Admin".
function Home() {
    const loggedIn = isLoggedIn();
    const user = getUser();
    const displayName = user?.name || user?.email;

    return (
        <div className="flex min-h-screen bg-[#F5F7FA]">
            <Sidebar />

            <main className="flex-1 p-10 overflow-x-auto">
                <div className="mb-9">
                    <h1 className="text-3xl font-bold text-[#172033]">
                        SIMASN — Sistem Informasi Manajemen ASN
                    </h1>
                    <div className="w-20 h-1 bg-[#D4A017] mt-4 mb-4" />
                    <p className="text-[#687386] text-[15px]">
                        Pemerintah Kota Yogyakarta · Badan Kepegawaian dan
                        Pengembangan Sumber Daya Manusia
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
                                ? "Lanjutkan ke panel admin untuk mengelola dan melihat data kepegawaian."
                                : "Masuk sebagai admin untuk mengelola dan melihat data kepegawaian secara lengkap."}
                        </p>
                    </div>
                    <a
                        href="/admin"
                        className="bg-[#006A4E] text-white px-6 py-3 rounded-lg font-semibold hover:bg-[#00543E] whitespace-nowrap"
                    >
                        {loggedIn ? "Buka Panel Admin" : "Masuk sebagai Admin"}
                    </a>
                </div>

                <div className="grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))" }}>
                    <LockedStatCard title="Total Pegawai" />
                    <LockedStatCard title="PNS" />
                    <LockedStatCard title="PPPK" />
                    <LockedStatCard title="Instansi" />
                </div>
            </main>
        </div>
    );
}

function LockedStatCard({ title }) {
    return (
        <div className="bg-white border border-[#E1E5EA] rounded-xl p-5">
            <div className="text-[13px] text-[#687386] mb-2">{title}</div>
            <div className="text-3xl font-bold text-[#B8BFC9] mb-1">••</div>
            <div className="text-xs text-[#8A93A0]">Masuk untuk melihat data</div>
        </div>
    );
}

export default Home;
