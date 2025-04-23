<?php

namespace App\Filament\Resources\ParteTrabajoSuministroDesplazamientoResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroDesplazamientoResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateParteTrabajoSuministroDesplazamiento extends CreateRecord
{
    protected static string $resource = ParteTrabajoSuministroDesplazamientoResource::class;

    public function getFormActions(): array
    {
        return [
            Action::make('iniciarTrabajo')
                ->label('Iniciar trabajo')
                ->color('success')
                ->modalHeading('Iniciar trabajo')
                ->modalSubmitActionLabel('Iniciar')
                ->modalWidth('xl')
                ->form([
                    TextInput::make('gps_inicio_desplazamiento')
                        ->label('GPS')
                        ->required(),

                    View::make('livewire.location-inicio-desplazamiento'),
                ])
                ->action(function (array $data) {
                    $this->form->fill(); // rellena lo que ya hay en el formulario
        
                    $formData = array_merge(
                        $this->form->getState(),
                        [
                            'fecha_hora_inicio_desplazamiento' => now(),
                            'gps_inicio_desplazamiento' => $data['gps_inicio_desplazamiento'] ?? '0.0000, 0.0000',
                        ]
                    );

                    $this->record = static::getModel()::create($formData);

                    Notification::make()
                        ->success()
                        ->title('Trabajo iniciado correctamente')
                        ->send();

                    $this->redirect(ParteTrabajoSuministroDesplazamientoResource::getUrl('edit', ['record' => $this->record]));
                }),
        ];
    }
}
