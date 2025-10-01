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
use App\Models\Maquina;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateParteTrabajoSuministroOperacionMaquina extends CreateRecord
{
    protected static string $resource = ParteTrabajoSuministroOperacionMaquinaResource::class;

    protected static bool $canCreateAnother = false;

    public function mount(): void
    {
        $model = static::getResource()::getModel();

        $abierto = $model::query()
            ->where('usuario_id', Auth::id())
            ->whereNull('fecha_hora_fin_trabajo')
            ->whereNull('deleted_at')
            ->first();

        if ($abierto) {
            Notification::make()
                ->title('Ya tienes un parte abierto')
                ->body('Debes cerrarlo antes de crear uno nuevo.')
                ->danger()
                ->send();

            $this->redirect(ParteTrabajoSuministroOperacionMaquinaResource::getUrl('view', [
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
                    Select::make('usuario_id')
                        ->label('Usuario')
                        ->relationship(
                            name: 'usuario',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn($query) => self::getUsuariosPermitidosQuery()
                        )
                        ->getOptionLabelFromRecordUsing(fn($record) => $record->name . ' ' . $record->apellidos)
                        ->searchable()
                        ->preload()
                        ->placeholder('- Selecciona un usuario -')
                        ->default(function () {
                            $usuarios = self::getUsuariosPermitidosQuery()->pluck('id');
                            return $usuarios->count() === 1 ? $usuarios->first() : null;
                        })
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (!$state) {
                                $set('maquina_id', null);
                                return;
                            }

                            // Obtener las máquinas donde el usuario esté vinculado en la tabla pivote
                            $maquinas = Maquina::whereHas('operarios', fn($q) => $q->where('users.id', $state))
                                ->pluck('id');

                            $set('maquina_id', $maquinas->count() === 1 ? $maquinas->first() : null);
                        }),

                    Select::make('maquina_id')
                        ->label('Máquina')
                        ->options(function (callable $get) {
                            $usuarioId = $get('usuario_id');

                            if (!$usuarioId) {
                                return [];
                            }

                            // Filtrar máquinas por relación many-to-many con el usuario seleccionado
                            $maquinas = Maquina::whereHas('operarios', fn($q) => $q->where('users.id', $usuarioId))
                                ->get();

                            if ($maquinas->isEmpty()) {
                                return [];
                            }

                            return $maquinas->mapWithKeys(fn($maquina) => [
                                $maquina->id => "{$maquina->marca} {$maquina->modelo}"
                            ])->toArray();
                        })
                        ->placeholder('- Selecciona una máquina -')
                        ->default(function (callable $get) {
                            $usuarioId = $get('usuario_id') ?? Filament::auth()->user()->id;

                            $maquinas = Maquina::whereHas('operarios', fn($q) => $q->where('users.id', $usuarioId))
                                ->pluck('id');

                            return $maquinas->count() === 1 ? $maquinas->first() : null;
                        })
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            $maquina = Maquina::find($state);
                            if ($maquina) {
                                $set('tipo_trabajo', $maquina->tipo_trabajo);
                            }
                        })
                        ->searchable()
                        ->required(),

                    Select::make('tipo_trabajo')
                        ->label('Tipo de trabajo')
                        ->options([
                            'astillado' => 'Astillado',
                            'triturado' => 'Triturado',
                            'pretiturado' => 'Pretiturado',
                            'saca' => 'Saca',
                            'tala' => 'Tala',
                            'cizallado' => 'Cizallado',
                            'carga' => 'Carga',
                            'transporte' => 'Transporte',
                        ])
                        ->placeholder('- Selecciona el tipo de trabajo -')
                        ->required()
                        ->searchable()
                        ->afterStateHydrated(function (callable $get, callable $set, $state) {
                            if ($state)
                                return;

                            $maquinaId = $get('maquina_id');

                            if ($maquinaId) {
                                $maquina = Maquina::find($maquinaId);
                                if ($maquina) {
                                    $set('tipo_trabajo', $maquina->tipo_trabajo);
                                }
                            }
                        }),

                    Select::make('referencia_id')
                        ->label('Referencia')
                        ->placeholder('- Selecciona una referencia -')
                        ->options(function (callable $get) {
                            $usuarioId = $get('usuario_id');
                            if (!$usuarioId)
                                return [];

                            $referenciasIds = DB::table('referencias_users')
                                ->where('user_id', $usuarioId)
                                ->pluck('referencia_id');

                            if ($referenciasIds->isEmpty())
                                return [];

                            return Referencia::whereIn('id', $referenciasIds)->with('proveedor', 'cliente')->get()
                                ->mapWithKeys(fn($r) => [
                                    $r->id => "$r->referencia | " .
                                        ($r->proveedor?->razon_social ?? $r->cliente?->razon_social ?? 'Sin interviniente') .
                                        " ({$r->monte_parcela}, {$r->ayuntamiento})"
                                ]);
                        })
                        ->default(function (callable $get) {
                            $usuarioId = $get('usuario_id');
                            if (!$usuarioId)
                                return null;

                            // Obtener las referencias reales que se van a mostrar en el select
                            $referenciasIds = DB::table('referencias_users')
                                ->where('user_id', $usuarioId)
                                ->pluck('referencia_id');

                            $referencias = Referencia::whereIn('id', $referenciasIds)->pluck('id');

                            return $referencias->count() === 1 ? $referencias->first() : null;
                        })
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('gps_inicio_trabajo')
                        ->label('GPS')
                        ->required()
                        ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                    View::make('livewire.location-inicio-trabajo'),
                ])
                ->action(function (array $data) {
                    $this->record = static::getModel()::create([
                        'usuario_id' => $data['usuario_id'],
                        'maquina_id' => $data['maquina_id'],
                        'tipo_trabajo' => $data['tipo_trabajo'],
                        'referencia_id' => $data['referencia_id'],
                        'fecha_hora_inicio_trabajo' => now(),
                        'gps_inicio_trabajo' => $data['gps_inicio_trabajo'] ?? '0.0000, 0.0000',
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Trabajo iniciado correctamente')
                        ->send();

                    $this->redirect(ParteTrabajoSuministroOperacionMaquinaResource::getUrl(name: 'view', parameters: ['record' => $this->record]));
                }),
        ];
    }

    private static function getUsuariosPermitidosQuery()
    {
        $user = Filament::auth()->user();

        return $user->hasAnyRole(['operarios', 'proveedor de servicio'])
            ? User::query()->where('id', $user->id)
            : User::query()->whereHas('roles', fn($q) =>
                $q->whereIn('name', ['administración', 'administrador', 'operarios', 'proveedor de servicio']));
    }
}