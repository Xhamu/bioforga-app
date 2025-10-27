<?php

namespace App\Filament\Resources\PrioridadStockResource\Pages;

use App\Filament\Resources\PrioridadStockResource;
use Filament\Resources\Pages\ListRecords;

class ListPrioridadStocks extends ListRecords
{
    protected static string $resource = PrioridadStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Usa el create estándar; si quieres acción para generar combinaciones, te la añado.
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
