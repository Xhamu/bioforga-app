<?php

namespace App\Filament\Resources\ParteTrabajoSuministroTransporteResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroTransporteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewParteTrabajoSuministroTransporte extends ViewRecord
{
    protected static string $resource = ParteTrabajoSuministroTransporteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
