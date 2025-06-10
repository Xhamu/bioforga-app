<?php

namespace App\Filament\Resources\PoblacionResource\Pages;

use App\Filament\Resources\PoblacionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPoblacion extends EditRecord
{
    protected static string $resource = PoblacionResource::class;

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
