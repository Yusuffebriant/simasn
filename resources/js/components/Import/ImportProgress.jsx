import { useEffect, useRef, useState } from "react";
import { LoaderCircle, AlertTriangle } from "lucide-react";
import { apiFetch } from "../../lib/api";

const POLL_INTERVAL_MS = 2500;

function ImportProgress({ file, periode, setResult, next, back }) {

    const [phase, setPhase] = useState("uploading"); // uploading | polling | error
    const [error, setError] = useState("");
    const pollTimer = useRef(null);

    useEffect(() => {
        let cancelled = false;

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

                if (cancelled) return;

                setPhase("polling");
                poll(batch_id);

            } catch (err) {
                if (!cancelled) {
                    setError(err.message);
                    setPhase("error");
                }
            }
        }

        async function poll(batchId) {

            try {
                const res = await apiFetch(`/imports/${batchId}`);

                if (!res.ok) {
                    throw new Error("Gagal memeriksa status import.");
                }

                const data = await res.json();

                if (cancelled) return;

                if (data.status === "diproses") {
                    pollTimer.current = setTimeout(() => poll(batchId), POLL_INTERVAL_MS);
                    return;
                }

                // status: selesai | gagal
                setResult(data);
                next();

            } catch (err) {
                if (!cancelled) {
                    setError(err.message);
                    setPhase("error");
                }
            }
        }

        startImport();

        return () => {
            cancelled = true;
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

    return (
        <div className="bg-white rounded-xl shadow p-8">
            <div className="flex items-center gap-3 text-gray-700">
                <LoaderCircle className="animate-spin" />
                <span>
                    {phase === "uploading"
                        ? "Mengunggah file..."
                        : "Memproses import di server..."}
                </span>
            </div>

            <p className="text-gray-500 text-sm mt-3">
                Mohon tunggu, halaman ini akan memeriksa status secara otomatis.
            </p>
        </div>
    );
}

export default ImportProgress;
