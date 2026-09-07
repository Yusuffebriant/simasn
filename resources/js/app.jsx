import '@vitejs/plugin-react/preamble';
import React from 'react';
import { createRoot } from 'react-dom/client';
import ErrorBoundary from './components/ErrorBoundary';
import Home from './pages/home/home';
import LoginPage from './pages/Login/Login';
import Admin from './pages/Admin/Admin';

import { isLoggedIn, hasRole, getUserRole } from './lib/api';
import Setting from './pages/Setting/Setting';

function withRedirectTo(path) {
    const target = encodeURIComponent(path);
    return `/login?redirect=${target}`;
}

// Setelah login berhasil / saat akun mencoba buka halaman yang bukan
// haknya, arahkan ke halaman "rumah" sesuai role-nya masing-masing.
// "/" (Home) itulah dashboard-nya — tidak ada halaman /dashboard terpisah.
function homeFor(role) {
    if (role === 'admin') return '/admin';
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

    // "/admin" — hanya boleh diakses role admin.
    // Viewer TIDAK boleh masuk sini, dilempar balik ke "/" (Home/dashboard).
    if (path === '/admin') {
        if (!isLoggedIn()) {
            window.location.replace(withRedirectTo('/admin'));
            return null;
        }
        if (!hasRole('admin')) {
            window.location.replace('/');
            return null;
        }
        return <Admin />;
    }

    // "/setting" — hanya boleh diakses role admin.
    if (path === '/setting') {
        if (!isLoggedIn()) {
            window.location.replace(withRedirectTo('/setting'));
            return null;
        }
        if (!hasRole('admin')) {
            window.location.replace(homeFor(getUserRole()));
            return null;
        }
        return <Setting />;
    }

    // Default: path tidak dikenal -> ke halaman utama, BUKAN Admin
    return <Home />;
}

createRoot(document.getElementById('app')).render(
    <React.StrictMode>
        <ErrorBoundary>
            <App />
        </ErrorBoundary>
    </React.StrictMode>
);