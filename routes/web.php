<?php

use Illuminate\Support\Facades\Route;

// Semua request non-API diarahkan ke view yang sama (React app),
// biar React Router yang urus routing di sisi client.
Route::view('/{any}', 'welcome')->where('any', '.*');