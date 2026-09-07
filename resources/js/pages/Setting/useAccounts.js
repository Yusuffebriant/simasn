import { useEffect, useState } from "react";
import { apiFetch } from "../../lib/api";

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
    const [rawAccounts, setRawAccounts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const [openMenu, setOpenMenu] = useState(null);
    const [editing, setEditing] = useState(null);
    const [showPassword, setShowPassword] = useState(false);
    const [newAccount, setNewAccount] = useState({ name: "", email: "", password: "" });
    const [searchTerm, setSearchTerm] = useState("");

    async function loadAccounts() {
        setLoading(true);
        setError(null);
        try {
            const res = await apiFetch("/users");
            if (!res.ok) throw new Error("Gagal memuat akun.");
            const json = await res.json();
            setRawAccounts(json.data || []);
        } catch (err) {
            setError(err.message || "Gagal memuat akun.");
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        loadAccounts();
    }, []);

    const accounts = rawAccounts
        .filter((acc) => {
            const keyword = searchTerm.trim().toLowerCase();
            if (!keyword) return true;
            return (
                acc.name.toLowerCase().includes(keyword) ||
                acc.email.toLowerCase().includes(keyword)
            );
        })
        .map((acc) => ({
            ...acc,
            initials: getInitials(acc.name),
        }));

    // ---- Tambah akun ----
    const handleAddAccount = async (e) => {
        e.preventDefault();
        if (!newAccount.name || !newAccount.email || !newAccount.password) return;

        setError(null);
        try {
            const res = await apiFetch("/users", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(newAccount),
            });
            const json = await res.json();
            if (!res.ok) {
                throw new Error(json.message || "Gagal menambah akun.");
            }
            setRawAccounts((prev) => [...prev, json.data]);
            setNewAccount({ name: "", email: "", password: "" });
            setShowPassword(false);
        } catch (err) {
            setError(err.message || "Gagal menambah akun.");
        }
    };

    // ---- Hapus akun ----
    const handleDelete = async (id, name) => {
        const yakin = window.confirm(`Hapus akun "${name}"? Tindakan ini tidak bisa dibatalkan.`);
        if (!yakin) return;

        setError(null);
        try {
            const res = await apiFetch(`/users/${id}`, { method: "DELETE" });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.message || "Gagal menghapus akun.");
            setRawAccounts((prev) => prev.filter((acc) => acc.id !== id));
            setOpenMenu(null);
        } catch (err) {
            setError(err.message || "Gagal menghapus akun.");
        }
    };

    // ---- Edit akun ----
    const startEdit = (acc) => {
        setEditing({ ...acc, password: "" });
        setOpenMenu(null);
    };

    const cancelEdit = () => setEditing(null);

    const saveEdit = async () => {
        if (!editing) return;
        setError(null);
        try {
            const payload = {
                name: editing.name,
                email: editing.email,
            };
            if (editing.password) payload.password = editing.password;

            const res = await apiFetch(`/users/${editing.id}`, {
                method: "PUT",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload),
            });
            const json = await res.json();
            if (!res.ok) throw new Error(json.message || "Gagal menyimpan perubahan.");

            setRawAccounts((prev) =>
                prev.map((acc) => (acc.id === editing.id ? json.data : acc))
            );
            setEditing(null);
        } catch (err) {
            setError(err.message || "Gagal menyimpan perubahan.");
        }
    };

    // ---- Menu titik-tiga ----
    const toggleMenu = (id) => setOpenMenu((prev) => (prev === id ? null : id));
    const closeMenu = () => setOpenMenu(null);

    // ---- Toggle lihat password ----
    const togglePasswordVisibility = () => setShowPassword((v) => !v);

    return {
        accounts,
        totalAccounts: rawAccounts.length,
        loading,
        error,
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