<?php

namespace App\Filament\Resources\ParteTrabajoSuministroOperacionMaquinaResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroOperacionMaquinaResource;
use App\Models\Referencia;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateParteTrabajoSuministroOperacionMaquina extends CreateRecord
{
    protected static string $resource = ParteTrabajoSuministroOperacionMaquinaResource::class;

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
                    Select::make('referencia_id')
                        ->label('Referencia')
                        ->placeholder('- Selecciona una referencia -')
                        ->options(function () {
                            $usuarioId = $this->form->getState()['usuario_id'] ?? Filament::auth()->user()->id;

                            if (!$usuarioId) {
                                return [];
                            }

                            $referenciasIds = \DB::table('referencias_users')
                                ->where('user_id', $usuarioId)
                                ->pluck('referencia_id');

                            if ($referenciasIds->isEmpty()) {
                                return [];
                            }

                            $referencias = Referencia::whereIn('id', $referenciasIds)
                                ->with('proveedor', 'cliente')
                                ->get();

                            return $referencias->mapWithKeys(function ($referencia) {
                                return [
                                    $referencia->id => "{$referencia->referencia} | " .
                                        ($referencia->proveedor?->razon_social ?? $referencia->cliente?->razon_social ?? 'Sin interviniente') .
                                        " ({$referencia->monte_parcela}, {$referencia->ayuntamiento})"
                                ];
                            });
                        })
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('gps_inicio_trabajo')
                        ->label('GPS')
                        ->required(),

                    View::make('livewire.location-inicio-trabajo'),
                ])
                ->action(function (array $data, $livewire) {
                    // Guardar el estado del formulario antes de usarlo
                    $livewire->save(false);

                    $formData = $livewire->form->getState();

                    $this->record = static::getModel()::create([
                        ...$formData,
                        'referencia_id' => $data['referencia_id'],
                        'fecha_hora_inicio_trabajo' => now(),
                        'gps_inicio_trabajo' => $data['gps_inicio_trabajo'] ?? '0.0000, 0.0000',
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Trabajo iniciado correctamente')
                        ->send();

                    $this->redirect(ParteTrabajoSuministroOperacionMaquinaResource::getUrl('edit', ['record' => $this->record]));
                }),
        ];
    }
}
