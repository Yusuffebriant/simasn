import Sidebar from "../../components/Sidebar";
import ImportExcel from "./ImportExcel";
import ExportExcel from "./ExportExcel";


function Admin(){

    return (

        <div className="flex min-h-screen bg-[#F5F7FA]">

            <Sidebar/>


            <main className="flex-1 p-10">

                <h1 className="
                    text-3xl
                    font-bold
                ">
                    Admin
                </h1>


                <div className="
                    mt-3
                    h-1
                    w-20
                    bg-[#D4A017]
                "></div>


                <div className="mt-8 grid grid-cols-2 gap-6">


                    <ImportExcel/>


                    <ExportExcel/>


                </div>


            </main>

        </div>

    )

}


export default Admin;