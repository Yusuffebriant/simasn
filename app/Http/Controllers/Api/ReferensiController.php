<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Instansi, GolonganRuang, Eselon, Agama, Pendidikan};

class ReferensiController extends Controller
{
    public function instansi()
    {
        return response()->json(Instansi::orderBy('nama')->get());
    }

    public function golonganRuang()
    {
        return response()->json(GolonganRuang::orderBy('kelompok')->orderBy('urutan')->get());
    }

    public function eselon()
    {
        return response()->json(Eselon::orderBy('urutan')->get());
    }

    public function agama()
    {
        return response()->json(Agama::orderBy('nama')->get());
    }

    public function pendidikan()
    {
        return response()->json(Pendidikan::orderBy('urutan')->get());
    }
}