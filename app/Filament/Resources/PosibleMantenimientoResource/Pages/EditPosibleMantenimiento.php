<?php

namespace App\Filament\Resources\PosibleMantenimientoResource\Pages;

use App\Filament\Resources\PosibleMantenimientoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPosibleMantenimiento extends EditRecord
{
    protected static string $resource = PosibleMantenimientoResource::class;

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
