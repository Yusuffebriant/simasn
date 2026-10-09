import { useEffect, useRef, useState } from "react";
import { AlertTriangle } from "lucide-react";
import { apiFetch } from "../../lib/api";

const POLL_INTERVAL_MS = 2500;

// Lingkaran progres: kalau percent = null, lingkaran berputar (loading tanpa angka pasti)
function CircleProgress({ percent }) {
    const size = 140;
    const stroke = 10;
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const indeterminate = percent === null;
    const offset = indeterminate
        ? circumference * 0.75
        : circumference - (percent / 100) * circumference;

    return (
        <div
            className="relative flex items-center justify-center"
            style={{ width: size, height: size }}
        >
            <svg
                width={size}
                height={size}
                className={`-rotate-90 ${indeterminate ? "animate-spin" : ""}`}
            >
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="#E5E7EB"
                    strokeWidth={stroke}
                />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="#006A4E"
                    strokeWidth={stroke}
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    style={{ transition: "stroke-dashoffset 0.6s ease" }}
                />
            </svg>

            <span className="absolute text-2xl font-bold text-gray-800">
                {indeterminate ? "..." : `${percent}%`}
            </span>
        </div>
    );
}

function ImportProgress({ file, periode, setResult, next, back }) {

    const [phase, setPhase] = useState("uploading"); // uploading | polling | error
    const [error, setError] = useState("");
    const [progress, setProgress] = useState({ done: 0, total: 0 });
    const pollTimer = useRef(null);
    const startedRef = useRef(false); // cegah upload dobel akibat React.StrictMode (dev-only double-invoke useEffect)

    useEffect(() => {
        if (startedRef.current) return;
        startedRef.current = true;

        async function startImport() {

            if (!file || !periode) {
                setError("File atau periode tidak ditemukan. Silakan ulangi dari awal.");
                setPhase("error");
                return;
            }

            try {
                const formData = new FormData();
                formData.append("file", file);
                formData.append("periode", periode);

                const uploadRes = await apiFetch("/imports", {
                    method: "POST",
                    body: formData,
                });

                if (uploadRes.status === 422) {
                    const body = await uploadRes.json();
                    throw new Error(
                        body.message || "Validasi gagal. Periksa file dan periode."
                    );
                }

                if (uploadRes.status === 429) {
                    throw new Error("Terlalu banyak percobaan, coba lagi nanti.");
                }

                if (uploadRes.status === 403) {
                    throw new Error("Anda tidak berhak melakukan import data.");
                }

                if (!uploadRes.ok) {
                    throw new Error("Gagal mengunggah file. Coba lagi.");
                }

                const { batch_id } = await uploadRes.json();

                setPhase("polling");
                poll(batch_id);

            } catch (err) {
                setError(err.message);
                setPhase("error");
            }
        }

        async function poll(batchId) {

            try {
                const res = await apiFetch(`/imports/${batchId}`);

                if (!res.ok) {
                    throw new Error("Gagal memeriksa status import.");
                }

                const data = await res.json();

                if (data.status === "diproses") {
                    setProgress({
                        done: (data.berhasil ?? 0) + (data.gagal ?? 0),
                        total: data.total_baris ?? 0,
                    });
                    pollTimer.current = setTimeout(() => poll(batchId), POLL_INTERVAL_MS);
                    return;
                }

                // status: selesai | gagal
                setProgress((p) => ({ ...p, done: p.total }));
                setResult(data);
                next();

            } catch (err) {
                setError(err.message);
                setPhase("error");
            }
        }

        startImport();

        return () => {
            if (pollTimer.current) clearTimeout(pollTimer.current);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    if (phase === "error") {
        return (
            <div className="bg-white rounded-xl shadow p-8">
                <div className="flex items-center gap-3 text-red-700 mb-4">
                    <AlertTriangle />
                    <h2 className="text-xl font-bold">Import Gagal</h2>
                </div>

                <p className="text-gray-700 mb-6">{error}</p>

                <button
                    onClick={back}
                    className="px-5 py-3 bg-gray-300 rounded-lg"
                >
                    Kembali
                </button>
            </div>
        );
    }

    // Persen hanya bisa dihitung setelah server tahu total baris.
    // Dibatasi maksimal 99% sampai server benar-benar menyatakan selesai.
    const hasTotal = phase === "polling" && progress.total > 0;
    const percent = hasTotal
        ? Math.min(99, Math.floor((progress.done / progress.total) * 100))
        : null;

    return (
        <div className="bg-white rounded-xl shadow p-8 flex flex-col items-center text-center">

            <CircleProgress percent={percent} />

            <p className="mt-5 text-lg font-semibold text-gray-800">
                {phase === "uploading"
                    ? "Mengunggah file..."
                    : "Memproses import di server..."}
            </p>

            {hasTotal && (
                <p className="text-sm text-gray-500 mt-1">
                    {progress.done.toLocaleString("id-ID")} dari{" "}
                    {progress.total.toLocaleString("id-ID")} baris diproses
                </p>
            )}

            <p className="text-gray-500 text-sm mt-3">
                Mohon tunggu, halaman ini akan memeriksa status secara otomatis.
            </p>
        </div>
    );
}

export default ImportProgress;