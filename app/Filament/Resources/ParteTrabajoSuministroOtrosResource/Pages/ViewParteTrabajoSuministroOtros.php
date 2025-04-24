<?php

namespace App\Filament\Resources\ParteTrabajoSuministroOtrosResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroOtrosResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewParteTrabajoSuministroOtros extends ViewRecord
{
    protected static string $resource = ParteTrabajoSuministroOtrosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
