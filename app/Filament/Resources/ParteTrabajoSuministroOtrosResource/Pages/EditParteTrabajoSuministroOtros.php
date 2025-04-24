<?php

namespace App\Filament\Resources\ParteTrabajoSuministroOtrosResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroOtrosResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParteTrabajoSuministroOtros extends EditRecord
{
    protected static string $resource = ParteTrabajoSuministroOtrosResource::class;

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
