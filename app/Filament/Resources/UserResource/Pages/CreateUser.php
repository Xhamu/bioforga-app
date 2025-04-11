<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return '/usuarios';
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Usuario creado');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['empresa_bioforga']) {
            $data['proveedor_id'] = null;
        }

        if ($data['proveedor_id'] !== null) {
            $data['empresa_bioforga'] = false;
        }

        return $data;
    }
}
