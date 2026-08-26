import { useState } from "react";
import * as XLSX from "xlsx";
import { LoaderCircle, UploadCloud } from "lucide-react";


function UploadExcel({

    setFile,
    setHeaders,
    setPreview,
    next

}) {


    const [loading,setLoading] = useState(false);

    const [filename,setFilename] = useState("");

    const [error,setError] = useState("");





    function handleUpload(e){


        const selectedFile = e.target.files[0];



        if(!selectedFile){

            return;

        }




        if(

            !selectedFile.name.endsWith(".xlsx")

            &&

            !selectedFile.name.endsWith(".xls")

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









    return (


        <div>


            <div className="

                bg-white

                rounded-xl

                shadow

                p-8

            ">





                <div className="

                    flex

                    items-center

                    gap-3

                    mb-5

                ">


                    <UploadCloud

                        size={32}

                    />



                    <h2 className="

                        text-xl

                        font-bold

                    ">

                        Upload Excel SIMPEG

                    </h2>



                </div>








                <p className="

                    text-gray-600

                    mb-5

                ">


                    Upload file mentah SIMPEG

                    untuk proses import data ASN.


                </p>








                <input


                    type="file"


                    accept=".xlsx,.xls"


                    onChange={handleUpload}


                    className="

                        border

                        rounded

                        p-3

                        w-full

                    "


                />









                {

                    filename &&


                    <div className="

                        mt-4

                        bg-gray-100

                        p-3

                        rounded

                    ">


                        File dipilih:


                        <b className="ml-2">

                            {filename}

                        </b>



                    </div>


                }









                {

                    loading &&



                    <div className="

                        mt-5

                        flex

                        items-center

                        gap-3

                        text-gray-600

                    ">


                        <LoaderCircle

                            className="animate-spin"

                        />


                        Membaca file Excel...



                    </div>



                }









                {

                    error &&



                    <div className="

                        mt-5

                        bg-red-100

                        text-red-700

                        p-3

                        rounded

                    ">


                        {error}


                    </div>



                }





            </div>


        </div>


    );


}



export default UploadExcel;