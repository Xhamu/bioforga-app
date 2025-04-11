<?php

namespace App\Filament\Resources\PosibleAveriaResource\Pages;

use App\Filament\Resources\PosibleAveriaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPosibleAveria extends ViewRecord
{
    protected static string $resource = PosibleAveriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
