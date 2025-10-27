<?php
// app/Observers/CargaTransporteObserver.php

namespace App\Observers;

use App\Models\CargaTransporte;
use App\Models\ParteTrabajoSuministroTransporte;
use App\Models\AlmacenIntermedio;
use App\Services\StockCalculator;

class CargaTransporteObserver
{
    public function created(CargaTransporte $carga): void
    {
        $this->recalcFor($this->currentAlmacenes($carga));
    }

    public function updated(CargaTransporte $carga): void
    {
        $ids = array_unique(array_filter(array_merge(
            $this->currentAlmacenes($carga),
            $this->originalAlmacenes($carga),
        )));
        $this->recalcFor($ids);
    }

    public function deleted(CargaTransporte $carga): void
    {
        $ids = array_unique(array_filter(array_merge(
            $this->currentAlmacenes($carga),
            $this->originalAlmacenes($carga),
        )));
        $this->recalcFor($ids);
    }

    public function restored(CargaTransporte $carga): void
    {
        $this->recalcFor($this->currentAlmacenes($carga));
    }

    public function forceDeleted(CargaTransporte $carga): void
    {
        $ids = array_unique(array_filter(array_merge(
            $this->currentAlmacenes($carga),
            $this->originalAlmacenes($carga),
        )));
        $this->recalcFor($ids);
    }

    /**
     * Almacenes afectados por el estado ACTUAL de la carga.
     * - Entradas: parte->almacen_id (cliente_id null, referencia_id != null)
     * - Salidas:  carga->almacen_id (parte->cliente_id != null)
     */
    protected function currentAlmacenes(CargaTransporte $carga): array
    {
        $ids = [];

        // salida desde almacén → almacen_id en la carga
        if ($carga->almacen_id) {
            $ids[] = (int) $carga->almacen_id;
        }

        // entrada hacia almacén → almacen_id en el parte
        $parte = $carga->relationLoaded('parteTrabajoSuministroTransporte')
            ? $carga->parteTrabajoSuministroTransporte
            : ParteTrabajoSuministroTransporte::find($carga->parte_trabajo_suministro_transporte_id);

        if ($parte && $parte->almacen_id) {
            $ids[] = (int) $parte->almacen_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Almacenes afectados por el estado ORIGINAL de la carga (antes del update/delete).
     */
    protected function originalAlmacenes(CargaTransporte $carga): array
    {
        $ids = [];

        $origAlmacenId = $carga->getOriginal('almacen_id');
        if ($origAlmacenId) {
            $ids[] = (int) $origAlmacenId;
        }

        $origParteId = $carga->getOriginal('parte_trabajo_suministro_transporte_id');
        if ($origParteId) {
            $origParte = ParteTrabajoSuministroTransporte::withTrashed()->find($origParteId);
            if ($origParte && $origParte->almacen_id) {
                $ids[] = (int) $origParte->almacen_id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    protected function recalcFor(array $almacenIds): void
    {
        if (empty($almacenIds))
            return;

        /** @var StockCalculator $calc */
        $calc = app(StockCalculator::class);

        $almacenes = AlmacenIntermedio::whereIn('id', $almacenIds)->get();
        foreach ($almacenes as $alm) {
            $calc->actualizarPrioridades($alm);
        }
    }
}
