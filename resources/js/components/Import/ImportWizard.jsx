import { useEffect, useState } from "react";

import UploadExcel from "./UploadExcel";
import PreviewExcel from "./PreviewExcel";
import ImportProgress from "./ImportProgress";
import ImportResult from "./ImportResult";
import { getCurrentPeriode } from "../../lib/periode";


const STEPS = ["Upload", "Preview", "Import", "Selesai"];


function ImportWizard() {

    const [step, setStep] = useState(1);

    const [file, setFile] = useState(null);
    const [headers, setHeaders] = useState([]);
    const [preview, setPreview] = useState([]);
    // Default awal periode = bulan berjalan, tapi pengguna bisa mengubahnya
    // di step Preview (mis. saat mengimport data historis seperti Juli 2026
    // atau April 2025) — lihat PreviewExcel.jsx.
    const [periode, setPeriode] = useState(getCurrentPeriode());

    // hasil akhir import, diisi oleh ImportProgress setelah polling selesai
    const [result, setResult] = useState(null);

    // pemberitahuan saat pengguna klik step yang belum boleh dibuka
    const [notice, setNotice] = useState("");

    useEffect(() => {
        if (!notice) return;
        const t = setTimeout(() => setNotice(""), 3500);
        return () => clearTimeout(t);
    }, [notice]);

    const hasFile = !!file && headers.length > 0;

    // Mengembalikan pesan penolakan, atau null jika step boleh dibuka
    function getBlockedMessage(target) {
        if (target === step) return "";

        if (step === 3) {
            return "Import sedang berjalan, mohon tunggu sampai selesai.";
        }

        if (step === 4) {
            return "Import sudah selesai. Klik \"Import File Lain\" untuk memulai import baru.";
        }

        if (target === 1) return null;

        if (!hasFile) {
            return "Harus upload file dulu sebelum membuka tahap ini.";
        }

        if (target === 3) {
            return "Buka tahap Preview dulu, lalu klik lanjut untuk memulai import.";
        }

        if (target === 4) {
            return "Import belum dijalankan.";
        }

        return null; // target === 2 dan file sudah ada
    }

    function handleStepClick(target) {
        const message = getBlockedMessage(target);

        if (message) {
            setNotice(message);
            return;
        }

        setNotice("");
        setStep(target);
    }

    return (
        <div>

            <h2 className="text-2xl font-bold mb-6">
                Import Data ASN
            </h2>

            {/* STEP BAR */}
            <div className="flex gap-3 mb-4">
                {STEPS.map((item, index) => {
                    const target = index + 1;
                    const active = step === target;
                    const blocked = !active && !!getBlockedMessage(target);

                    return (
                        <button
                            type="button"
                            key={item}
                            onClick={() => handleStepClick(target)}
                            aria-disabled={blocked}
                            className={`
                                px-4 py-2 rounded-lg transition
                                ${active
                                    ? "bg-[#006A4E] text-white"
                                    : blocked
                                        ? "bg-gray-200 text-gray-400 cursor-not-allowed"
                                        : "bg-gray-200 hover:bg-gray-300"}
                            `}
                        >
                            {target}. {item}
                        </button>
                    );
                })}
            </div>

            {notice && (
                <div
                    role="alert"
                    className="mb-6 px-4 py-3 rounded-lg bg-amber-50 border border-amber-300 text-amber-800 text-sm"
                >
                    {notice}
                </div>
            )}

            {!notice && <div className="mb-4" />}

            {step === 1 && (
                <UploadExcel
                    setFile={setFile}
                    setHeaders={setHeaders}
                    setPreview={setPreview}
                    next={() => setStep(2)}
                />
            )}

            {step === 2 && (
                <PreviewExcel
                    file={file}
                    headers={headers}
                    preview={preview}
                    periode={periode}
                    setPeriode={setPeriode}
                    next={() => setStep(3)}
                    back={() => setStep(1)}
                />
            )}

            {step === 3 && (
                <ImportProgress
                    file={file}
                    periode={periode}
                    setResult={setResult}
                    next={() => setStep(4)}
                    back={() => setStep(2)}
                />
            )}

            {step === 4 && (
                <ImportResult
                    result={result}
                    onRestart={() => {
                        setStep(1);
                        setFile(null);
                        setHeaders([]);
                        setPreview([]);
                        setPeriode(getCurrentPeriode());
                        setResult(null);
                    }}
                />
            )}

        </div>
    );
}

export default ImportWizard;