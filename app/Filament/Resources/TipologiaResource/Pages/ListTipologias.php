<?php

namespace App\Filament\Resources\TipologiaResource\Pages;

use App\Filament\Resources\TipologiaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTipologias extends ListRecords
{
    protected static string $resource = TipologiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
