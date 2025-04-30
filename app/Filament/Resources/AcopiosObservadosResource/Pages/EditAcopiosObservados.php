<?php

namespace App\Filament\Resources\AcopiosObservadosResource\Pages;

use App\Filament\Resources\AcopiosObservadosResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAcopiosObservados extends EditRecord
{
    protected static string $resource = AcopiosObservadosResource::class;

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
