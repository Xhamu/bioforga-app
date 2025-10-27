<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AlmacenIntermedio;
use App\Services\StockCalculator;

class RebuildAlmacenStock extends Command
{
    protected $signature = 'stock:rebuild {almacen_id?}';
    protected $description = 'Recalcula y actualiza prioridades_stock para uno o todos los almacenes';

    public function handle(): int
    {
        /** @var StockCalculator $calc */
        $calc = app(StockCalculator::class);

        $query = AlmacenIntermedio::query();
        if ($id = $this->argument('almacen_id')) {
            $query->where('id', (int) $id);
        }

        $count = 0;
        foreach ($query->cursor() as $alm) {
            $calc->actualizarPrioridades($alm);
            $this->info("✅ Actualizado almacén #{$alm->id} ({$alm->referencia})");
            $count++;
        }

        $this->info("✔️ Completado. Almacenes procesados: {$count}");
        return Command::SUCCESS;
    }
}
