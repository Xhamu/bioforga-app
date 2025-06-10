<?php

namespace App\Filament\Resources\PoblacionResource\Pages;

use App\Filament\Resources\PoblacionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPoblacion extends ViewRecord
{
    protected static string $resource = PoblacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
