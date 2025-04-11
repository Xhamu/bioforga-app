<?php

namespace App\Filament\Resources\ParteTrabajoSuministroTransporteResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroTransporteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParteTrabajoSuministroTransportes extends ListRecords
{
    protected static string $resource = ParteTrabajoSuministroTransporteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
