<?php

namespace App\Filament\Resources\ParteTrabajoAyudanteResource\Pages;

use App\Filament\Resources\ParteTrabajoAyudanteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParteTrabajoAyudante extends EditRecord
{
    protected static string $resource = ParteTrabajoAyudanteResource::class;

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
