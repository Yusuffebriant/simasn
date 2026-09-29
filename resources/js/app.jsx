import '@vitejs/plugin-react/preamble';
import React from 'react';
import { createRoot } from 'react-dom/client';
import ErrorBoundary from './components/ErrorBoundary';
import Home from './pages/home/home';
import LoginPage from './pages/Login/Login';
import Admin from './pages/Admin/Admin';
import Statistik from './pages/Statistik/Statistik';

import { isLoggedIn, hasRole, getUserRole, verifySession } from './lib/api';
import Setting from './pages/Setting/Setting';

function withRedirectTo(path) {
    const target = encodeURIComponent(path);
    return `/login?redirect=${target}`;
}

// Setelah login berhasil / saat akun mencoba buka halaman yang bukan
// haknya, arahkan ke halaman "rumah" sesuai role-nya masing-masing.
// "/" (Home) itulah dashboard-nya — tidak ada halaman /dashboard terpisah.
// Role 'admin' sudah tidak dipakai lagi. Kendali diambil alih oleh
// super-admin (akses semua instansi) dan admin-instansi (akses instansi
// sendiri) — keduanya berhak masuk /admin dan /setting.
const ADMIN_ROLES = ['super-admin', 'admin-instansi'];

function homeFor(role) {
    if (ADMIN_ROLES.includes(role)) return '/admin';
    return '/';
}

function App() {
    const path = window.location.pathname;

    // "/" — Home, sekaligus berfungsi sebagai dashboard.
    // BISA diakses tanpa login (tampilan umum) maupun sudah login
    // (viewer & admin — semua lihat statistik di sini).
    if (path === '/') {
        return <Home />;
    }

    if (path === '/login') {
        // Kalau sudah login, tidak perlu lihat form login lagi —
        // langsung ke tujuan semula (?redirect=...) atau halaman sesuai role.
        if (isLoggedIn()) {
            const params = new URLSearchParams(window.location.search);
            window.location.replace(params.get('redirect') || homeFor(getUserRole()));
            return null;
        }
        return <LoginPage />;
    }

    // "/statistik" — statistik pejabat struktural & fungsional. Sama seperti
    // /admin dan /setting: hanya super-admin dan admin-instansi yang boleh
    // masuk. Role lain dilempar balik ke halaman rumahnya.
    if (path === '/statistik') {
        if (!isLoggedIn()) {
            window.location.replace(withRedirectTo('/statistik'));
            return null;
        }
        if (!hasRole(...ADMIN_ROLES)) {
            window.location.replace(homeFor(getUserRole()));
            return null;
        }
        return <Statistik />;
    }

    // "/admin" — boleh diakses role admin, super-admin, admin-instansi.
    // Viewer TIDAK boleh masuk sini, dilempar balik ke "/" (Home/dashboard).
    if (path === '/admin') {
        if (!isLoggedIn()) {
            window.location.replace(withRedirectTo('/admin'));
            return null;
        }
        if (!hasRole(...ADMIN_ROLES)) {
            window.location.replace('/');
            return null;
        }
        return <Admin />;
    }

    // "/setting" — boleh diakses role admin, super-admin, admin-instansi.
    if (path === '/setting') {
        if (!isLoggedIn()) {
            window.location.replace(withRedirectTo('/setting'));
            return null;
        }
        if (!hasRole(...ADMIN_ROLES)) {
            window.location.replace(homeFor(getUserRole()));
            return null;
        }
        return <Setting />;
    }

    // Default: path tidak dikenal -> ke halaman utama, BUKAN Admin
    return <Home />;
}

// Sebelum menampilkan halaman, pastikan token di browser (kalau ada) masih
// berlaku di server. Kalau sudah kedaluwarsa, data login dibersihkan dulu
// sehingga sidebar & halaman terproteksi langsung memperlakukan user sebagai
// belum login. Tanpa token, tidak ada request/penundaan sama sekali.
function Root() {
    const [ready, setReady] = React.useState(!isLoggedIn());

    React.useEffect(() => {
        if (ready) return;
        verifySession().finally(() => setReady(true));
    }, [ready]);

    if (!ready) return null;
    return <App />;
}

createRoot(document.getElementById('app')).render(
    <React.StrictMode>
        <ErrorBoundary>
            <Root />
        </ErrorBoundary>
    </React.StrictMode>
);