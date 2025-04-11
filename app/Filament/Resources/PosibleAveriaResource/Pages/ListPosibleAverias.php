<?php

namespace App\Filament\Resources\PosibleAveriaResource\Pages;

use App\Filament\Resources\PosibleAveriaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPosibleAverias extends ListRecords
{
    protected static string $resource = PosibleAveriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
