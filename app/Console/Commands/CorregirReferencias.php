<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Referencia;
use App\Models\AlmacenIntermedio;

class CorregirReferencias extends Command
{
    protected $signature = 'referencias:corregir';
    protected $description = 'Corrige las referencias quitando un 0 inicial en el contador final para dejarlas en dos cifras';

    public function handle()
    {
        $this->info('Corrigiendo referencias en Referencia...');

        $referencias = Referencia::all();
        $contadorReferencias = 0;

        foreach ($referencias as $referencia) {
            $original = $referencia->referencia;

            // Extrae los últimos 3 dígitos
            $sufijo = substr($original, -3);

            // Solo modifica si empieza por 0
            if ($sufijo[0] === '0') {
                $nuevoSufijo = substr($sufijo, 1); // elimina solo el primer 0
                $nuevaReferencia = substr($original, 0, -3) . $nuevoSufijo;

                // Actualiza
                $referencia->referencia = $nuevaReferencia;
                $referencia->save();

                $this->line("Modificada Referencia: $original → $nuevaReferencia");
                $contadorReferencias++;
            }
        }

        $this->info("Proceso completado en Referencia. Total modificadas: $contadorReferencias");

        $this->info('Corrigiendo referencias en AlmacenIntermedio...');

        $almacenes = AlmacenIntermedio::all();
        $contadorAlmacenes = 0;

        foreach ($almacenes as $almacen) {
            $original = $almacen->referencia;

            // Extrae los últimos 3 dígitos
            $sufijo = substr($original, -3);

            // Solo modifica si empieza por 0
            if ($sufijo[0] === '0') {
                $nuevoSufijo = substr($sufijo, 1); // elimina solo el primer 0
                $nuevaReferencia = substr($original, 0, -3) . $nuevoSufijo;

                // Actualiza
                $almacen->referencia = $nuevaReferencia;
                $almacen->save();

                $this->line("Modificado AlmacenIntermedio: $original → $nuevaReferencia");
                $contadorAlmacenes++;
            }
        }

        $this->info("Proceso completado en AlmacenIntermedio. Total modificadas: $contadorAlmacenes");

        return Command::SUCCESS;
    }
}
