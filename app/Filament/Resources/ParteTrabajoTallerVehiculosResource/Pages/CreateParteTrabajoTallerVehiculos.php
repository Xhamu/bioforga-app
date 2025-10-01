<?php

namespace App\Filament\Resources\ParteTrabajoTallerVehiculosResource\Pages;

use App\Filament\Resources\ParteTrabajoTallerVehiculosResource;
use Auth;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateParteTrabajoTallerVehiculos extends CreateRecord
{
    protected static string $resource = ParteTrabajoTallerVehiculosResource::class;

    protected static bool $canCreateAnother = false;

    public function mount(): void
    {
        $model = static::getResource()::getModel();

        $abierto = $model::query()
            ->where('usuario_id', Auth::id())
            ->whereNull('fecha_hora_fin_taller_vehiculos')
            ->whereNull('deleted_at')
            ->first();

        if ($abierto) {
            Notification::make()
                ->title('Ya tienes un parte abierto')
                ->body('Debes cerrarlo antes de crear uno nuevo.')
                ->danger()
                ->send();

            $this->redirect(ParteTrabajoTallerVehiculosResource::getUrl('view', [
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
                    Select::make('taller_id')
                        ->label('Taller')
                        ->relationship('taller', 'nombre')
                        ->searchable()
                        ->preload()
                        ->required(),

                    // Máquina (todas)
                    Select::make('vehiculo_id')
                        ->label('Vehículo')
                        ->options(function () {
                            return \App\Models\Vehiculo::all()->mapWithKeys(function ($vehiculo) {
                                return [$vehiculo->id => "{$vehiculo->marca} {$vehiculo->modelo} ({$vehiculo->matricula})"];
                            })->toArray();
                        })
                        ->searchable()
                        ->required(),

                    TextInput::make('kilometros')
                        ->label('Kilometraje')
                        ->numeric()
                        ->minValue(0)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->form->fill();

                    $formData = array_merge(
                        $this->form->getState(),
                        [
                            'taller_id' => $data['taller_id'] ?? null,
                            'vehiculo_id' => $data['vehiculo_id'] ?? null,
                            'kilometros' => $data['kilometros'] ?? null,
                            'fecha_hora_inicio_taller_vehiculos' => now(),
                        ]
                    );

                    $this->record = static::getModel()::create($formData);

                    Notification::make()
                        ->success()
                        ->title('Trabajo iniciado correctamente')
                        ->send();

                    $this->redirect(ParteTrabajoTallerVehiculosResource::getUrl(name: 'view', parameters: ['record' => $this->record]));
                }),
        ];
    }
}
