<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReferenciaAlertasController extends Controller
{
    public function aceptarTodas(Request $request)
    {
        $user = $request->user();

        // Solo Patricia (por NIF, como comentabas)
        if (!$user || $user->nif !== '76901543P') {
            abort(403);
        }

        $user->referenciaAlertas()
            ->whereNull('accepted_at')
            ->update(['accepted_at' => now()]);

        return response()->json(['status' => 'ok']);
    }
}
