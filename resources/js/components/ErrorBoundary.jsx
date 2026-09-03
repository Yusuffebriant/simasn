import { Component } from "react";

// Menangkap error render yang tidak tertangani supaya tidak menampilkan
// layar putih kosong tanpa pesan. Dipasang membungkus <App /> di app.jsx.
class ErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true, error };
    }

    componentDidCatch(error, info) {
        // Tetap log ke console supaya stack trace lengkap masih bisa
        // dilihat saat debugging.
        console.error("Unhandled render error:", error, info?.componentStack);
    }

    handleReload = () => {
        window.location.reload();
    };

    render() {
        if (this.state.hasError) {
            return (
                <div
                    style={{
                        minHeight: "100vh",
                        display: "flex",
                        flexDirection: "column",
                        alignItems: "center",
                        justifyContent: "center",
                        gap: 16,
                        padding: 24,
                        textAlign: "center",
                        fontFamily: "Inter, Arial, sans-serif",
                        background: "#F5F7FA",
                    }}
                >
                    <h1 style={{ fontSize: 22, color: "#172033", margin: 0 }}>
                        Terjadi kesalahan saat memuat halaman
                    </h1>
                    <p style={{ color: "#687386", maxWidth: 420, margin: 0 }}>
                        {this.state.error?.message ||
                            "Silakan muat ulang halaman. Kalau masih terjadi, hubungi admin."}
                    </p>
                    <button
                        type="button"
                        onClick={this.handleReload}
                        style={{
                            padding: "10px 20px",
                            borderRadius: 8,
                            border: "none",
                            background: "#006A4E",
                            color: "#fff",
                            fontWeight: 600,
                            cursor: "pointer",
                        }}
                    >
                        Muat Ulang
                    </button>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;