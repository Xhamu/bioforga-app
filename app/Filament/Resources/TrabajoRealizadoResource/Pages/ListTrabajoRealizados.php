<?php

namespace App\Filament\Resources\TrabajoRealizadoResource\Pages;

use App\Filament\Resources\TrabajoRealizadoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTrabajoRealizados extends ListRecords
{
    protected static string $resource = TrabajoRealizadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
