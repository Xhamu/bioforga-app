<?php

namespace App\Filament\Resources\SuperadminUserResource\Pages;

use App\Filament\Resources\SuperadminUserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSuperadminUser extends CreateRecord
{
    protected static string $resource = SuperadminUserResource::class;
}
