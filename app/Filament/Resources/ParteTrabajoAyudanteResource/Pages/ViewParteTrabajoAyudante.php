<?php

namespace App\Filament\Resources\ParteTrabajoAyudanteResource\Pages;

use App\Filament\Resources\ParteTrabajoAyudanteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewParteTrabajoAyudante extends ViewRecord
{
    protected static string $resource = ParteTrabajoAyudanteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
