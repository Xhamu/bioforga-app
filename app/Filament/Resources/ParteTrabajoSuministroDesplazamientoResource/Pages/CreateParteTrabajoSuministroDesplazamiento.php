<?php

namespace App\Filament\Resources\ParteTrabajoSuministroDesplazamientoResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroDesplazamientoResource;
use App\Models\Maquina;
use App\Models\User;
use App\Models\Vehiculo;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

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
                    Select::make('usuario_id')
                        ->relationship(
                            'usuario',
                            'name',
                            modifyQueryUsing: function ($query) {
                                $user = Filament::auth()->user();

                                if ($user->hasAnyRole(['superadmin', 'administrador', 'administración'])) {
                                    // Ver todos menos los superadmin
                                    $query->whereDoesntHave('roles', function ($q) {
                                        $q->where('name', 'superadmin');
                                    });
                                } else {
                                    // Ver solo a sí mismo
                                    $query->where('id', $user->id);
                                }
                            }
                        )
                        ->getOptionLabelFromRecordUsing(fn($record) => $record->name . ' ' . $record->apellidos)
                        ->searchable()
                        ->preload()
                        ->default(Filament::auth()->user()->id)
                        ->required()
                        ->reactive(),

                    Select::make('flota')
                        ->label('Medio usado')
                        ->searchable()
                        ->options([
                            'vehiculo' => 'Vehículo',
                            'maquina' => 'Máquina',
                        ])
                        ->validationMessages([
                            'required' => 'El campo Medio usado es obligatorio.',
                        ])
                        ->required()
                        ->reactive(), // Necesario para que actualice el valor del select

                    // Vehículo (visible solo si flota = vehiculo)
                    Select::make('vehiculo_id')
                        ->label('Vehículo')
                        ->relationship(
                            name: 'vehiculo',
                            titleAttribute: 'marca',
                            modifyQueryUsing: fn($query, callable $get) => $query->when(
                                $get('usuario_id'),
                                fn($q, $usuarioId) => $q->whereJsonContains('conductor_habitual', (string) $usuarioId)
                            )
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn($record) => $record->marca . ' ' . $record->modelo . ' (' . $record->matricula . ')'
                        )
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->visible(fn($get) => $get('flota') === 'vehiculo')
                        ->required(fn($get) => $get('flota') === 'vehiculo')
                        ->default(function (callable $get) {
                            $usuarioId = $get('usuario_id');
                            if (!$usuarioId)
                                return null;

                            $vehiculos = Vehiculo::whereJsonContains('conductor_habitual', (string) $usuarioId)->get();
                            return $vehiculos->count() === 1 ? $vehiculos->first()->id : null;
                        }),

                    Select::make('maquina_id')
                        ->label('Máquina')
                        ->relationship(
                            name: 'maquina',
                            titleAttribute: 'marca',
                            modifyQueryUsing: fn($query, callable $get) => $query->when(
                                $get('usuario_id'),
                                fn($q, $usuarioId) =>
                                $q->whereHas('operarios', fn($q2) => $q2->where('users.id', (string) $usuarioId))
                            )
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn($record) => $record->marca . ' ' . $record->modelo
                        )
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->visible(fn($get) => $get('flota') === 'maquina')
                        ->required(fn($get) => $get('flota') === 'maquina')
                        ->default(function (callable $get) {
                            $usuarioId = $get('usuario_id');
                            if (!$usuarioId)
                                return null;

                            // Obtener las máquinas que realmente se muestran en el select
                            $maquinas = Maquina::whereHas('operarios', fn($q) => $q->where('users.id', (string) $usuarioId))
                                ->pluck('id');

                            return $maquinas->count() === 1 ? $maquinas->first() : null;
                        }),

                    Select::make('destino')
                        ->label('Destino')
                        ->searchable()
                        ->options([
                            'referencia' => 'Referencia',
                            'taller' => 'Taller',
                            'otro' => 'Otro',
                        ])
                        ->validationMessages([
                            'required' => 'El campo Destino es obligatorio.',
                        ])
                        ->required()
                        ->reactive(),

                    Select::make('referencia_id')
                        ->label('Referencia')
                        ->searchable()
                        ->options(function (callable $get) {
                            $usuarioId = $get('usuario_id');

                            if (!$usuarioId) {
                                return [];
                            }

                            $usuario = User::find($usuarioId);

                            return $usuario?->referencias()
                                ->select('referencias.id', 'referencias.referencia', 'referencias.ayuntamiento', 'referencias.monte_parcela')
                                ->get()
                                ->mapWithKeys(function ($ref) {
                                    $label = "{$ref->referencia} | ({$ref->ayuntamiento}, {$ref->monte_parcela})";
                                    return [$ref->id => $label];
                                }) ?? [];
                        })
                        ->afterStateHydrated(function ($component, $state) {
                            $options = $component->getOptions();

                            if (count($options) === 1 && blank($state)) {
                                $component->state(array_key_first($options));
                            }
                        })
                        ->visible(fn($get) => $get('destino') === 'referencia')
                        ->required(fn($get) => $get('destino') === 'referencia')
                        ->preload(),

                    Select::make('taller_id')
                        ->label('Taller')
                        ->searchable()
                        ->options(function () {
                            return \App\Models\Taller::pluck('nombre', 'id');
                        })
                        ->afterStateHydrated(function ($component, $state) {
                            $options = $component->getOptions();

                            if (count($options) === 1 && blank($state)) {
                                $component->state(array_key_first($options));
                            }
                        })
                        ->visible(fn($get) => $get('destino') === 'taller')
                        ->required(fn($get) => $get('destino') === 'taller'),

                    TextInput::make('gps_inicio_desplazamiento')
                        ->label('GPS')
                        ->required()
                        ->validationMessages([
                            'required' => 'El campo GPS es obligatorio.',
                        ])
                        ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                    View::make('livewire.location-inicio-desplazamiento')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    $this->form->fill(); // rellena lo que ya hay en el formulario
        
                    $formData = array_merge(
                        $this->form->getState(),
                        [
                            'usuario_id' => $data['usuario_id'],
                            'destino' => $data['destino'],
                            'fecha_hora_inicio_desplazamiento' => now(),
                            'gps_inicio_desplazamiento' => $data['gps_inicio_desplazamiento'] ?? '0.0000, 0.0000',
                            'referencia_id' => $data['referencia_id'] ?? null,
                            'taller_id' => $data['taller_id'] ?? null,
                            'maquina_id' => $data['maquina_id'] ?? null,
                            'vehiculo_id' => $data['vehiculo_id'] ?? null,
                        ]
                    );

                    $this->record = static::getModel()::create($formData);

                    Notification::make()
                        ->success()
                        ->title('Trabajo iniciado correctamente')
                        ->send();

                    $this->redirect("/partes-trabajo-suministro-desplazamiento/{$this->record->id}");
                }),
        ];
    }
}
