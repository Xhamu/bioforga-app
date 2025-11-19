<?php

namespace App\Console\Commands;

use App\Models\AlmacenIntermedio;
use App\Models\PrioridadStock;
use App\Services\StockCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildAlmacenStock extends Command
{
    protected $signature = 'stock:rebuild {almacen_id?}';
    protected $description = 'Recalcula prioridades_stock (crea faltantes y actualiza cantidades) para uno o todos los almacenes';

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
            // 1) Crear/renumerar prioridades que falten (igual que el seeder)
            $this->ensurePrioridadesForAlmacen((int) $alm->id);

            // 2) Recalcular cantidades disponibles usando StockCalculator
            $calc->actualizarPrioridades($alm);

            $this->info("✅ Reconstruido stock de almacén #{$alm->id} ({$alm->referencia})");
            $count++;
        }

        $this->info("✔️ Completado. Almacenes procesados: {$count}");

        return Command::SUCCESS;
    }

    /**
     * Crea las filas de prioridades que falten para un almacén y renumera
     * las prioridades en el orden canónico CERTS x ESPECIES.
     *
     * Equivalente a la lógica del PrioridadesStockSeeder, pero reutilizando
     * las constantes del modelo PrioridadStock.
     */
    private function ensurePrioridadesForAlmacen(int $almacenId): void
    {
        DB::transaction(function () use ($almacenId) {
            $certs = PrioridadStock::CERTS;
            $especies = PrioridadStock::ESPECIES;

            // Cargar existentes (clave: CERT|ESP)
            $existentes = PrioridadStock::where('almacen_intermedio_id', $almacenId)
                ->get()
                ->keyBy(fn($r) => strtoupper(trim($r->certificacion)) . '|' . strtoupper(trim($r->especie)));

            // Crear los que falten
            $prio = 1;
            foreach ($certs as $cert) {
                foreach ($especies as $esp) {
                    $key = $cert . '|' . $esp;

                    if (!isset($existentes[$key])) {
                        PrioridadStock::create([
                            'almacen_intermedio_id' => $almacenId,
                            'certificacion' => $cert,
                            'especie' => $esp,
                            'prioridad' => $prio,
                            'cantidad_disponible' => 0.00,
                        ]);
                    }

                    $prio++;
                }
            }

            // Renumerar TODO el almacén en orden canónico
            $todos = PrioridadStock::where('almacen_intermedio_id', $almacenId)
                ->get()
                ->keyBy(fn($r) => strtoupper(trim($r->certificacion)) . '|' . strtoupper(trim($r->especie)));

            $prio = 1;
            foreach ($certs as $cert) {
                foreach ($especies as $esp) {
                    $key = $cert . '|' . $esp;

                    if (isset($todos[$key])) {
                        $fila = $todos[$key];

                        if ((int) $fila->prioridad !== $prio) {
                            $fila->update(['prioridad' => $prio]);
                        }

                        $prio++;
                    }
                }
            }
        });
    }
}
