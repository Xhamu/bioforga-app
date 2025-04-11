<?php

namespace App\Filament\Resources\PosibleMantenimientoResource\Pages;

use App\Filament\Resources\PosibleMantenimientoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPosibleMantenimientos extends ListRecords
{
    protected static string $resource = PosibleMantenimientoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
