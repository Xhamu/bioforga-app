<?php

namespace App\Filament\Resources\ParteTrabajoTallerVehiculosResource\Pages;

use App\Filament\Resources\ParteTrabajoTallerVehiculosResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewParteTrabajoTallerVehiculos extends ViewRecord
{
    protected static string $resource = ParteTrabajoTallerVehiculosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
