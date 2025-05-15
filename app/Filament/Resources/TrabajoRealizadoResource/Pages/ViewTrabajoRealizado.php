<?php

namespace App\Filament\Resources\TrabajoRealizadoResource\Pages;

use App\Filament\Resources\TrabajoRealizadoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTrabajoRealizado extends ViewRecord
{
    protected static string $resource = TrabajoRealizadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
