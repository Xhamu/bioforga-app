<?php

namespace App\Filament\Resources\ParteTrabajoTallerVehiculosResource\Pages;

use App\Filament\Resources\ParteTrabajoTallerVehiculosResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParteTrabajoTallerVehiculos extends EditRecord
{
    protected static string $resource = ParteTrabajoTallerVehiculosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
