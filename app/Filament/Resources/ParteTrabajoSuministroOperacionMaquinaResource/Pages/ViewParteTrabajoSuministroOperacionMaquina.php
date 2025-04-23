<?php

namespace App\Filament\Resources\ParteTrabajoSuministroOperacionMaquinaResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroOperacionMaquinaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewParteTrabajoSuministroOperacionMaquina extends ViewRecord
{
    protected static string $resource = ParteTrabajoSuministroOperacionMaquinaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
