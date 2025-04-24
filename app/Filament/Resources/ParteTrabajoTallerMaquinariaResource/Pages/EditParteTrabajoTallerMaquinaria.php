<?php

namespace App\Filament\Resources\ParteTrabajoTallerMaquinariaResource\Pages;

use App\Filament\Resources\ParteTrabajoTallerMaquinariaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParteTrabajoTallerMaquinaria extends EditRecord
{
    protected static string $resource = ParteTrabajoTallerMaquinariaResource::class;

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
