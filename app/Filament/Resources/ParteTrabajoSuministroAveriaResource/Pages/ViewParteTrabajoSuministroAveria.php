<?php

namespace App\Filament\Resources\ParteTrabajoSuministroAveriaResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroAveriaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewParteTrabajoSuministroAveria extends ViewRecord
{
    protected static string $resource = ParteTrabajoSuministroAveriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
