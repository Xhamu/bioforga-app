<?php

namespace App\Filament\Resources\AcopiosObservadosResource\Pages;

use App\Filament\Resources\AcopiosObservadosResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAcopiosObservados extends ViewRecord
{
    protected static string $resource = AcopiosObservadosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
