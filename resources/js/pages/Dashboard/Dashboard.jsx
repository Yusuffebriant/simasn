import Sidebar from "../../components/Sidebar";


function Dashboard(){

    return (

        <div className="flex bg-[#F5F7FA] min-h-screen">


            <Sidebar/>


            <main className="flex-1 p-8">


                <h1 className="text-3xl font-bold text-[#1F2937]">
                    Statistik Pegawai
                </h1>


                <div className="mt-5 border-b-4 border-[#D4A017] w-20">
                </div>


                <div className="mt-10">
                    {/* Cards dan Charts nanti masuk sini */}
                </div>


            </main>


        </div>

    )

}


export default Dashboard;