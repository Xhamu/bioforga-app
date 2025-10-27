<?php
// app/Observers/PrioridadStockObserver.php

namespace App\Observers;

use App\Models\PrioridadStock;
use App\Models\AlmacenIntermedio;
use App\Services\StockCalculator;

class PrioridadStockObserver
{
    public function created(PrioridadStock $p): void
    {
        $this->recalc($p);
    }

    public function updated(PrioridadStock $p): void
    {
        $this->recalc($p);
    }

    public function deleted(PrioridadStock $p): void
    {
        $this->recalc($p);
    }

    public function restored(PrioridadStock $p): void
    {
        $this->recalc($p);
    }

    protected function recalc(PrioridadStock $p): void
    {
        if (!$p->almacen_intermedio_id)
            return;

        $alm = AlmacenIntermedio::find($p->almacen_intermedio_id);
        if (!$alm)
            return;

        app(StockCalculator::class)->actualizarPrioridades($alm);
    }
}
