<?php

namespace App\Filament\Resources\ParteTrabajoTallerMaquinariaResource\Pages;

use App\Filament\Resources\ParteTrabajoTallerMaquinariaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewParteTrabajoTallerMaquinaria extends ViewRecord
{
    protected static string $resource = ParteTrabajoTallerMaquinariaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
