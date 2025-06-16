<?php

namespace App\Filament\Resources\ReferenciaResource\Pages;

use App\Filament\Resources\ReferenciaResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateReferencia extends CreateRecord
{
    protected static string $resource = ReferenciaResource::class;

    protected function getRedirectUrl(): string
    {
        return '/referencias';
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Referencia creada');
    }

    protected function afterCreate(): void
    {
        $this->record->usuarios()->sync($this->data['usuarios'] ?? []);
    }

}
