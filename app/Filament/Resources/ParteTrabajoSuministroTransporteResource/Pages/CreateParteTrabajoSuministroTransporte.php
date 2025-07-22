<?php

namespace App\Filament\Resources\ParteTrabajoSuministroTransporteResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroTransporteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateParteTrabajoSuministroTransporte extends CreateRecord
{
    protected static string $resource = ParteTrabajoSuministroTransporteResource::class;

    protected static bool $canCreateAnother = false;
}
