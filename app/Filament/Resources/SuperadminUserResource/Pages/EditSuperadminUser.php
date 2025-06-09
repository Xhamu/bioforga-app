<?php

namespace App\Filament\Resources\SuperadminUserResource\Pages;

use App\Filament\Resources\SuperadminUserResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use STS\FilamentImpersonate\Impersonate;

class EditSuperadminUser extends EditRecord
{
    protected static string $resource = SuperadminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getActions(): array
    {
        return [
            Impersonate::make()->record($this->getRecord()) // <--
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Usuario actualizado');
    }

    protected function afterSave()
    {
        return redirect('/superadmin-users');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = bcrypt($data['password']);
        }

        return $data;
    }
}
