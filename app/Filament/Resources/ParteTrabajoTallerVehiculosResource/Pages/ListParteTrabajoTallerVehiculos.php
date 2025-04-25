<?php

namespace App\Filament\Resources\ParteTrabajoTallerVehiculosResource\Pages;

use App\Filament\Resources\ParteTrabajoTallerVehiculosResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParteTrabajoTallerVehiculos extends ListRecords
{
    protected static string $resource = ParteTrabajoTallerVehiculosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
