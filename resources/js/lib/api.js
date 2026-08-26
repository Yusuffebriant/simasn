// Helper API kecil untuk SIMASN.
// Backend pakai Sanctum TOKEN-based (bukan cookie/SPA), lihat dokumentasi
// bagian 1: tidak perlu /sanctum/csrf-cookie, tidak perlu credentials:'include'.
// Token dikirim lewat header Authorization: Bearer <token> di setiap request
// ke endpoint terproteksi.

export const API_BASE_URL =
    import.meta.env.VITE_API_BASE_URL || "/api";

// PENTING: key ini harus SAMA PERSIS di semua file yang baca/tulis token
// (Login.jsx, app.jsx, Dashboard.jsx, Admin/*). Sebelumnya ada mismatch
// ("token" vs "simasn_token") yang bikin request selalu dianggap 401.
const TOKEN_KEY = "token";
const USER_KEY = "user";

export function getToken() {
    return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
    localStorage.setItem(TOKEN_KEY, token);
}

export function getUser() {
    try {
        const raw = localStorage.getItem(USER_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

export function setUser(user) {
    localStorage.setItem(USER_KEY, JSON.stringify(user));
}

export function isLoggedIn() {
    return !!getToken();
}

export function clearAuth() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
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
        clearAuth();
        // Simpan tujuan semula supaya setelah login user diarahkan balik
        // ke halaman yang tadi mau diakses (mis. /admin).
        const redirect = encodeURIComponent(
            window.location.pathname + window.location.search
        );
        window.location.href = `/login?redirect=${redirect}`;
        throw new Error("Sesi berakhir, silakan login kembali.");
    }

    return res;
}
