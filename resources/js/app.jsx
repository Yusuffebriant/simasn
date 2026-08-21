import '@vitejs/plugin-react/preamble';
import React from 'react';
import { createRoot } from 'react-dom/client';

import Dashboard from './pages/Dashboard/Dashboard';
import LoginPage from './pages/Login/Login';

function App() {
    const path = window.location.pathname;

    if (path === '/login') {
        return <LoginPage />;
    }

    if (path === '/dashboard') {
        return <Dashboard />;
    }

    return (
        <div>
            <h1>SIMASN</h1>
            <p>React berhasil dijalankan.</p>
        </div>
    );
}

createRoot(document.getElementById('app')).render(
    <React.StrictMode>
        <App />
    </React.StrictMode>
);