import '@vitejs/plugin-react/preamble';
import React from 'react';
import { createRoot } from 'react-dom/client';
import ErrorBoundary from './components/ErrorBoundary';
import Home from './pages/home/home';
import Dashboard from './pages/Dashboard/Dashboard';
import LoginPage from './pages/Login/Login';
import Admin from './pages/Admin/Admin';

import { isLoggedIn } from './lib/api';
import Setting from './pages/Setting/Setting';

function withRedirectTo(path) {
    const target = encodeURIComponent(path);
    return `/login?redirect=${target}`;
}

function App() {
    const path = window.location.pathname;

    // "/" — halaman utama, tampilan ala-dashboard, BISA diakses tanpa login.
    // Data sensitif (rekap, dsb) tetap butuh token di endpoint aslinya —
    // halaman ini cuma menampilkan info umum + ajakan masuk sebagai admin.
    if (path === '/') {
        return <Home />;
    }

    if (path === '/login') {
        // Kalau sudah login, tidak perlu lihat form login lagi —
        // langsung ke tujuan semula (?redirect=...) atau /admin.
        if (isLoggedIn()) {
            const params = new URLSearchParams(window.location.search);
            window.location.replace(params.get('redirect') || '/admin');
            return null;
        }
        return <LoginPage />;
    }

    // "/dashboard" — versi dashboard lengkap dengan data user (protected).
    if (path === '/dashboard') {
        if (!isLoggedIn()) {
            window.location.replace(withRedirectTo('/dashboard'));
            return null;
        }
        return <Dashboard />;
    }

    // "/admin" — hanya boleh diakses setelah login. Ini yang dipicu
    // saat user klik "Admin" di sidebar dari halaman publik.
    if (path === '/admin') {
        if (!isLoggedIn()) {
            window.location.replace(withRedirectTo('/admin'));
            return null;
        }
        return <Admin />;
    }
    
    if (path === "/setting") {
        return <Setting />;
    }

    // Default: path tidak dikenal -> ke halaman utama, BUKAN Dashboard/Admin
    return <Home />;
}

createRoot(document.getElementById('app')).render(
    <React.StrictMode>
        <ErrorBoundary>
            <App />
        </ErrorBoundary>
    </React.StrictMode>
);