<?php

namespace App\Filament\Resources\ParteTrabajoSuministroOperacionMaquinaResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroOperacionMaquinaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParteTrabajoSuministroOperacionMaquina extends EditRecord
{
    protected static string $resource = ParteTrabajoSuministroOperacionMaquinaResource::class;

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
