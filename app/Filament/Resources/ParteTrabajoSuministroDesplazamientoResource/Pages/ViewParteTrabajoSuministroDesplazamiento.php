<?php

namespace App\Filament\Resources\ParteTrabajoSuministroDesplazamientoResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroDesplazamientoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewParteTrabajoSuministroDesplazamiento extends ViewRecord
{
    protected static string $resource = ParteTrabajoSuministroDesplazamientoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
