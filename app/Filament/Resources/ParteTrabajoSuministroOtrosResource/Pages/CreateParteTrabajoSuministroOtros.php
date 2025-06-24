<?php

namespace App\Filament\Resources\ParteTrabajoSuministroOtrosResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroOtrosResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateParteTrabajoSuministroOtros extends CreateRecord
{
    protected static string $resource = ParteTrabajoSuministroOtrosResource::class;

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
                    Textarea::make('descripcion')
                        ->label('Descripción del trabajo realizado')
                        ->required(),

                    TextInput::make('gps_inicio_otros')
                        ->label('GPS')
                        ->required()
                        ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                    View::make('livewire.location-inicio-otros')->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    $formData = array_merge(
                        $this->form->getState(),
                        [
                            'descripcion' => $data['descripcion'] ?? null,
                            'fecha_hora_inicio_otros' => now(),
                            'gps_inicio_otros' => $data['gps_inicio_otros'] ?? '0.0000, 0.0000',
                        ]
                    );

                    $this->record = static::getModel()::create($formData);

                    Notification::make()
                        ->success()
                        ->title('Trabajo iniciado correctamente')
                        ->send();

                    $this->redirect(ParteTrabajoSuministroOtrosResource::getUrl(name: 'view', parameters: ['record' => $this->record]));
                }),
        ];
    }
}
