<?php

use App\Models\Referencia;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return view('welcome');
});

Route::get('/contador-referencias-hoy', function () {
    $total = Referencia::whereDate('created_at', now())->count();
    return response()->json(['total' => $total]);
});