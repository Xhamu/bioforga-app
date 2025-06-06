<?php

namespace App\Filament\Resources\PosibleAveriaResource\Pages;

use App\Filament\Resources\PosibleAveriaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPosibleAveria extends EditRecord
{
    protected static string $resource = PosibleAveriaResource::class;

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
