<?php

namespace App\Filament\Resources\TrabajoRealizadoResource\Pages;

use App\Filament\Resources\TrabajoRealizadoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTrabajoRealizado extends EditRecord
{
    protected static string $resource = TrabajoRealizadoResource::class;

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
