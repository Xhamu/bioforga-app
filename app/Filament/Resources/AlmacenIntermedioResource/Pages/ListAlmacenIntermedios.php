<?php

namespace App\Filament\Resources\AlmacenIntermedioResource\Pages;

use App\Filament\Resources\AlmacenIntermedioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAlmacenIntermedios extends ListRecords
{
    protected static string $resource = AlmacenIntermedioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
