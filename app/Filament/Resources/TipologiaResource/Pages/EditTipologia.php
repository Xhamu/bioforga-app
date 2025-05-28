<?php

namespace App\Filament\Resources\TipologiaResource\Pages;

use App\Filament\Resources\TipologiaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipologia extends EditRecord
{
    protected static string $resource = TipologiaResource::class;

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
