<?php

namespace App\Filament\Resources\AlmacenIntermedioResource\Pages;

use App\Filament\Resources\AlmacenIntermedioResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAlmacenIntermedio extends EditRecord
{
    protected static string $resource = AlmacenIntermedioResource::class;

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
