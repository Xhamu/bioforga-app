<?php

namespace App\Filament\Resources\ParteTrabajoTallerMaquinariaResource\Pages;

use App\Filament\Resources\ParteTrabajoTallerMaquinariaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParteTrabajoTallerMaquinarias extends ListRecords
{
    protected static string $resource = ParteTrabajoTallerMaquinariaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
