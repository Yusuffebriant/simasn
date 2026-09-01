// Helper API kecil untuk SIMASN.
// Token disimpan di localStorage key "token" — sama seperti yang dipakai
// Login.jsx (saat menyimpan token setelah login) dan Dashboard.jsx.

export const API_BASE_URL =
    import.meta.env.VITE_API_BASE_URL || "/api";

const TOKEN_KEY = "token";

export function getToken() {
    return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
    localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken() {
    localStorage.removeItem(TOKEN_KEY);
}

// Wrapper fetch yang otomatis menambahkan header Authorization.
// Untuk request dengan body FormData (upload file), JANGAN set
// Content-Type manual — biarkan browser yang menentukan boundary-nya.
export async function apiFetch(path, options = {}) {
    const token = getToken();

    const headers = {
        Accept: "application/json",
        ...(options.headers || {}),
    };

    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    const res = await fetch(`${API_BASE_URL}${path}`, {
        ...options,
        headers,
    });

    if (res.status === 401) {
        clearToken();
        // TODO: ganti dengan redirect ke halaman login setelah dibuat
        window.location.href = "/login";
        throw new Error("Sesi berakhir, silakan login kembali.");
    }

    return res;
}