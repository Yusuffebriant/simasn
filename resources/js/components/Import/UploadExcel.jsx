import { useRef, useState } from "react";
import * as XLSX from "xlsx";
import {
    LoaderCircle,
    UploadCloud,
    FileSpreadsheet,
    CheckCircle2,
    AlertTriangle,
    Info,
} from "lucide-react";


function UploadExcel({

    setFile,
    setHeaders,
    setPreview,
    next

}) {


    const [loading,setLoading] = useState(false);

    const [filename,setFilename] = useState("");

    const [error,setError] = useState("");

    const [dragOver,setDragOver] = useState(false);

    const inputRef = useRef(null);





    function processFile(selectedFile){



        if(!selectedFile){

            return;

        }




        if(

            !selectedFile.name.toLowerCase().endsWith(".xlsx")

            &&

            !selectedFile.name.toLowerCase().endsWith(".xls")

        ){

            setError(
                "File harus berupa Excel (.xlsx/.xls)"
            );

            return;

        }


        const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10MB, sesuai batas backend

        if(selectedFile.size > MAX_SIZE_BYTES){

            setError(
                "Ukuran file maksimal 10MB."
            );

            return;

        }



        setError("");

        setFilename(
            selectedFile.name
        );


        setFile(
            selectedFile
        );



        setLoading(true);






        const reader = new FileReader();






        reader.onload = (event)=>{


            try{


                const workbook = XLSX.read(

                    event.target.result,

                    {

                        type:"binary",

                        cellDates:true

                    }

                );





                const worksheet =

                    workbook.Sheets[

                        workbook.SheetNames[0]

                    ];







                const rows =

                    XLSX.utils.sheet_to_json(

                        worksheet,

                        {

                            header:1,

                            raw:false

                        }

                    );







                /*
                    Cari header Excel SIMPEG

                    contoh:

                    NO
                    ID
                    NIP
                    NAMA
                    JENIS_KEDUDUKAN

                */



                let headerIndex = -1;





                rows.forEach(

                    (row,index)=>{


                        const text = row

                            .join(" ")

                            .toUpperCase();





                        if(

                            text.includes("NO")

                            &&

                            text.includes("ID")

                            &&

                            text.includes("NIP")

                            &&

                            text.includes("NAMA")

                        ){

                            headerIndex=index;

                        }


                    }

                );







                if(headerIndex === -1){


                    throw new Error(

                        "Header Excel SIMPEG tidak ditemukan"

                    );


                }








                /*
                    Ambil nama field/header

                    contoh:

                    NIP
                    NAMA
                    UNIT

                */



                const headers =

                    rows[headerIndex]

                    .map(

                        item =>

                        String(item)

                        .trim()

                        .replace(/\s+/g,"_")

                    );








                let startRow =

                    headerIndex + 1;







                /*
                    Buang baris nomor atribut

                    contoh:

                    1 2 3 4 5

                */


                const possibleIndexRow =

                    rows[startRow];





                if(possibleIndexRow){



                    const fakeRow =

                    possibleIndexRow.every(

                        item =>

                        item !== undefined

                        &&

                        item !== ""

                        &&

                        !isNaN(item)

                    );





                    if(fakeRow){

                        startRow++;

                    }


                }









                /*
                    Ambil 10 data pertama

                */



                const previewData =

                    rows.slice(

                        startRow,

                        startRow + 10

                    );








                console.log(
                    "HEADER : ",
                    headers
                );


                console.log(
                    "PREVIEW : ",
                    previewData
                );







                /*
                    Kirim ke ImportWizard

                */


                setHeaders(

                    headers

                );



                setPreview(

                    previewData

                );







                /*
                    otomatis lanjut Preview

                */


                setTimeout(()=>{


                    next();


                },500);






            }


            catch(err){



                console.error(err);



                setError(

                    err.message

                );


            }





            finally{


                setLoading(false);


            }



        };









        reader.readAsBinaryString(

            selectedFile

        );




    }









    function handleUpload(e){

        processFile(e.target.files[0]);

        // reset agar file yang sama bisa dipilih ulang setelah error
        e.target.value = "";

    }

    function handleDrop(e){

        e.preventDefault();

        setDragOver(false);

        if(loading){
            return;
        }

        processFile(e.dataTransfer.files?.[0]);

    }

    function openPicker(){

        if(!loading){
            inputRef.current?.click();
        }

    }

    return (
        <div className="bg-white rounded-xl shadow p-8">

            <div className="flex items-center gap-3 mb-2">
                <UploadCloud size={32} className="text-[#006A4E]" />
                <h2 className="text-xl font-bold">
                    Upload Excel SIMPEG
                </h2>
            </div>

            <p className="text-gray-600 mb-6">
                Pilih file Excel mentah dari SIMPEG untuk proses import data ASN.
            </p>

            {/* AREA UPLOAD */}
            <input
                ref={inputRef}
                type="file"
                accept=".xlsx,.xls"
                onChange={handleUpload}
                className="hidden"
            />

            <div
                role="button"
                tabIndex={0}
                onClick={openPicker}
                onKeyDown={(e) => {
                    if (e.key === "Enter" || e.key === " ") {
                        e.preventDefault();
                        openPicker();
                    }
                }}
                onDragOver={(e) => {
                    e.preventDefault();
                    setDragOver(true);
                }}
                onDragLeave={() => setDragOver(false)}
                onDrop={handleDrop}
                className={`
                    flex flex-col items-center justify-center text-center
                    rounded-xl border-2 border-dashed px-6 py-12 transition
                    ${loading ? "cursor-wait" : "cursor-pointer"}
                    ${dragOver
                        ? "border-[#006A4E] bg-emerald-50"
                        : "border-gray-300 bg-gray-50 hover:border-[#006A4E] hover:bg-emerald-50"}
                `}
            >
                {loading ? (
                    <>
                        <LoaderCircle size={48} className="animate-spin text-[#006A4E] mb-4" />
                        <p className="font-semibold text-gray-700">
                            Membaca file Excel...
                        </p>
                        <p className="text-sm text-gray-500 mt-1">
                            Mohon tunggu sebentar
                        </p>
                    </>
                ) : (
                    <>
                        <div className="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mb-4">
                            <FileSpreadsheet size={32} className="text-[#006A4E]" />
                        </div>

                        <p className="text-lg font-semibold text-gray-800">
                            {dragOver
                                ? "Lepaskan file di sini"
                                : "Klik di sini untuk memilih file Excel"}
                        </p>

                        <p className="text-gray-500 mt-1">
                            atau seret dan lepas file ke kotak ini
                        </p>

                        <span className="mt-5 inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-[#006A4E] text-white font-medium">
                            <UploadCloud size={18} />
                            Pilih File Excel
                        </span>

                        <p className="text-xs text-gray-500 mt-4">
                            Format: .xlsx atau .xls &middot; Ukuran maksimal 10 MB
                        </p>
                    </>
                )}
            </div>

            {/* FILE TERPILIH */}
            {filename && !error && (
                <div className="mt-4 flex items-center gap-3 rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-emerald-900">
                    <CheckCircle2 size={20} className="flex-none" />
                    <span className="text-sm break-all">
                        File dipilih: <b>{filename}</b>
                    </span>
                </div>
            )}

            {/* ERROR */}
            {error && (
                <div
                    role="alert"
                    className="mt-4 flex items-start gap-3 rounded-lg bg-red-50 border border-red-200 p-3 text-red-700"
                >
                    <AlertTriangle size={20} className="flex-none mt-0.5" />
                    <div className="text-sm">
                        <p>{error}</p>
                        <p className="mt-1 text-red-600">
                            Silakan pilih file lain dan coba lagi.
                        </p>
                    </div>
                </div>
            )}

            {/* CATATAN */}
            <div className="mt-5 flex items-start gap-2 text-sm text-gray-500">
                <Info size={16} className="flex-none mt-0.5" />
                <span>
                    Gunakan file asli hasil ekspor SIMPEG tanpa mengubah nama kolom.
                    Pada tahap berikutnya Anda bisa memeriksa data sebelum benar-benar diimport.
                </span>
            </div>

        </div>
    );
}

export default UploadExcel;