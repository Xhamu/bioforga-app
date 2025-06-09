<?php

namespace App\Filament\Resources\PoblacionResource\Pages;

use App\Filament\Resources\PoblacionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPoblacions extends ListRecords
{
    protected static string $resource = PoblacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
