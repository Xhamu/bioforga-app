<?php

namespace App\Filament\Resources\SuperadminUserResource\Pages;

use App\Filament\Resources\SuperadminUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSuperadminUser extends EditRecord
{
    protected static string $resource = SuperadminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
