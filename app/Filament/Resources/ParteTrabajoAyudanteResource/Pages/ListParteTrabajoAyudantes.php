<?php

namespace App\Filament\Resources\ParteTrabajoAyudanteResource\Pages;

use App\Filament\Resources\ParteTrabajoAyudanteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParteTrabajoAyudantes extends ListRecords
{
    protected static string $resource = ParteTrabajoAyudanteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
