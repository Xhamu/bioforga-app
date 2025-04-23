<?php

namespace App\Filament\Resources\ParteTrabajoSuministroOperacionMaquinaResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroOperacionMaquinaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParteTrabajoSuministroOperacionMaquinas extends ListRecords
{
    protected static string $resource = ParteTrabajoSuministroOperacionMaquinaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
