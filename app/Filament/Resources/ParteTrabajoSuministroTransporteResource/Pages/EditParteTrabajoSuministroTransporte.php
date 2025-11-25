<?php

namespace App\Filament\Resources\ParteTrabajoSuministroTransporteResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroTransporteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParteTrabajoSuministroTransporte extends EditRecord
{
    protected static string $resource = ParteTrabajoSuministroTransporteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('volver_atras')
                ->label('Volver atrás')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->action(fn() => redirect()->back()) // No funciona desde un Action normal
                // Mejor usar JS:
                // ->extraAttributes(['onclick' => 'history.back()'])
                ->extraAttributes(['onclick' => 'history.back(); return false;']),

            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
