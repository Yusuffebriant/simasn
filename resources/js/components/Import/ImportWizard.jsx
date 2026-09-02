import { useState } from "react";

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

    return (
        <div>

            <h2 className="text-2xl font-bold mb-6">
                Import Data ASN
            </h2>

            {/* STEP BAR */}
            <div className="flex gap-3 mb-8">
                {STEPS.map((item, index) => (
                    <div
                        key={item}
                        className={`
                            px-4 py-2 rounded-lg
                            ${step === index + 1
                                ? "bg-[#006A4E] text-white"
                                : "bg-gray-200"}
                        `}
                    >
                        {index + 1}. {item}
                    </div>
                ))}
            </div>

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