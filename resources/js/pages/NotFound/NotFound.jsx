// Halaman 404 — ditampilkan untuk alamat yang tidak dikenal (lihat bagian
// default di app.jsx).
export default function NotFound() {
    return (
        <div className="min-h-screen bg-gray-100 flex flex-col items-center justify-center px-6 text-center">
            <h1 className="text-8xl font-extrabold text-[#006A4E]">404</h1>

            <div className="w-20 h-1 bg-[#D4A017] mt-5 mb-5" />

            <h2 className="text-2xl font-bold text-[#172033]">
                Halaman tidak ditemukan
            </h2>
            <p className="mt-2 text-[#687386]">
                Alamat yang Anda buka tidak tersedia.
            </p>

            <a
                href="/"
                className="mt-8 px-6 py-2.5 rounded-lg bg-[#006A4E] text-white text-sm font-semibold hover:bg-[#005a41] transition-colors"
            >
                Kembali ke Dashboard
            </a>
        </div>
    );
}