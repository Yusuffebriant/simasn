function ImportExcel(){

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
                Import Data Pegawai
            </h2>


            <p className="
                text-gray-500
                mt-2
            ">
                Upload file Excel untuk memperbarui data pegawai.
            </p>


            <input
                type="file"
                accept=".xlsx,.xls"
                className="
                    mt-5
                    border
                    p-2
                    rounded
                    w-full
                "
            />


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
                Upload Excel
            </button>


        </div>

    )

}

export default ImportExcel;