<?php

namespace App\Filament\Resources\ParteTrabajoSuministroAveriaResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroAveriaResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateParteTrabajoSuministroAveria extends CreateRecord
{
    protected static string $resource = ParteTrabajoSuministroAveriaResource::class;

    protected static bool $canCreateAnother = false;

    public function mount(): void
    {
        $model = static::getResource()::getModel();

        $abierto = $model::query()
            ->where('usuario_id', Auth::id())
            ->whereNull('fecha_hora_fin_averia')
            ->whereNull('deleted_at')
            ->first();

        if ($abierto) {
            Notification::make()
                ->title('Ya tienes un parte abierto')
                ->body('Debes cerrarlo antes de crear uno nuevo.')
                ->danger()
                ->send();

            $this->redirect(ParteTrabajoSuministroAveriaResource::getUrl('view', [
                'record' => $abierto->getKey(),
            ]));
        }

        parent::mount();
    }

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
                    TextInput::make('gps_inicio_averia')
                        ->label('GPS')
                        ->required()
                        ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                    View::make('livewire.location-inicio-averia')->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    $formData = array_merge(
                        $this->form->getState(),
                        [
                            'fecha_hora_inicio_averia' => now(),
                            'gps_inicio_averia' => $data['gps_inicio_averia'] ?? '0.0000, 0.0000',
                        ]
                    );

                    $this->record = static::getModel()::create($formData);

                    Notification::make()
                        ->success()
                        ->title('Trabajo iniciado correctamente')
                        ->send();

                    $this->redirect(ParteTrabajoSuministroAveriaResource::getUrl(name: 'view', parameters: ['record' => $this->record]));
                }),
        ];
    }
}
