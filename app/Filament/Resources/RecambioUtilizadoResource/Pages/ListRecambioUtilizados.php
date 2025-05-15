<?php

namespace App\Filament\Resources\RecambioUtilizadoResource\Pages;

use App\Filament\Resources\RecambioUtilizadoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecambioUtilizados extends ListRecords
{
    protected static string $resource = RecambioUtilizadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
