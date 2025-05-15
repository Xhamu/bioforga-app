<?php

namespace App\Filament\Resources\AlmacenIntermedioResource\Pages;

use App\Filament\Resources\AlmacenIntermedioResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAlmacenIntermedio extends ViewRecord
{
    protected static string $resource = AlmacenIntermedioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
