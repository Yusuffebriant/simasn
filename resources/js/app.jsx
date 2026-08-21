import '@vitejs/plugin-react/preamble';
import React from 'react';
import { createRoot } from 'react-dom/client';
import Dashboard from './pages/Dashboard/Dashboard';
import LoginPage from './pages/Login/Login';
import Admin from './pages/Admin/Admin';

function App() {
    const path = window.location.pathname;
    if (path === '/login') {
        return <LoginPage />;
    }
    if (path === '/dashboard') {
        return <Dashboard />;
    }
    if (path === '/admin') {
        return <Admin />;
    }
    return <Dashboard />;
}

createRoot(document.getElementById('app')).render(
    <React.StrictMode>
        <App />
    </React.StrictMode>
);