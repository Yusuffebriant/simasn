<?php

use Illuminate\Support\Facades\Route;

Route::get('/admin', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('welcome');
});
