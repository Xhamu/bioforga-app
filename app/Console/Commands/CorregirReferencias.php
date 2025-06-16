<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Referencia;

class CorregirReferencias extends Command
{
    protected $signature = 'referencias:corregir';
    protected $description = 'Corrige las referencias quitando un 0 inicial en el contador final para dejarlo en dos cifras';

    public function handle()
    {
        $this->info('Corrigiendo referencias...');

        $referencias = Referencia::all();
        $contador = 0;

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

                $this->line("Modificada: $original → $nuevaReferencia");
                $contador++;
            }
        }

        $this->info("Proceso completado. Total modificadas: $contador");

        return Command::SUCCESS;
    }
}
