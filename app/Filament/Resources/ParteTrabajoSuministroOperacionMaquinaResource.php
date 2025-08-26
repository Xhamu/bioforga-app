<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoSuministroOperacionMaquinaResource\Pages;
use App\Models\Maquina;
use App\Models\ParteTrabajoSuministroOperacionMaquina;
use App\Models\Referencia;
use App\Models\User;
use Arr;
use Carbon\Carbon;
use Filament\Forms\Components\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Filament\Forms\Get;
use Filament\Forms\Components\Actions\Action as FormAction;
use Illuminate\Database\Eloquent\Model;

class ParteTrabajoSuministroOperacionMaquinaResource extends Resource
{
    protected static ?string $model = ParteTrabajoSuministroOperacionMaquina::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Partes de trabajo';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'partes-trabajo-suministro-operacion-maquina';
    public static ?string $label = 'operación máquina';
    public static ?string $pluralLabel = 'Operaciones máquina';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos generales')
                    ->schema([
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

                                $maquinas = \App\Models\Maquina::where('operario_id', $state)->pluck('id');
                                $set('maquina_id', $maquinas->count() === 1 ? $maquinas->first() : null);
                            }),

                        Select::make('maquina_id')
                            ->label('Máquina')
                            ->options(function (callable $get) {
                                $usuarioId = $get('usuario_id');

                                if (!$usuarioId) {
                                    return [];
                                }

                                $maquinas = \App\Models\Maquina::where('operario_id', $usuarioId)->get();

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
                                $maquinas = \App\Models\Maquina::where('operario_id', $usuarioId)->pluck('id');
                                return $maquinas->count() === 1 ? $maquinas->first() : null;
                            })
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $maquina = \App\Models\Maquina::find($state);
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
                                    $maquina = \App\Models\Maquina::find($maquinaId);
                                    if ($maquina) {
                                        $set('tipo_trabajo', $maquina->tipo_trabajo);
                                    }
                                }
                            }),
                    ])
                    ->visible(fn($record) => filled($record?->fecha_hora_inicio_trabajo))
                    ->columns(3),

                Section::make('')
                    ->schema([
                        Select::make('referencia_id')
                            ->label('Referencia')
                            ->options(function () {
                                return \App\Models\Referencia::with('proveedor', 'cliente')->get()
                                    ->mapWithKeys(fn($r) => [
                                        $r->id => "{$r->referencia} | " .
                                            ($r->proveedor?->razon_social ?? $r->cliente?->razon_social ?? 'Sin interviniente') .
                                            " ({$r->monte_parcela}, {$r->ayuntamiento})"
                                    ]);
                            })
                            ->searchable()
                            ->disabled(fn($record) => !$record || !$record->exists)
                            ->visible(fn($record) => filled($record?->referencia_id)),

                        Placeholder::make('')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_trabajo) {
                                    return new HtmlString('<p>Estado actual: <strong>Sin iniciar</strong></p>');
                                }

                                $estado = 'Desconocido';
                                $totalMinutos = 0;

                                $inicio = Carbon::parse($record->getRawOriginal('fecha_hora_inicio_trabajo'))->timezone('Europe/Madrid');
                                $parada = $record->fecha_hora_parada_trabajo
                                    ? Carbon::parse($record->getRawOriginal('fecha_hora_parada_trabajo'))->timezone('Europe/Madrid')
                                    : null;

                                $reanudacion = $record->fecha_hora_reanudacion_trabajo
                                    ? Carbon::parse($record->getRawOriginal('fecha_hora_reanudacion_trabajo'))->timezone('Europe/Madrid')
                                    : null;

                                $fin = $record->fecha_hora_fin_trabajo
                                    ? Carbon::parse($record->getRawOriginal('fecha_hora_fin_trabajo'))->timezone('Europe/Madrid')
                                    : null;

                                if ($fin) {
                                    if ($parada && $reanudacion) {
                                        $totalMinutos = $inicio->diffInMinutes($parada) + $reanudacion->diffInMinutes($fin);
                                    } else {
                                        $totalMinutos = $inicio->diffInMinutes($fin);
                                    }
                                    $estado = 'Finalizado';
                                } elseif ($reanudacion) {
                                    $totalMinutos = $inicio->diffInMinutes($parada) + $reanudacion->diffInMinutes(Carbon::now('Europe/Madrid'));
                                    $estado = 'Reanudado';
                                } elseif ($parada) {
                                    $totalMinutos = $inicio->diffInMinutes($parada);
                                    $estado = 'Pausado';
                                } else {
                                    $totalMinutos = $inicio->diffInMinutes(Carbon::now('Europe/Madrid'));
                                    $estado = 'Trabajando';
                                }

                                $horas = floor($totalMinutos / 60);
                                $minutos = $totalMinutos % 60;

                                $emoji = match ($estado) {
                                    'Trabajando' => '🟢',
                                    'Pausado' => '⏸️',
                                    'Reanudado' => '🔁',
                                    'Finalizado' => '✅',
                                    default => '❓',
                                };

                                $gpsInicio = $record->gps_inicio_trabajo ? ' (<a href="https://maps.google.com/?q=' . $record->gps_inicio_trabajo . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)' : '';
                                $gpsPausa = $record->gps_parada_trabajo ? ' (<a href="https://maps.google.com/?q=' . $record->gps_parada_trabajo . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)' : '';
                                $gpsReanudar = $record->gps_reanudacion_trabajo ? ' (<a href="https://maps.google.com/?q=' . $record->gps_reanudacion_trabajo . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)' : '';
                                $gpsFin = $record->gps_fin_trabajo ? ' (<a href="https://maps.google.com/?q=' . $record->gps_fin_trabajo . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)' : '';

                                $tabla = '
                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <table class="w-full text-sm text-left text-gray-700 dark:text-gray-200">
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Estado actual</th>
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">' . $emoji . ' ' . $estado . '</td>
                            </tr>
                            <tr>
                                <th class="px-4 py-3">Hora de inicio</th>
                                <td class="px-4 py-3">' . $inicio->format('H:i') . $gpsInicio . '</td>
                            </tr>
                            <tr>
                                <th class="px-4 py-3">Hora de pausa</th>
                                <td class="px-4 py-3">' . ($parada ? $parada->format('H:i') . $gpsPausa : '-') . '</td>
                            </tr>
                            <tr>
                                <th class="px-4 py-3">Hora de reanudación</th>
                                <td class="px-4 py-3">' . ($reanudacion ? $reanudacion->format('H:i') . $gpsReanudar : '-') . '</td>
                            </tr>
                            <tr>
                                <th class="px-4 py-3">Hora de finalización</th>
                                <td class="px-4 py-3">' . ($fin ? $fin->format('H:i') . $gpsFin : '-') . '</td>
                            </tr>
                            <tr class="bg-gray-50 dark:bg-gray-800 border-t">
                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Tiempo total</th>
                                <td class="px-4 py-3 font-semibold">' . $horas . 'h ' . $minutos . 'min</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                ';
                                return new HtmlString($tabla);
                            })
                            ->visible(function () {
                                return !Filament::auth()->user()?->hasAnyRole(['superadmin', 'administración']);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Section::make('Fechas y horas')
                    ->schema([
                        DateTimePicker::make('fecha_hora_inicio_trabajo')
                            ->label('Hora de inicio')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_inicio_trabajo) {
                                    return Actions\Action::make('ver_gps_inicio')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_inicio_trabajo, shouldOpenInNewTab: true);
                                }
                                return null;
                            }),

                        DateTimePicker::make('fecha_hora_parada_trabajo')
                            ->label('Hora de pausa')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_parada_trabajo) {
                                    return Actions\Action::make('ver_gps_pausa')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_parada_trabajo, shouldOpenInNewTab: true);
                                }
                                return null;
                            }),

                        DateTimePicker::make('fecha_hora_reanudacion_trabajo')
                            ->label('Hora de reanudación')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_reanudacion_trabajo) {
                                    return Actions\Action::make('ver_gps_reanudar')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_reanudacion_trabajo, shouldOpenInNewTab: true);
                                }
                                return null;
                            }),

                        DateTimePicker::make('fecha_hora_fin_trabajo')
                            ->label('Hora de finalización')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_fin_trabajo) {
                                    return Actions\Action::make('ver_gps_fin')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_fin_trabajo, shouldOpenInNewTab: true);
                                }
                                return null;
                            }),

                        Placeholder::make('tiempo_total')
                            ->label('Tiempo total')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_trabajo) {
                                    return 'Sin iniciar';
                                }

                                $inicio = Carbon::parse($record->fecha_hora_inicio_trabajo)->timezone('Europe/Madrid');
                                $fin = $record->fecha_hora_fin_trabajo
                                    ? Carbon::parse($record->fecha_hora_fin_trabajo)->timezone('Europe/Madrid')
                                    : Carbon::now('Europe/Madrid');
                                $parada = $record->fecha_hora_parada_trabajo
                                    ? Carbon::parse($record->fecha_hora_parada_trabajo)->timezone('Europe/Madrid')
                                    : null;
                                $reanudacion = $record->fecha_hora_reanudacion_trabajo
                                    ? Carbon::parse($record->fecha_hora_reanudacion_trabajo)->timezone('Europe/Madrid')
                                    : null;

                                $totalMinutos = 0;

                                if ($record->fecha_hora_fin_trabajo) {
                                    if ($parada && $reanudacion) {
                                        $totalMinutos = $inicio->diffInMinutes($parada) + $reanudacion->diffInMinutes($fin);
                                    } else {
                                        $totalMinutos = $inicio->diffInMinutes($fin);
                                    }
                                } elseif ($reanudacion) {
                                    $totalMinutos = $inicio->diffInMinutes($parada) + $reanudacion->diffInMinutes(Carbon::now('Europe/Madrid'));
                                } elseif ($parada) {
                                    $totalMinutos = $inicio->diffInMinutes($parada);
                                } else {
                                    $totalMinutos = $inicio->diffInMinutes(Carbon::now('Europe/Madrid'));
                                }

                                $horas = floor($totalMinutos / 60);
                                $minutos = $totalMinutos % 60;

                                return "{$horas}h {$minutos}min";
                            }),
                    ])
                    ->columns(2)
                    ->visible(
                        fn($record) =>
                        $record &&
                        $record->exists &&
                        Filament::auth()->user()?->hasAnyRole(['superadmin', 'administración'])
                    ),

                Section::make('Observaciones')
                    ->schema([
                        Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->placeholder('Escribe aquí cualquier detalle adicional...')
                            ->rows(8)
                            ->columnSpanFull()
                            ->maxLength(5000),

                        Actions::make([
                            FormAction::make('addObservaciones')
                                ->label('Añadir observaciones')
                                ->icon('heroicon-m-plus')
                                ->color('success')
                                ->modalHeading('Añadir observaciones')
                                ->modalSubmitActionLabel('Guardar')
                                ->modalWidth('lg')
                                ->form([
                                    Textarea::make('nueva_observacion')
                                        ->label('Nueva observación')
                                        ->placeholder('Escribe aquí la nueva observación...')
                                        ->rows(3)
                                        ->required(),
                                ])
                                ->action(function (Model $record, array $data) {
                                    $append = trim($data['nueva_observacion'] ?? '');
                                    if ($append === '') {
                                        return;
                                    }

                                    $stamp = now()->timezone('Europe/Madrid')->format('d/m/Y H:i');
                                    $prev = (string) ($record->observaciones ?? '');

                                    $nuevo = ($prev !== '' ? $prev . "\n" : '')
                                        . '[' . $stamp . '] ' . $append;

                                    $record->update(['observaciones' => $nuevo]);

                                    Notification::make()
                                        ->title('Observaciones añadidas')
                                        ->success()
                                        ->send();

                                    return redirect(request()->header('Referer'));
                                }),
                        ])
                            ->visible(function ($record) {
                                if (!$record)
                                    return false;

                                return (
                                    $record->fecha_hora_inicio_trabajo && !$record->fecha_hora_fin_trabajo
                                );
                            })->fullWidth()
                    ]),

                Section::make()
                    ->visible(function ($record) {
                        if (!$record)
                            return false;

                        return (
                            $record->fecha_hora_inicio_trabajo && !$record->fecha_hora_parada_trabajo && !$record->fecha_hora_fin_trabajo ||

                            $record->fecha_hora_parada_trabajo && !$record->fecha_hora_reanudacion_trabajo && !$record->fecha_hora_fin_trabajo ||

                            $record->fecha_hora_inicio_trabajo && !$record->fecha_hora_fin_trabajo
                        );
                    })
                    ->schema([
                        Actions::make([
                            Action::make('Parar')
                                ->label('Parar trabajo')
                                ->color('warning')
                                ->button()
                                ->extraAttributes(['id' => 'btn-parar-trabajo', 'class' => 'w-full'])
                                ->visible(
                                    fn($record) =>
                                    $record &&
                                    $record->fecha_hora_inicio_trabajo &&
                                    !$record->fecha_hora_parada_trabajo &&
                                    !$record->fecha_hora_fin_trabajo
                                )
                                ->requiresConfirmation()
                                ->form([
                                    Hidden::make('gps_parada_trabajo'),
                                ])
                                ->action(function (array $data, $record) {
                                    $record->update([
                                        'fecha_hora_parada_trabajo' => now(),
                                        'gps_parada_trabajo' => $data['gps_parada_trabajo'],
                                    ]);

                                    Notification::make()
                                        ->info()
                                        ->title('Trabajo pausado')
                                        ->send();
                                }),

                            Action::make('Reanudar')
                                ->label('Reanudar trabajo')
                                ->color('info')
                                ->extraAttributes(['id' => 'btn-reanudar-trabajo', 'class' => 'w-full'])
                                ->visible(
                                    fn($record) =>
                                    $record &&
                                    $record->fecha_hora_parada_trabajo &&
                                    !$record->fecha_hora_reanudacion_trabajo &&
                                    !$record->fecha_hora_fin_trabajo
                                )
                                ->button()
                                ->requiresConfirmation()
                                ->form([
                                    Hidden::make('gps_reanudacion_trabajo'),
                                ])
                                ->action(function (array $data, $record) {
                                    $record->update([
                                        'fecha_hora_reanudacion_trabajo' => now(),
                                        'gps_reanudacion_trabajo' => $data['gps_reanudacion_trabajo'],
                                    ]);

                                    Notification::make()
                                        ->success()
                                        ->title('Trabajo reanudado')
                                        ->send();
                                }),

                            Action::make('Finalizar')
                                ->label('Finalizar trabajo')
                                ->color('danger')
                                ->extraAttributes(['class' => 'w-full'])
                                ->visible(function ($record) {
                                    if (!$record) {
                                        return false;
                                    }

                                    if (!$record->fecha_hora_inicio_trabajo || $record->fecha_hora_fin_trabajo) {
                                        return false;
                                    }

                                    if ($record->fecha_hora_parada_trabajo && !$record->fecha_hora_reanudacion_trabajo) {
                                        return false;
                                    }

                                    return true;
                                })

                                ->button()
                                ->modalHeading('Finalizar trabajo')
                                ->modalSubmitActionLabel('Finalizar')
                                ->modalWidth('xl')
                                ->form(function (Get $get) {
                                    $tipoHoras = Maquina::find($get('maquina_id'))?->tipo_horas ?? [];
                                    $tipoConsumos = Maquina::find($get('maquina_id'))?->tipo_consumo ?? [];

                                    return [
                                        Select::make('tipo_cantidad_producida')
                                            ->label('Tipo de cantidad')
                                            ->options([
                                                'camiones' => 'Camiones',
                                                'toneladas' => 'Toneladas',
                                                'metros_cubicos' => 'Metros cúbicos (m³)'
                                            ])
                                            ->searchable()
                                            ->reactive()
                                            ->required(),

                                        TextInput::make('cantidad_producida')
                                            ->numeric()
                                            ->label(fn(Get $get) => 'Cantidad producida (' . ($get('tipo_cantidad_producida') ?? 'camiones/tn') . ')')
                                            ->visible(fn(Get $get) => filled($get('tipo_cantidad_producida')))
                                            ->required(),

                                        // Campos de tipo horas
                                        ...collect($tipoHoras)->map(
                                            fn($hora) =>
                                            TimePicker::make($hora)
                                                ->label(ucfirst(str_replace('_', ' ', $hora)))
                                                ->withoutSeconds()
                                                ->required()
                                        )->toArray(),

                                        // Campos de tipo consumo
                                        ...collect($tipoConsumos)->map(
                                            fn($consumo) =>
                                            TextInput::make('consumo_' . $consumo)
                                                ->label(ucfirst(str_replace('_', ' ', $consumo)))
                                                ->numeric()
                                                ->required()
                                        )->toArray(),

                                        FileUpload::make('horometro')
                                            ->label('Foto easygreen o horómetro')
                                            ->disk('public')
                                            ->directory('horometros')
                                            ->required(),

                                        Textarea::make('observaciones')
                                            ->rows(4)
                                            ->maxLength(1000),

                                        TextInput::make('gps_fin_trabajo')
                                            ->label('GPS')
                                            ->required()
                                            ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                                        View::make('livewire.location-fin-trabajo'),
                                    ];
                                })

                                ->action(function (array $data, $record) {
                                    $record->update([
                                        'horas_encendido' => $data['horas_encendido'] ?? null,
                                        'horas_rotor' => $data['horas_rotor'] ?? null,
                                        'horas_trabajo' => $data['horas_trabajo'] ?? null,
                                        'cantidad_producida' => $data['cantidad_producida'] ?? null,
                                        'tipo_cantidad_producida' => $data['tipo_cantidad_producida'] ?? null,
                                        'horometro' => $data['horometro'] ?? null,
                                        'consumo_gasoil' => $data['consumo_gasoil'] ?? null,
                                        'consumo_cuchillas' => $data['consumo_cuchillas'] ?? null,
                                        'consumo_muelas' => $data['consumo_muelas'] ?? null,
                                        'fecha_hora_fin_trabajo' => now(),
                                        'observaciones' => $data['observaciones'] ?? null,
                                        'gps_fin_trabajo' => $data['gps_fin_trabajo'] ?? null,
                                    ]);

                                    Notification::make()
                                        ->success()
                                        ->title('Trabajo finalizado correctamente')
                                        ->send();

                                    return redirect(ParteTrabajoSuministroOperacionMaquinaResource::getUrl());
                                }),

                            Action::make('FinalizarYCerrarReferencia')
                                ->label('Finalizar trabajo y cerrar referencia')
                                ->color('danger')
                                ->icon('heroicon-o-lock-closed')
                                ->extraAttributes(['class' => 'w-full'])
                                ->visible(fn($record) => $record && $record->referencia && $record->referencia->estado !== 'cerrado')
                                ->button()
                                ->modalHeading('Finalizar trabajo y cerrar referencia')
                                ->modalSubmitActionLabel('Finalizar y cerrar referencia')
                                ->modalWidth('xl')
                                ->form(function (Get $get) {
                                    $tipoHoras = Maquina::find($get('maquina_id'))?->tipo_horas ?? [];
                                    $tipoConsumos = Maquina::find($get('maquina_id'))?->tipo_consumo ?? [];

                                    return [
                                        Select::make('tipo_cantidad_producida')
                                            ->label('Tipo de cantidad')
                                            ->options([
                                                'camiones' => 'Camiones',
                                                'toneladas' => 'Toneladas',
                                                'metros_cubicos' => 'Metros cúbicos (m³)'
                                            ])
                                            ->searchable()
                                            ->reactive()
                                            ->required(),

                                        TextInput::make('cantidad_producida')
                                            ->numeric()
                                            ->label(fn(Get $get) => 'Cantidad producida (' . ($get('tipo_cantidad_producida') ?? 'camiones/tn') . ')')
                                            ->visible(fn(Get $get) => filled($get('tipo_cantidad_producida')))
                                            ->required(),

                                        // Campos de tipo horas
                                        ...collect($tipoHoras)->map(
                                            fn($hora) =>
                                            TimePicker::make($hora)
                                                ->label(ucfirst(str_replace('_', ' ', $hora)))
                                                ->withoutSeconds()
                                                ->required()
                                        )->toArray(),

                                        // Campos de tipo consumo
                                        ...collect($tipoConsumos)->map(
                                            fn($consumo) =>
                                            TextInput::make('consumo_' . $consumo)
                                                ->label(ucfirst(str_replace('_', ' ', $consumo)))
                                                ->numeric()
                                                ->required()
                                        )->toArray(),

                                        FileUpload::make('horometro')
                                            ->label('Foto easygreen o horómetro')
                                            ->disk('public')
                                            ->directory('horometros')
                                            ->required(),

                                        Textarea::make('observaciones')
                                            ->rows(4)
                                            ->maxLength(1000),

                                        TextInput::make('gps_fin_trabajo')
                                            ->label('GPS')
                                            ->required()
                                            ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                                        View::make('livewire.location-fin-trabajo'),
                                    ];
                                })
                                ->action(function (array $data, $record) {
                                    // Finalizar trabajo igual que en la otra acción
                                    $record->update([
                                        'horas_encendido' => $data['horas_encendido'] ?? null,
                                        'horas_rotor' => $data['horas_rotor'] ?? null,
                                        'horas_trabajo' => $data['horas_trabajo'] ?? null,
                                        'cantidad_producida' => $data['cantidad_producida'] ?? null,
                                        'tipo_cantidad_producida' => $data['tipo_cantidad_producida'] ?? null,
                                        'horometro' => $data['horometro'] ?? null,
                                        'consumo_gasoil' => $data['consumo_gasoil'] ?? null,
                                        'consumo_cuchillas' => $data['consumo_cuchillas'] ?? null,
                                        'consumo_muelas' => $data['consumo_muelas'] ?? null,
                                        'fecha_hora_fin_trabajo' => now(),
                                        'observaciones' => $data['observaciones'] ?? null,
                                        'gps_fin_trabajo' => $data['gps_fin_trabajo'] ?? null,
                                    ]);

                                    // Cerrar la referencia
                                    if ($record->referencia && $record->referencia->estado === 'en_proceso') {
                                        $record->referencia->update(['estado' => 'cerrado']);
                                    }

                                    Notification::make()
                                        ->success()
                                        ->title('Trabajo finalizado y referencia cerrada')
                                        ->send();

                                    return redirect(ParteTrabajoSuministroOperacionMaquinaResource::getUrl());
                                }),

                        ])
                            ->columns(4)
                    ]),

                Section::make('Resumen de trabajo')
                    ->visible(fn($record) => $record && $record->fecha_hora_fin_trabajo !== null)
                    ->schema([
                        TimePicker::make('horas_encendido')
                            ->label('Horas encendido')
                            ->withoutSeconds()
                            ->visible(fn($record) => filled($record?->horas_encendido))
                            ->columnSpan(1),

                        TimePicker::make('horas_rotor')
                            ->label('Horas rotor')
                            ->withoutSeconds()
                            ->visible(fn($record) => filled($record?->horas_rotor))
                            ->columnSpan(1),

                        TimePicker::make('horas_trabajo')
                            ->label('Horas trabajo')
                            ->withoutSeconds()
                            ->visible(fn($record) => filled($record?->horas_trabajo))
                            ->columnSpan(1),

                        TextInput::make('consumo_gasoil')
                            ->label('Consumo de gasoil (L)')
                            ->numeric()
                            ->visible(fn($record) => filled($record?->consumo_gasoil))
                            ->columnSpan(1),

                        TextInput::make('consumo_cuchillas')
                            ->label('Cuchillas usadas (ud)')
                            ->numeric()
                            ->visible(fn($record) => filled($record?->consumo_cuchillas))
                            ->columnSpan(1),

                        TextInput::make('consumo_muelas')
                            ->label('Muelas usadas (ud)')
                            ->numeric()
                            ->visible(fn($record) => filled($record?->consumo_muelas))
                            ->columnSpan(1),

                        Select::make('tipo_cantidad_producida')
                            ->label('Tipo cantidad')
                            ->options([
                                'camiones' => 'Camiones',
                                'toneladas' => 'Toneladas',
                            ])
                            ->searchable()
                            ->visible(fn($record) => filled($record?->cantidad_producida))
                            ->columnSpan(1),

                        TextInput::make('cantidad_producida')
                            ->label(function ($get) {
                                $tipo = $get('tipo_cantidad_producida');
                                $unidad = match ($tipo) {
                                    'camiones' => 'camiones',
                                    'toneladas' => 'toneladas',
                                    default => '',
                                };

                                return 'Cantidad producida' . ($unidad ? " ({$unidad})" : '');
                            })
                            ->visible(fn($record) => filled($record?->cantidad_producida))
                            ->columnSpan(1),

                        FileUpload::make('horometro')
                            ->label('Foto easygreen o horómetro')
                            ->disk('public')
                            ->directory('horometros')
                            ->imageEditor()
                            ->openable()
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha_hora_inicio_trabajo')
                    ->label('Fecha')
                    ->date('d/m/Y') // Este sí se transforma con ->timezone()
                    ->timezone('Europe/Madrid') // Solo aplica a ->date()
                    ->weight(FontWeight::Bold)
                    ->description(function ($record) {
                        $inicio = $record->fecha_hora_inicio_trabajo
                            ? $record->fecha_hora_inicio_trabajo->copy()->setTimezone('Europe/Madrid')
                            : null;

                        $fin = $record->fecha_hora_fin_trabajo
                            ? Carbon::parse($record->fecha_hora_fin_trabajo)->setTimezone('Europe/Madrid')
                            : null;

                        if (!$inicio) {
                            return '-';
                        }

                        $inicioStr = $inicio->format('H:i');
                        $finStr = $fin ? $fin->format('H:i') : '-';

                        if ($fin && $inicio->format('d/m/Y') !== $fin->format('d/m/Y')) {
                            $finStr = $fin->format('d/m/Y') . ' ' . $fin->format('H:i');
                        }

                        return "Inicio: $inicioStr | Fin: $finStr";
                    })
                    ->sortable()
                    ->tooltip(function ($record) {
                        $inicio = $record->fecha_hora_inicio_trabajo
                            ? $record->fecha_hora_inicio_trabajo->copy()->setTimezone('Europe/Madrid')->format('d/m/Y H:i')
                            : '-';

                        $fin = $record->fecha_hora_fin_trabajo
                            ? Carbon::parse($record->fecha_hora_fin_trabajo)->setTimezone('Europe/Madrid')->format('d/m/Y H:i')
                            : '-';

                        return "Inicio: $inicio\nFin: $fin";
                    }),

                TextColumn::make('usuario_maquina_horas_produccion')
                    ->label('Usuario / Máquina / Horas / Producción')
                    ->html(),

                TextColumn::make('referencia_interviniente')
                    ->label('Referencia / Interviniente')
                    ->html(),
            ])
            ->persistFiltersInSession()
            ->filters(
                [
                    Filter::make('fecha_hora_inicio_trabajo')
                        ->columnSpanFull()
                        ->columns(2)
                        ->form([
                            DatePicker::make('created_from')
                                ->label('Desde'),

                            DatePicker::make('created_until')
                                ->label('Hasta'),
                        ])
                        ->query(function ($query, array $data) {
                            return $query
                                ->when($data['created_from'], fn($query, $date) => $query->whereDate('fecha_hora_inicio_trabajo', '>=', $date))
                                ->when($data['created_until'], fn($query, $date) => $query->whereDate('fecha_hora_inicio_trabajo', '<=', $date));
                        }),

                    SelectFilter::make('usuario_id')
                        ->label('Usuario')
                        ->options(function () {
                            $usuariosIds = ParteTrabajoSuministroOperacionMaquina::query()
                                ->distinct()
                                ->pluck('usuario_id')
                                ->filter()
                                ->unique();

                            return User::query()
                                ->whereIn('id', $usuariosIds)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn($usuario) => [
                                    $usuario->id => trim($usuario->name . ' ' . ($usuario->apellidos ?? '')),
                                ])
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->placeholder('Todos'),

                    SelectFilter::make('cliente_id')
                        ->label('Cliente')
                        ->relationship(
                            'referencia.cliente',
                            'razon_social',
                            fn($query) => $query->whereIn(
                                'id',
                                Referencia::query()
                                    ->whereIn(
                                        'id',
                                        ParteTrabajoSuministroOperacionMaquina::query()->distinct()->pluck('referencia_id')
                                    )
                                    ->pluck('cliente_id')
                                    ->filter()
                                    ->unique()
                            )
                        )
                        ->searchable()
                        ->preload()
                        ->placeholder('Todos'),

                    SelectFilter::make('tipo_referencia')
                        ->label('Tipo de referencia')
                        ->searchable()
                        ->options([
                            'suministro' => 'Suministro',
                            'servicio' => 'Servicio',
                        ])
                        ->query(function (Builder $query, array $data): Builder {
                            if ($data['value'] === 'suministro') {
                                return $query->whereHas('referencia', function (Builder $q) {
                                    $q->whereNotNull('formato');
                                });
                            }

                            if ($data['value'] === 'servicio') {
                                return $query->whereHas('referencia', function (Builder $q) {
                                    $q->whereNotNull('tipo_servicio');
                                });
                            }

                            return $query;
                        })
                        ->placeholder('Todas'),

                    SelectFilter::make('referencia_id')
                        ->label('Referencia')
                        ->options(function () {
                            $referenciaIds = ParteTrabajoSuministroOperacionMaquina::query()
                                ->distinct()
                                ->pluck('referencia_id')
                                ->filter()
                                ->unique();

                            return Referencia::query()
                                ->whereIn('id', $referenciaIds)
                                ->orderBy('referencia')
                                ->get()
                                ->mapWithKeys(fn($referencia) => [
                                    $referencia->id => trim(
                                        $referencia->referencia . ' (' .
                                        ($referencia->ayuntamiento ?? '-') . ', ' .
                                        ($referencia->monte_parcela ?? '-') . ')'
                                    ),
                                ])
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->placeholder('Todas'),

                    SelectFilter::make('maquina_id')
                        ->label('Máquina')
                        ->options(function () {
                            $maquinaIds = ParteTrabajoSuministroOperacionMaquina::query()
                                ->distinct()
                                ->pluck('maquina_id')
                                ->filter()
                                ->unique();

                            return Maquina::query()
                                ->whereIn('id', $maquinaIds)
                                ->orderBy('marca')
                                ->orderBy('modelo')
                                ->get()
                                ->mapWithKeys(fn($maquina) => [
                                    $maquina->id => trim(
                                        ($maquina->marca ?? '-') . ' ' . ($maquina->modelo ?? '-')
                                    ),
                                ])
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->placeholder('Todas'),

                    SelectFilter::make('tipo_trabajo')
                        ->label('Tipo de trabajo')
                        ->options(function () {
                            return ParteTrabajoSuministroOperacionMaquina::query()
                                ->distinct()
                                ->pluck('tipo_trabajo')
                                ->filter()
                                ->unique()
                                ->mapWithKeys(fn($tipo) => [$tipo => ucfirst($tipo)])
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->placeholder('Todos'),
                ],
                layout: FiltersLayout::AboveContent
            )
            ->filtersFormColumns(2)
            ->headerActions([
                Tables\Actions\Action::make('toggle_trashed')
                    ->label(fn() => request('trashed') === 'true' ? 'Ver activos' : 'Ver eliminados')
                    ->icon(fn() => request('trashed') === 'true' ? 'heroicon-o-eye' : 'heroicon-o-trash')
                    ->color(fn() => request('trashed') === 'true' ? 'gray' : 'danger')
                    ->visible(fn() => Filament::auth()->user()?->hasRole('superadmin'))
                    ->action(function () {
                        $verEliminados = request('trashed') !== 'true';

                        if ($verEliminados && ParteTrabajoSuministroOperacionMaquina::onlyTrashed()->count() === 0) {
                            Notification::make()
                                ->title('No hay registros eliminados')
                                ->body('Actualmente no existen registros en la papelera.')
                                ->warning()
                                ->send();

                            return;
                        }

                        // Redirige a la misma URL con o sin `trashed=true`
                        return redirect()->to(request()->fullUrlWithQuery([
                            'trashed' => $verEliminados ? 'true' : null,
                        ]));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->paginated(true)
            ->paginationPageOptions([50, 100, 200])
            ->defaultSort('fecha_hora_inicio_trabajo', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParteTrabajoSuministroOperacionMaquinas::route('/'),
            'create' => Pages\CreateParteTrabajoSuministroOperacionMaquina::route('/create'),
            'view' => Pages\ViewParteTrabajoSuministroOperacionMaquina::route('/{record}'),
            'edit' => Pages\EditParteTrabajoSuministroOperacionMaquina::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);

        if (request('trashed') === 'true') {
            $query->onlyTrashed();
        } else {
            $query->whereNull('deleted_at');
        }

        $user = Filament::auth()->user();
        $rolesPermitidos = ['superadmin', 'administración', 'administrador', 'técnico'];

        if (!$user->hasAnyRole($rolesPermitidos)) {
            return $query->where('usuario_id', $user->id);
        }

        // Técnicos solo ven partes de referencias de su sector
        if ($user->hasRole('técnico')) {
            $sectores = array_filter(Arr::wrap($user->sector ?? []));

            if (!empty($sectores)) {
                $query->whereHas('referencia', function (Builder $q) use ($sectores) {
                    $q->whereIn('sector', $sectores);
                });
            } else {
                // opcional: no mostrar nada si el técnico no tiene sectores asignados
                // $query->whereRaw('1=0');
            }
        }

        return $query;
    }

    private static function getUsuariosPermitidosQuery()
    {
        $user = Filament::auth()->user();

        return $user->hasRole('operarios')
            ? \App\Models\User::query()->where('id', $user->id)
            : \App\Models\User::query()->whereHas('roles', fn($q) =>
                $q->whereIn('name', ['administración', 'administrador', 'operarios', 'proveedor de servicio']));
    }
}
