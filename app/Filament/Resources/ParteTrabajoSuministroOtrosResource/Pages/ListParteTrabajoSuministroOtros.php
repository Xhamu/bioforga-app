<?php

namespace App\Filament\Resources\ParteTrabajoSuministroOtrosResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroOtrosResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParteTrabajoSuministroOtros extends ListRecords
{
    protected static string $resource = ParteTrabajoSuministroOtrosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
