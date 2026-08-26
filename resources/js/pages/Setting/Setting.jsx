import Sidebar from "../../components/Sidebar";
import useAccounts from "./useAccounts";

function Setting() {
    const {
        accounts,
        totalAccounts,
        searchTerm,
        setSearchTerm,
        openMenu,
        editing,
        setEditing,
        showPassword,
        newAccount,
        setNewAccount,
        handleAddAccount,
        handleDelete,
        startEdit,
        cancelEdit,
        saveEdit,
        toggleMenu,
        closeMenu,
        togglePasswordVisibility,
    } = useAccounts();

    return (
        <div className="flex h-screen">
        <Sidebar />
        <div className="flex-1 min-w-0 bg-[#F5F7FA] h-screen overflow-y-auto text-[#1F2937]">
            {/* Topbar */}
            <div className="px-9 pt-6">
                <h1 className="inline-block text-3xl font-extrabold pb-3 border-b-[3px] border-[#D4A017]">
                    Setting
                </h1>
            </div>

            <div className="max-w-3xl mx-auto px-9 py-7">
                {/* Page heading */}
                <div className="mb-6">
                    <h2 className="text-base font-bold">Manajemen Akun</h2>
                    <p className="text-sm text-gray-500 mt-1">
                        Tambahkan akun baru untuk pegawai BKPSDM yang membutuhkan akses ke sistem.
                    </p>
                </div>

                {/* Tambah akun */}
                <div className="bg-white border border-gray-200 rounded-2xl p-6 mb-6 shadow-sm">
                    <div className="flex items-center gap-2.5 mb-5">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#006A4E" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="8.5" cy="7" r="4" />
                            <line x1="20" y1="8" x2="20" y2="14" />
                            <line x1="23" y1="11" x2="17" y2="11" />
                        </svg>
                        <h3 className="font-bold text-sm">Tambah Akun Baru</h3>
                    </div>

                    <form className="space-y-4" onSubmit={handleAddAccount}>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-semibold mb-1.5">Nama Lengkap</label>
                                <div className="relative">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="absolute left-3 top-1/2 -translate-y-1/2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                        <circle cx="12" cy="7" r="4" />
                                    </svg>
                                    <input
                                        type="text"
                                        placeholder="Contoh: Siti Aminah"
                                        value={newAccount.name}
                                        onChange={(e) => setNewAccount({ ...newAccount, name: e.target.value })}
                                        className="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2.5 text-sm outline-none focus:border-[#006A4E] focus:ring-4 focus:ring-[#E6F2EE] transition"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1.5">Email</label>
                                <div className="relative">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="absolute left-3 top-1/2 -translate-y-1/2">
                                        <path d="M22 6l-10 7L2 6" />
                                        <path d="M2 6h20v12H2z" />
                                    </svg>
                                    <input
                                        type="email"
                                        placeholder="nama@bkpsdm.jogjakota.go.id"
                                        value={newAccount.email}
                                        onChange={(e) => setNewAccount({ ...newAccount, email: e.target.value })}
                                        className="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2.5 text-sm outline-none focus:border-[#006A4E] focus:ring-4 focus:ring-[#E6F2EE] transition"
                                    />
                                </div>
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold mb-1.5">Password</label>
                            <div className="relative">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="absolute left-3 top-1/2 -translate-y-1/2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" />
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                </svg>
                                <input
                                    type={showPassword ? "text" : "password"}
                                    placeholder="Minimal 8 karakter"
                                    value={newAccount.password}
                                    onChange={(e) => setNewAccount({ ...newAccount, password: e.target.value })}
                                    className="w-full border border-gray-200 rounded-lg pl-9 pr-10 py-2.5 text-sm outline-none focus:border-[#006A4E] focus:ring-4 focus:ring-[#E6F2EE] transition"
                                />
                                <button
                                    type="button"
                                    onClick={togglePasswordVisibility}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    {showPassword ? (
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    ) : (
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.6 18.6 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a18.6 18.6 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                                            <line x1="1" y1="1" x2="23" y2="23" />
                                        </svg>
                                    )}
                                </button>
                            </div>
                        </div>

                        <div className="flex items-center gap-2 text-xs text-gray-500">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#D4A017" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="12" y1="16" x2="12" y2="12" />
                                <line x1="12" y1="8" x2="12.01" y2="8" />
                            </svg>
                            Akun baru akan menerima email verifikasi
                        </div>

                        <button
                            type="submit"
                            className="inline-flex items-center gap-2 bg-[#006A4E] hover:bg-[#00543D] text-white text-sm font-bold px-5 py-2.5 rounded-lg transition"
                        >
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                            Tambah Akun
                        </button>
                    </form>
                </div>

                {/* Akun terdaftar */}
                <div className="bg-white border border-gray-200 rounded-2xl shadow-sm">
                    <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 rounded-t-2xl">
                        <h3 className="font-bold text-sm">
                            Akun Terdaftar <span className="text-gray-400 font-medium">({totalAccounts})</span>
                        </h3>
                        <div className="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2 w-52">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6B7280" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <input
                                type="text"
                                placeholder="Cari akun..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="text-sm outline-none w-full bg-transparent"
                            />
                        </div>
                    </div>

                    {accounts.length === 0 ? (
                        <div className="px-6 py-10 text-center">
                            <p className="text-sm text-gray-400">
                                {searchTerm ? "Akun tidak ditemukan." : "Belum ada akun terdaftar."}
                            </p>
                        </div>
                    ) : (
                        accounts.map((acc) => (
                            <div
                                key={acc.email}
                                className="flex items-center gap-3.5 px-6 py-4 border-b border-gray-200 last:border-b-0"
                            >
                                <div className="w-9 h-9 rounded-full bg-[#E6F2EE] text-[#006A4E] text-xs font-bold flex items-center justify-center shrink-0">
                                    {acc.initials}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-semibold">{acc.name}</p>
                                    <p className="text-xs text-gray-500">{acc.email}</p>
                                </div>
                                <span
                                    className={`text-[11px] font-bold px-2.5 py-1 rounded-full shrink-0 ${
                                        acc.role === "Admin"
                                            ? "bg-[#FBF1DC] text-[#92720F]"
                                            : "bg-[#E6F2EE] text-[#00543D]"
                                    }`}
                                >
                                    {acc.role}
                                </span>
                                <div className="relative shrink-0">
                                    <button
                                        type="button"
                                        onClick={() => toggleMenu(acc.email)}
                                        className="w-7 h-7 rounded-md flex items-center justify-center text-gray-400 hover:bg-gray-100"
                                    >
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                            <circle cx="12" cy="5" r="1.6" />
                                            <circle cx="12" cy="12" r="1.6" />
                                            <circle cx="12" cy="19" r="1.6" />
                                        </svg>
                                    </button>

                                    {openMenu === acc.email && (
                                        <>
                                            <div className="fixed inset-0 z-10" onClick={closeMenu} />
                                            <div className="absolute right-0 top-8 z-20 w-36 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">
                                                <button
                                                    type="button"
                                                    onClick={() => startEdit(acc)}
                                                    className="w-full flex items-center gap-2 px-3.5 py-2.5 text-sm text-[#1F2937] hover:bg-[#F5F7FA] transition"
                                                >
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                        <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                    </svg>
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => handleDelete(acc.email, acc.name)}
                                                    className="w-full flex items-center gap-2 px-3.5 py-2.5 text-sm text-[#C0392B] hover:bg-red-50 transition"
                                                >
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                        <polyline points="3 6 5 6 21 6" />
                                                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                                        <path d="M10 11v6" />
                                                        <path d="M14 11v6" />
                                                        <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" />
                                                    </svg>
                                                    Hapus
                                                </button>
                                            </div>
                                        </>
                                    )}
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>

            {editing && (
                <div className="fixed inset-0 z-30 flex items-center justify-center bg-black/40 px-4">
                    <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
                        <h3 className="font-bold text-sm mb-4">Edit Akun</h3>

                        <div className="space-y-4">
                            <div>
                                <label className="block text-xs font-semibold mb-1.5">Nama Lengkap</label>
                                <input
                                    type="text"
                                    value={editing.name}
                                    onChange={(e) => setEditing({ ...editing, name: e.target.value })}
                                    className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#006A4E] focus:ring-4 focus:ring-[#E6F2EE] transition"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1.5">Email</label>
                                <input
                                    type="email"
                                    value={editing.email}
                                    onChange={(e) => setEditing({ ...editing, email: e.target.value })}
                                    className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#006A4E] focus:ring-4 focus:ring-[#E6F2EE] transition"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold mb-1.5">Role</label>
                                <select
                                    value={editing.role}
                                    onChange={(e) => setEditing({ ...editing, role: e.target.value })}
                                    className="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm outline-none focus:border-[#006A4E] focus:ring-4 focus:ring-[#E6F2EE] transition bg-white"
                                >
                                    <option value="Admin">Admin</option>
                                    <option value="Staff">Staff</option>
                                </select>
                            </div>
                        </div>

                        <div className="flex justify-end gap-2 mt-6">
                            <button
                                type="button"
                                onClick={cancelEdit}
                                className="px-4 py-2.5 rounded-lg text-sm font-semibold text-gray-500 hover:bg-gray-100 transition"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                onClick={saveEdit}
                                className="px-5 py-2.5 rounded-lg text-sm font-bold text-white bg-[#006A4E] hover:bg-[#00543D] transition"
                            >
                                Simpan
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
        </div>
    );
}

export default Setting;
