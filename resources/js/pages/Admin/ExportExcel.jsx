function ExportExcel(){

    return (

        <div className="
            bg-white
            rounded-xl
            shadow
            p-6
        ">


            <h2 className="
                text-xl
                font-semibold
            ">
                Export Laporan
            </h2>


            <p className="
                text-gray-500
                mt-2
            ">
                Download laporan rekapitulasi pegawai.
            </p>



            <select
                className="
                    mt-5
                    border
                    rounded
                    p-2
                    w-full
                "
            >

                <option>
                    Rekap Jumlah Pegawai
                </option>

                <option>
                    Rekap Pendidikan
                </option>

                <option>
                    Rekap Jabatan
                </option>

            </select>



            <button
                className="
                    mt-4
                    bg-[#006A4E]
                    text-white
                    px-5
                    py-2
                    rounded
                "
            >
                Download Excel
            </button>


        </div>

    )

}

export default ExportExcel;