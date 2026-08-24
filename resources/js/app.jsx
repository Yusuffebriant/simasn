import '@vitejs/plugin-react/preamble';
import React from 'react';
import { createRoot } from 'react-dom/client';
import Dashboard from './pages/Dashboard/Dashboard';
import Admin from './pages/Admin/Admin';
import Setting from './pages/Setting/Setting';

function App() {

    const path = window.location.pathname;


    if(path === "/admin"){
        return <Admin />;
    }
    
    if (path === "/setting") {
        return <Setting />;
    }


    return <Dashboard />;

}

createRoot(document.getElementById('app')).render(
    <React.StrictMode>
        <App />
    </React.StrictMode>
);