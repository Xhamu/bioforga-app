<?php

namespace App\Filament\Resources\ParteTrabajoSuministroAveriaResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroAveriaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParteTrabajoSuministroAveria extends EditRecord
{
    protected static string $resource = ParteTrabajoSuministroAveriaResource::class;

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
