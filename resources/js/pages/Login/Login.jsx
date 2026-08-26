import { useState } from "react";
import { Mail, Lock, Eye, EyeOff, Loader2 } from "lucide-react";
import { setToken, setUser } from "../../lib/api";

// ---------------------------------------------------------------------------
// LoginPage — Portal Kepegawaian BKPSDM
//
// Kontrak backend (Laravel + Sanctum, TOKEN-based — bukan cookie/SPA):
//   1. POST /login  { email, password }   (TANPA credentials:'include',
//      TANPA /sanctum/csrf-cookie — endpoint ini tidak ada di backend)
//   2. Sukses (200) -> { user, token } -> simpan token, sertakan sebagai
//      header "Authorization: Bearer <token>" di setiap request berikutnya
//   3. 422 -> { message, errors: { email: [...] } } -> kredensial salah
//   4. 429 -> rate limit 5x/menit terlampaui
//
// Ganti API_BASE_URL, nama instansi, dan path logo sesuai kebutuhan daerahmu.
// ---------------------------------------------------------------------------

const API_BASE_URL = "/api";
const NAMA_INSTANSI = "BKPSDM";
const NAMA_INSTANSI_LENGKAP =
  "Badan Kepegawaian dan Pengembangan Sumber Daya Manusia";

export default function LoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState({});

  function validate() {
    const next = {};
    if (!email.trim()) next.email = "Email wajib diisi.";
    else if (!/^\S+@\S+\.\S+$/.test(email)) next.email = "Format email tidak valid.";
    if (!password) next.password = "Kata sandi wajib diisi.";
    setErrors(next);
    return Object.keys(next).length === 0;
  }

  async function handleSubmit(e) {
    e.preventDefault();

    if (!validate()) return;

    setLoading(true);
    setErrors({});

    try {
      // Login ke API Laravel — token-based, TIDAK pakai cookie/CSRF.
      const res = await fetch(`${API_BASE_URL}/login`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ email, password }),
      });

      const data = await res.json();

      // 422 -> validasi/kredensial salah (lihat dokumentasi bagian 1)
      if (res.status === 422) {
        const fieldErrors = {};

        Object.entries(data.errors || {}).forEach(([key, msgs]) => {
          fieldErrors[key] = msgs[0];
        });

        setErrors(fieldErrors);
        return;
      }

      // 429 -> rate limit 5 percobaan/menit terlampaui
      if (res.status === 429) {
        setErrors({ form: "Terlalu banyak percobaan, coba lagi nanti." });
        return;
      }

      // Login gagal (kasus lain)
      if (!res.ok || !data.token) {
        setErrors({
          form: data.message || "Email atau kata sandi salah.",
        });
        return;
      }

      // Simpan token + user (key konsisten lewat lib/api.js)
      setToken(data.token);
      if (data.user) setUser(data.user);

      // Kembali ke halaman yang tadi mau diakses (mis. /admin),
      // default ke /admin kalau tidak ada tujuan spesifik.
      const params = new URLSearchParams(window.location.search);
      const redirect = params.get("redirect") || "/admin";
      window.location.assign(redirect);

    } catch (err) {
      console.error("Login error:", err);

      setErrors({
        form: "Tidak dapat terhubung ke server. Coba lagi.",
      });
    } finally {
      setLoading(false);
    }
  }

  return (
    <div
      style={{
        fontFamily: "'Inter', sans-serif",
        minHeight: "640px",
        background: "#F5F7FA",
        display: "flex",
        flexDirection: "column",
        alignItems: "center",
        justifyContent: "center",
        padding: "40px 20px",
      }}
      className="bkpsdm-root"
    >
      <style>{`
        @import url('https://fonts.googleapis.com/css2?family=Source+Serif+4:wght@600;700&family=Inter:wght@400;500;600&display=swap');
        .bkpsdm-root { --navy:#006A4E; --navy-dark:#00543E; --red:#D4A017; --ink:#1F2937; --line:#DDE1E6; }
        .bkpsdm-input:focus { outline: none; border-color: var(--navy) !important; box-shadow: 0 0 0 3px rgba(0,106,78,0.14); }
        .bkpsdm-btn:hover:not(:disabled) { background: var(--navy-dark) !important; }
        .bkpsdm-btn:active:not(:disabled) { transform: translateY(1px); }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
      `}</style>

      {/* Header instansi */}
      <div style={{ display: "flex", flexDirection: "column", alignItems: "center", marginBottom: 28 }}>
        <img
          src="/logo.png"
          alt={`Logo ${NAMA_INSTANSI}`}
          style={{
            width: 64,
            height: 64,
            objectFit: "contain",
            marginBottom: 14,
          }}
        />
        <h1
          style={{
            fontFamily: "'Source Serif 4', serif",
            fontWeight: 700,
            fontSize: 22,
            color: "var(--ink)",
            margin: 0,
            textAlign: "center",
          }}
        >
          {NAMA_INSTANSI}
        </h1>
        <p style={{ fontSize: 13, color: "#5B6472", margin: "4px 0 0", textAlign: "center", maxWidth: 320 }}>
          {NAMA_INSTANSI_LENGKAP}
        </p>
      </div>

      {/* Kartu login */}
      <div
        style={{
          width: "100%",
          maxWidth: 400,
          background: "#fff",
          borderRadius: 12,
          border: "1px solid var(--line)",
          overflow: "hidden",
        }}
      >
        {/* Motif garis tipis — aksen identitas, bukan dekorasi berlebih */}
        <div style={{ display: "flex", height: 5 }}>
          <div style={{ flex: 2, background: "var(--navy)" }} />
          <div style={{ flex: 1, background: "var(--red)" }} />
          <div style={{ flex: 2, background: "var(--navy)" }} />
        </div>

        <form onSubmit={handleSubmit} noValidate style={{ padding: "32px 32px 28px" }}>
          <h2
            style={{
              fontFamily: "'Source Serif 4', serif",
              fontWeight: 600,
              fontSize: 19,
              color: "var(--ink)",
              margin: "0 0 4px",
            }}
          >
            Portal Kepegawaian
          </h2>
          <p style={{ color: "#5B6472", fontSize: 13.5, margin: "0 0 24px" }}>
            Masuk menggunakan email terdaftar.
          </p>

          {errors.form && (
            <div
              style={{
                background: "#FBEAEC",
                border: "1px solid #E8AEB6",
                color: "#8A1F30",
                fontSize: 13,
                padding: "10px 12px",
                borderRadius: 8,
                marginBottom: 18,
              }}
            >
              {errors.form}
            </div>
          )}

          {/* Email */}
          <label style={{ display: "block", fontSize: 13, fontWeight: 600, color: "var(--ink)", marginBottom: 6 }}>
            Email
          </label>
          <div style={{ position: "relative", marginBottom: errors.email ? 6 : 18 }}>
            <Mail size={16} color="#8A93A0" style={{ position: "absolute", left: 12, top: 13 }} />
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="nama@bkpsdm.go.id"
              className="bkpsdm-input"
              style={{
                width: "100%",
                boxSizing: "border-box",
                padding: "11px 12px 11px 38px",
                borderRadius: 8,
                border: `1px solid ${errors.email ? "#D8564A" : "var(--line)"}`,
                fontSize: 14,
                color: "var(--ink)",
              }}
            />
          </div>
          {errors.email && (
            <p style={{ color: "#B1362A", fontSize: 12.5, margin: "0 0 14px" }}>{errors.email}</p>
          )}

          {/* Password */}
          <label style={{ display: "block", fontSize: 13, fontWeight: 600, color: "var(--ink)", marginBottom: 6 }}>
            Kata sandi
          </label>
          <div style={{ position: "relative", marginBottom: errors.password ? 6 : 12 }}>
            <Lock size={16} color="#8A93A0" style={{ position: "absolute", left: 12, top: 13 }} />
            <input
              type={showPassword ? "text" : "password"}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="••••••••"
              className="bkpsdm-input"
              style={{
                width: "100%",
                boxSizing: "border-box",
                padding: "11px 38px 11px 38px",
                borderRadius: 8,
                border: `1px solid ${errors.password ? "#D8564A" : "var(--line)"}`,
                fontSize: 14,
                color: "var(--ink)",
              }}
            />
            <button
              type="button"
              onClick={() => setShowPassword((s) => !s)}
              aria-label={showPassword ? "Sembunyikan kata sandi" : "Tampilkan kata sandi"}
              style={{ position: "absolute", right: 10, top: 9, background: "none", border: "none", cursor: "pointer", padding: 4, display: "flex" }}
            >
              {showPassword ? <EyeOff size={16} color="#8A93A0" /> : <Eye size={16} color="#8A93A0" />}
            </button>
          </div>
          {errors.password && (
            <p style={{ color: "#B1362A", fontSize: 12.5, margin: "0 0 12px" }}>{errors.password}</p>
          )}

          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", margin: "6px 0 22px" }}>
            <label style={{ display: "flex", alignItems: "center", gap: 8, fontSize: 13.5, color: "#4A4F58", cursor: "pointer" }}>
              <input
                type="checkbox"
                checked={remember}
                onChange={(e) => setRemember(e.target.checked)}
                style={{ accentColor: "#006A4E", width: 15, height: 15 }}
              />
              Ingat saya
            </label>
            <a href="/forgot-password" style={{ fontSize: 13.5, color: "var(--navy)", fontWeight: 600, textDecoration: "none" }}>
              Lupa kata sandi?
            </a>
          </div>

          <button
            type="submit"
            disabled={loading}
            className="bkpsdm-btn"
            style={{
              width: "100%",
              padding: "12px 16px",
              borderRadius: 8,
              border: "none",
              background: "var(--navy)",
              color: "#fff",
              fontSize: 14.5,
              fontWeight: 600,
              cursor: loading ? "default" : "pointer",
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              gap: 8,
              opacity: loading ? 0.8 : 1,
              transition: "background 0.15s ease",
            }}
          >
            {loading && <Loader2 size={16} style={{ animation: "spin 0.8s linear infinite" }} />}
            {loading ? "Memproses..." : "Masuk"}
          </button>
        </form>
      </div>

      <p style={{ fontSize: 12, color: "#8A93A0", marginTop: 22, textAlign: "center" }}>
        Sistem ini hanya untuk pegawai dan admin {NAMA_INSTANSI} yang terdaftar.
        <br />
        © {new Date().getFullYear()} {NAMA_INSTANSI_LENGKAP}
      </p>
    </div>
  );
}