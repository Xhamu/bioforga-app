<?php

namespace App\Providers;

use App\Models\ParteTrabajoSuministroTransporte;
use App\Models\Referencia;
use App\Observers\ParteTrabajoSuministroTransporteObserver;
use App\Observers\ReferenciaObserver;
use Illuminate\Support\ServiceProvider;
use App\Models\CargaTransporte;
use App\Models\PrioridadStock;
use App\Observers\CargaTransporteObserver;
use App\Observers\PrioridadStockObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CargaTransporte::observe(CargaTransporteObserver::class);
        PrioridadStock::observe(PrioridadStockObserver::class);
        Referencia::observe(ReferenciaObserver::class);
    }
}
