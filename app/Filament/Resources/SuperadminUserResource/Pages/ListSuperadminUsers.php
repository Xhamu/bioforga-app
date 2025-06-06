<?php

namespace App\Filament\Resources\SuperadminUserResource\Pages;

use App\Filament\Resources\SuperadminUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSuperadminUsers extends ListRecords
{
    protected static string $resource = SuperadminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
