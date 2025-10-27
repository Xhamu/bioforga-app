<?php

namespace Database\Seeders;

use App\Models\AlmacenIntermedio;
use App\Models\PrioridadStock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrioridadesStockSeeder extends Seeder
{
    private array $CERTS = ['SURE INDUSTRIAL', 'SURE FORESTAL', 'PEFC', 'SBP'];
    private array $ESPECIES = ['PINO', 'EUCALIPTO', 'ACACIA', 'FRONDOSA', 'OTROS'];

    public function run(): void
    {
        AlmacenIntermedio::query()
            ->orderBy('id')
            ->chunkById(200, function ($almacenes) {
                foreach ($almacenes as $alm) {
                    $this->seedForAlmacen((int) $alm->id);
                }
            });
    }

    private function seedForAlmacen(int $almacenId): void
    {
        DB::transaction(function () use ($almacenId) {
            // Cargar existentes (clave: CERT|ESP)
            $existentes = PrioridadStock::where('almacen_intermedio_id', $almacenId)
                ->get()
                ->keyBy(fn($r) => strtoupper(trim($r->certificacion)) . '|' . strtoupper(trim($r->especie)));

            // Crear los que falten
            $prio = 1;
            foreach ($this->CERTS as $cert) {
                foreach ($this->ESPECIES as $esp) {
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
            foreach ($this->CERTS as $cert) {
                foreach ($this->ESPECIES as $esp) {
                    $key = $cert . '|' . $esp;
                    if (isset($todos[$key])) {
                        $fila = $todos[$key];
                        if ((int) $fila->prioridad !== $prio) {
                            $fila->prioridad = $prio;
                            $fila->save();
                        }
                        $prio++;
                    }
                }
            }
        });
    }
}
