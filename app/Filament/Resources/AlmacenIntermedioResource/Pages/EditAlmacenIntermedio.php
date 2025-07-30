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

    /** 
     * Sobrescribimos para enviar el registro a las vistas personalizadas
     */
    protected function getFormViewData(): array
    {
        return array_merge(parent::getFormViewData(), [
            'record' => $this->record, // Pasamos el registro completo
        ]);
    }
}
