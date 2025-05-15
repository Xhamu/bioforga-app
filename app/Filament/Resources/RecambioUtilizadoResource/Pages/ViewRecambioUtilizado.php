<?php

namespace App\Filament\Resources\RecambioUtilizadoResource\Pages;

use App\Filament\Resources\RecambioUtilizadoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRecambioUtilizado extends ViewRecord
{
    protected static string $resource = RecambioUtilizadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
