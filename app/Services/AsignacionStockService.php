<?php

// app/Services/AsignacionStockService.php
namespace App\Services;

use App\Models\AlmacenIntermedio;
use App\Models\PrioridadStock;
use InvalidArgumentException;

class AsignacionStockService
{
    public function __construct(
        private StockCalculator $calc, // ⬅️ servicio que calcula disponibilidad por (cert, especie)
    ) {
    }

    /**
     * Calcula una propuesta de carga SIN modificar datos.
     *
     * @return array{
     *   asignaciones: array<int, array{certificacion:string, especie:string, cantidad:float}>,
     *   restante: float
     * }
     */
    public function preview(AlmacenIntermedio $almacen, float $cantidadSolicitada): array
    {
        if ($cantidadSolicitada <= 0) {
            throw new InvalidArgumentException('La cantidad solicitada debe ser > 0.');
        }

        // Disponibilidad “virtual” por clave "CERT|ESP" tras repartir salidas previas por prioridad
        $disponible = $this->calc->calcular($almacen)['disponible'];

        // Orden de consumo: prioridad ascendente
        $prioridades = PrioridadStock::query()
            ->where('almacen_intermedio_id', $almacen->id)
            ->orderBy('prioridad')
            ->orderBy('id')
            ->get(['certificacion', 'especie']);

        $restante = $cantidadSolicitada;
        $asignaciones = [];

        foreach ($prioridades as $p) {
            if ($restante <= 0)
                break;

            $key = "{$p->certificacion}|{$p->especie}";
            $hay = (float) ($disponible[$key] ?? 0.0);
            if ($hay <= 0)
                continue;

            $usar = min($hay, $restante);
            if ($usar <= 0)
                continue;

            $asignaciones[] = [
                'certificacion' => $p->certificacion,
                'especie' => $p->especie,
                'cantidad' => (float) round($usar, 2),
            ];

            $restante -= $usar;
        }

        return [
            'asignaciones' => $asignaciones,
            'restante' => (float) max(0, round($restante, 2)),
        ];
    }

    /**
     * Confirma la propuesta. Aquí NO se descuenta stock directamente:
     * tu app ya registra la salida creando la CargaTransporte (almacen_id + cantidad),
     * y el stock se recalcula automáticamente con el histórico.
     *
     * @return array{
     *   asignaciones: array<int, array{certificacion:string, especie:string, cantidad:float}>,
     *   restante: float
     * }
     */
    public function confirm(AlmacenIntermedio $almacen, float $cantidadSolicitada): array
    {
        // Mismo algoritmo que preview. La deducción real ocurre cuando creas la carga.
        return $this->preview($almacen, $cantidadSolicitada);
    }
}
