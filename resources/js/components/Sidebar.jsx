function Sidebar() {
    return (
        <aside className="w-64 min-h-screen bg-[#006A4E] text-white">

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


            <nav className="mt-5">

                <a 
                    href="/dashboard"
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

                <a 
                    href="/setting"
                    className="block px-6 py-3 hover:bg-white/20"
                >
                    Settings
                </a>

            </nav>

        </aside>
    )
}

export default Sidebar;