<?php

namespace App\Observers;

use App\Models\Referencia;
use App\Models\User;
use App\Models\ReferenciaUserAlerta;

class ReferenciaObserver
{
    public function created(Referencia $referencia): void
    {
        // Prefijos de referencias de suministro
        $prefijosSuministro = ['SUCA', 'SUSA', 'SUOT', 'SUEX'];

        // Verificar si el campo referencia contiene alguno de los prefijos
        $esSuministro = false;

        foreach ($prefijosSuministro as $prefijo) {
            if (str_contains($referencia->referencia, $prefijo)) {
                $esSuministro = true;
                break;
            }
        }

        if (!$esSuministro) {
            return;
        }

        // Buscar a Patricia por NIF
        $patricia = User::where('nif', '76901543P')->first();

        if (!$patricia) {
            return;
        }

        // Crear alerta solamente si es referencia de suministro
        ReferenciaUserAlerta::create([
            'referencia_id' => $referencia->id,
            'user_id' => $patricia->id,
        ]);
    }
}
