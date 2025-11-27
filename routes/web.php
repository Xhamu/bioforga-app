<?php

use App\Http\Controllers\ReferenciaAlertasController;
use App\Models\Referencia;
use Illuminate\Support\Facades\Route;
use App\Exports\ReferenciasExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

Route::get('/test', function () {
    return view('welcome');
});

Route::get('/contador-referencias-hoy', function () {
    $total = Referencia::whereDate('created_at', now())->count();
    return response()->json(['total' => $total]);
});

Route::get('/admin/referencias/export', function (Request $request) {
    $tipo = $request->query('tipo');
    $nombre = $request->query('nombre', 'referencias-' . now()->format('Y-m-d') . '.xlsx');

    return Excel::download(new ReferenciasExport($tipo), $nombre);
})->name('referencias.export')->middleware(['auth', 'verified']);

Route::post(
    '/referencias/alertas/aceptar',
    [ReferenciaAlertasController::class, 'aceptarTodas']
)->name('referencias.alertas.aceptar');