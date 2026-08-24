import { useState } from "react";

const initialAccounts = [
    { initials: "RW", name: "Rina Wulandari", email: "rina.wulandari@bkpsdm.jogjakota.go.id", role: "Admin" },
];

function getInitials(name) {
    return name
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join("");
}

export default function useAccounts() {
    const [accounts, setAccounts] = useState(initialAccounts);
    const [openMenu, setOpenMenu] = useState(null);
    const [editing, setEditing] = useState(null);
    const [showPassword, setShowPassword] = useState(false);
    const [newAccount, setNewAccount] = useState({ name: "", email: "", password: "" });
    const [searchTerm, setSearchTerm] = useState("");

    const filteredAccounts = accounts.filter((acc) => {
        const keyword = searchTerm.trim().toLowerCase();
        if (!keyword) return true;
        return (
            acc.name.toLowerCase().includes(keyword) ||
            acc.email.toLowerCase().includes(keyword)
        );
    });

    // ---- Tambah akun ----
    const handleAddAccount = (e) => {
        e.preventDefault();
        if (!newAccount.name || !newAccount.email || !newAccount.password) return;

        setAccounts((prev) => [
            ...prev,
            {
                initials: getInitials(newAccount.name),
                name: newAccount.name,
                email: newAccount.email,
                role: "Staff",
            },
        ]);

        setNewAccount({ name: "", email: "", password: "" });
        setShowPassword(false);
    };

    // ---- Hapus akun ----
    const handleDelete = (email, name) => {
        const yakin = window.confirm(`Hapus akun "${name}"? Tindakan ini tidak bisa dibatalkan.`);
        if (!yakin) return;
        setAccounts((prev) => prev.filter((acc) => acc.email !== email));
        setOpenMenu(null);
    };

    // ---- Edit akun ----
    const startEdit = (acc) => {
        setEditing({ ...acc, originalEmail: acc.email });
        setOpenMenu(null);
    };

    const cancelEdit = () => setEditing(null);

    const saveEdit = () => {
        setAccounts((prev) =>
            prev.map((acc) => (acc.email === editing.originalEmail ? editing : acc))
        );
        setEditing(null);
    };

    // ---- Menu titik-tiga ----
    const toggleMenu = (email) => setOpenMenu((prev) => (prev === email ? null : email));
    const closeMenu = () => setOpenMenu(null);

    // ---- Toggle lihat password ----
    const togglePasswordVisibility = () => setShowPassword((v) => !v);

    return {
        accounts: filteredAccounts,
        totalAccounts: accounts.length,
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
    };
}