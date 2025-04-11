<?php

namespace App\Filament\Resources\TallerResource\Pages;

use App\Filament\Resources\TallerResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTaller extends CreateRecord
{
    protected static string $resource = TallerResource::class;

    protected function afterCreate()
    {
        return redirect('/talleres');
    }
}
