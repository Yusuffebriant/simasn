function Home() {
    return (
        <div className="min-h-screen bg-[#F5F7FA] flex flex-col items-center justify-center text-center px-6">
            <img
                src="/logo.png"
                alt="Logo BKPSDM"
                className="w-20 h-20 mb-4"
            />

            <h1 className="text-2xl font-bold text-[#1F2937]">
                SIMASN — Sistem Informasi Manajemen ASN
            </h1>

            <p className="text-[#5B6472] mt-2 max-w-md">
                Pemerintah Kota Yogyakarta · Badan Kepegawaian dan Pengembangan
                Sumber Daya Manusia
            </p>

            <a
                href="/login"
                className="mt-8 bg-[#006A4E] text-white px-6 py-3 rounded-lg font-semibold hover:bg-[#00543E]"
            >
                Masuk sebagai Admin
            </a>
        </div>
    );
}

export default Home;