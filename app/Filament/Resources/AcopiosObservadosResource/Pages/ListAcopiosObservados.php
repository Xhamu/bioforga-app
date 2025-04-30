<?php

namespace App\Filament\Resources\AcopiosObservadosResource\Pages;

use App\Filament\Resources\AcopiosObservadosResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAcopiosObservados extends ListRecords
{
    protected static string $resource = AcopiosObservadosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
