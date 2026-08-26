import '@vitejs/plugin-react/preamble';
import React from 'react';
import { createRoot } from 'react-dom/client';
import Home from './pages/Home/Home';
import Dashboard from './pages/Dashboard/Dashboard';
import LoginPage from './pages/Login/Login';
import Admin from './pages/Admin/Admin';
import Setting from './pages/Setting/Setting';

function isLoggedIn() {
    return !!localStorage.getItem('token');
}

function App() {
    const path = window.location.pathname;

    // Halaman publik — bisa diakses siapa saja
    if (path === '/') {
        return <Home />;
    }
    if (path === '/login') {
        // Kalau sudah login, tidak perlu lihat form login lagi
        if (isLoggedIn()) {
            window.location.replace('/dashboard');
            return null;
        }
        return <LoginPage />;
    }

    // Halaman terproteksi — wajib token
    if (path === '/dashboard') {
        if (!isLoggedIn()) {
            window.location.replace('/login');
            return null;
        }
        return <Dashboard />;
    }
    if (path === '/admin') {
        if (!isLoggedIn()) {
            window.location.replace('/login');
            return null;
        }
        return <Admin />;
    }
    
    if (path === "/setting") {
        return <Setting />;
    }

    // Default: path tidak dikenal -> ke halaman utama, BUKAN Dashboard
    return <Home />;
}

createRoot(document.getElementById('app')).render(
    <React.StrictMode>
        <App />
    </React.StrictMode>
);