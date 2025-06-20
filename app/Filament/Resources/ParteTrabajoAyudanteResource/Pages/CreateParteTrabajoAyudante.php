<?php

namespace App\Filament\Resources\ParteTrabajoAyudanteResource\Pages;

use App\Filament\Resources\ParteTrabajoAyudanteResource;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateParteTrabajoAyudante extends CreateRecord
{
    protected static string $resource = ParteTrabajoAyudanteResource::class;

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
                    Select::make('eleccion')
                        ->label('Medio utilizado')
                        ->options([
                            'maquina' => 'Máquina',
                            'vehiculo' => 'Vehículo',
                        ])
                        ->searchable()
                        ->reactive()
                        ->required(),

                    Select::make('maquina_id')
                        ->label('Selecciona máquina')
                        ->options(
                            \App\Models\Maquina::all()->mapWithKeys(fn($m) => [
                                $m->id => $m->marca . ' ' . $m->modelo,
                            ])->toArray()
                        )
                        ->visible(fn($get) => $get('eleccion') === 'maquina')
                        ->searchable(),

                    Select::make('vehiculo_id')
                        ->label('Selecciona vehículo')
                        ->options(
                            \App\Models\Vehiculo::all()->mapWithKeys(fn($m) => [
                                $m->id => $m->marca . ' ' . $m->modelo . ' (' . $m->matricula . ')',
                            ])->toArray()
                        )->visible(fn($get) => $get('eleccion') === 'vehiculo')
                        ->searchable(),

                    Select::make('tipologia')
                        ->label('Tipología')
                        ->options(function () {
                            return \App\Models\Tipologia::pluck('nombre', 'nombre');
                        })
                        ->searchable()
                        ->required(),

                    TextInput::make('gps_inicio_ayudante')
                        ->label('GPS')
                        ->required(),

                    View::make('livewire.location-inicio-ayudante')->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    $formData = array_merge(
                        $this->form->getState(),
                        [
                            'tipologia' => $data['tipologia'] ?? null,
                            'maquina_id' => $data['maquina_id'] ?? null,
                            'vehiculo_id' => $data['vehiculo_id'] ?? null,
                            'fecha_hora_inicio_ayudante' => now(),
                            'gps_inicio_ayudante' => $data['gps_inicio_ayudante'] ?? '0.0000, 0.0000',
                        ]
                    );

                    $this->record = static::getModel()::create($formData);

                    Notification::make()
                        ->success()
                        ->title('Trabajo iniciado correctamente')
                        ->send();

                    $this->redirect(ParteTrabajoAyudanteResource::getUrl());
                }),
        ];
    }
}
