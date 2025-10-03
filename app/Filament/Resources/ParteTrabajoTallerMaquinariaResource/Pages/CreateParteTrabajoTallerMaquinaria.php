<?php

namespace App\Filament\Resources\ParteTrabajoTallerMaquinariaResource\Pages;

use App\Filament\Resources\ParteTrabajoTallerMaquinariaResource;
use Auth;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\View;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Forms\Components\TextInput\Mask;

class CreateParteTrabajoTallerMaquinaria extends CreateRecord
{
    protected static string $resource = ParteTrabajoTallerMaquinariaResource::class;

    protected static bool $canCreateAnother = false;

    public function mount(): void
    {
        $model = static::getResource()::getModel();

        $abierto = $model::query()
            ->where('usuario_id', Auth::id())
            ->whereNull('fecha_hora_fin_taller_maquinaria')
            ->whereNull('deleted_at')
            ->first();

        if ($abierto) {
            Notification::make()
                ->title('Ya tienes un parte abierto')
                ->body('Debes cerrarlo antes de crear uno nuevo.')
                ->danger()
                ->send();

            $this->redirect(ParteTrabajoTallerMaquinariaResource::getUrl('view', [
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
                    Select::make('maquina_id')
                        ->label('Máquina')
                        ->options(function () {
                            return \App\Models\Maquina::all()->mapWithKeys(function ($maquina) {
                                return [$maquina->id => "{$maquina->marca} {$maquina->modelo}"];
                            })->toArray();
                        })
                        ->searchable()
                        ->required(),

                    TimePicker::make('horas_servicio')
                        ->label('Horas de servicio')
                        ->withoutSeconds()
                        ->required()
                ])
                ->action(function (array $data) {
                    $this->form->fill();

                    $formData = array_merge(
                        $this->form->getState(),
                        [
                            'taller_id' => $data['taller_id'] ?? null,
                            'maquina_id' => $data['maquina_id'] ?? null,
                            'horas_servicio' => $data['horas_servicio'] ?? null,
                            'fecha_hora_inicio_taller_maquinaria' => now(),
                        ]
                    );

                    $this->record = static::getModel()::create($formData);

                    Notification::make()
                        ->success()
                        ->title('Trabajo iniciado correctamente')
                        ->send();

                    $this->redirect(ParteTrabajoTallerMaquinariaResource::getUrl(name: 'view', parameters: ['record' => $this->record]));
                }),
        ];
    }
}
