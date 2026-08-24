import { useEffect, useState } from "react";


function ColumnMapping({

    headers,

    mapping,

    setMapping,

    next,

    back

}) {



    const systemFields = [

        {
            value:"nip",
            label:"NIP"
        },

        {
            value:"nama",
            label:"Nama Pegawai"
        },

        {
            value:"instansi_id",
            label:"Instansi"
        },

        {
            value:"unit",
            label:"Unit / Sub Unit"
        },

        {
            value:"jenis_kelamin",
            label:"Jenis Kelamin"
        },

        {
            value:"status_kepegawaian",
            label:"Status Kepegawaian"
        },

        {
            value:"golongan_ruang_id",
            label:"Golongan Ruang"
        },

        {
            value:"eselon_id",
            label:"Eselon"
        },

        {
            value:"agama_id",
            label:"Agama"
        },

        {
            value:"pendidikan_id",
            label:"Pendidikan"
        },

        {
            value:"jabatan",
            label:"Jabatan"
        },

        {
            value:"tanggal_lahir",
            label:"Tanggal Lahir"
        },

        {
            value:"tmt_pangkat",
            label:"TMT Pangkat"
        },

        {
            value:"tanggal_pensiun",
            label:"Tanggal Pensiun"
        }

    ];






    const [localMapping,setLocalMapping] = useState({});





    useEffect(()=>{


        const auto = {};



        headers.forEach(header=>{


            const key =
                header
                .toLowerCase()
                .replaceAll("_","")
                .replaceAll(" ","");



            if(key.includes("nip")){

                auto[header]="nip";

            }


            else if(key.includes("nama")){

                auto[header]="nama";

            }


            else if(
                key.includes("unit")
                ||
                key.includes("instansi")
            ){

                auto[header]="instansi_id";

            }


            else if(
                key.includes("pangkat")
                ||
                key.includes("golru")
            ){

                auto[header]="golongan_ruang_id";

            }


            else if(
                key.includes("pendidikan")
                ||
                key.includes("tingkatpendidikan")
            ){

                auto[header]="pendidikan_id";

            }


            else if(
                key.includes("eselon")
            ){

                auto[header]="eselon_id";

            }


            else if(
                key.includes("agama")
            ){

                auto[header]="agama_id";

            }


            else if(
                key.includes("jabatan")
            ){

                auto[header]="jabatan";

            }


            else if(
                key.includes("tgl lahir")
                ||
                key.includes("tanggal_lahir")
            ){

                auto[header]="tanggal_lahir";

            }


            else if(
                key.includes("pensiun")
            ){

                auto[header]="tanggal_pensiun";

            }



        });



        setLocalMapping(auto);

        setMapping(auto);



    },[headers]);







    function handleChange(
        excelColumn,
        value
    ){


        const update={

            ...localMapping,

            [excelColumn]:value

        };


        setLocalMapping(update);

        setMapping(update);


    }







    return (

        <div>


            <div className="
                bg-white
                rounded-xl
                shadow
                p-8
            ">



                <h2 className="
                    text-2xl
                    font-bold
                    mb-6
                ">

                    Mapping Kolom Excel

                </h2>




                <p className="
                    text-gray-600
                    mb-6
                ">

                    Cocokkan kolom Excel dengan
                    field sistem SIMASN.

                </p>





                <div className="
                    space-y-4
                ">



                {

                headers.map((header,index)=>(


                    <div

                    key={index}

                    className="
                        grid
                        grid-cols-2
                        gap-5
                        items-center
                        border
                        p-4
                        rounded-lg
                    "

                    >



                        <div>


                            <p className="
                                font-semibold
                            ">

                                {header}

                            </p>


                            <p className="
                                text-sm
                                text-gray-500
                            ">

                                Kolom Excel

                            </p>


                        </div>






                        <select

                        value={
                            localMapping[header] || ""
                        }


                        onChange={(e)=>
                            handleChange(
                                header,
                                e.target.value
                            )
                        }


                        className="
                            border
                            rounded
                            p-2
                        "

                        >


                            <option value="">

                                -- Pilih Field --

                            </option>



                            {
                            systemFields.map(field=>(

                                <option

                                key={field.value}

                                value={field.value}

                                >

                                    {field.label}

                                </option>


                            ))
                            }



                        </select>



                    </div>



                ))

                }



                </div>








                <div className="
                    mt-8
                    flex
                    gap-3
                ">



                    <button

                    onClick={back}

                    className="
                        px-5
                        py-3
                        bg-gray-300
                        rounded-lg
                    "

                    >

                        Kembali

                    </button>





                    <button

                    onClick={next}

                    className="
                        px-5
                        py-3
                        bg-[#006A4E]
                        text-white
                        rounded-lg
                    "

                    >

                        Simpan Mapping

                    </button>



                </div>




            </div>


        </div>

    );

}



export default ColumnMapping;