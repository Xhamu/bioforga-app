<?php

namespace App\Filament\Resources\ParteTrabajoSuministroDesplazamientoResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroDesplazamientoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParteTrabajoSuministroDesplazamiento extends EditRecord
{
    protected static string $resource = ParteTrabajoSuministroDesplazamientoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
