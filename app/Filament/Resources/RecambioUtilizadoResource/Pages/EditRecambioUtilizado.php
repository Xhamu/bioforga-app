<?php

namespace App\Filament\Resources\RecambioUtilizadoResource\Pages;

use App\Filament\Resources\RecambioUtilizadoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRecambioUtilizado extends EditRecord
{
    protected static string $resource = RecambioUtilizadoResource::class;

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
