<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoSuministroOperacionMaquinaResource\Pages;
use App\Filament\Resources\ParteTrabajoSuministroOperacionMaquinaResource\RelationManagers;
use App\Models\Maquina;
use App\Models\ParteTrabajoSuministroOperacionMaquina;
use App\Models\Referencia;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
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
                        Filament::auth()->user()?->hasAnyRole(['superadmin', 'administración']) &&
                        filled($record?->fecha_hora_inicio_trabajo)
                    ),

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
                                ->extraAttributes(['class' => 'w-full']) // Hace que el botón ocupe todo el ancho disponible
                                ->visible(
                                    fn($record) =>
                                    $record &&
                                    $record->fecha_hora_inicio_trabajo &&
                                    !$record->fecha_hora_fin_trabajo
                                )
                                ->button()
                                ->modalHeading('Finalizar trabajo')
                                ->modalSubmitActionLabel('Finalizar')
                                ->modalWidth('xl')
                                ->form(function (Get $get) {
                                    $tipoHoras = \App\Models\Maquina::find($get('maquina_id'))?->tipo_horas ?? [];
                                    $tipoConsumos = \App\Models\Maquina::find($get('maquina_id'))?->tipo_consumo ?? [];

                                    return [
                                        Select::make('tipo_cantidad_producida')
                                            ->label('Tipo de cantidad')
                                            ->options([
                                                'camiones' => 'Camiones',
                                                'toneladas' => 'Toneladas',
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

                Section::make('Observaciones')
                    ->visible(fn($record) => filled($record?->usuario_id))
                    ->schema([
                        Textarea::make('observaciones')
                            ->rows(4)
                            ->maxLength(1000),
                    ]),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->timezone('Europe/Madrid')
                    ->weight(FontWeight::Bold)
                    ->description(function ($record) {
                        $inicio = $record->created_at
                            ? $record->created_at->timezone('Europe/Madrid')
                            : null;

                        $fin = $record->fecha_hora_fin_trabajo
                            ? Carbon::parse($record->fecha_hora_fin_trabajo)->timezone('Europe/Madrid')
                            : null;

                        if (!$inicio) {
                            return '-';
                        }

                        $inicioStr = $inicio->format('H:i');
                        $finStr = $fin ? $fin->format('H:i') : '-';

                        // Si el fin existe y cae en un día distinto al inicio, añade la fecha
                        if ($fin && $inicio->format('d/m/Y') !== $fin->format('d/m/Y')) {
                            $finStr = $fin->format('d/m/Y') . ' ' . $fin->format('H:i');
                        }

                        return "Inicio: $inicioStr | Fin: $finStr";
                    })
                    ->sortable()
                    ->tooltip(function ($record) {
                        // Tooltip con fecha y horas completas
                        $inicio = $record->created_at
                            ? $record->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i')
                            : '-';

                        $fin = $record->fecha_hora_fin_trabajo
                            ? Carbon::parse($record->fecha_hora_fin_trabajo)
                                ->timezone('Europe/Madrid')
                                ->format('d/m/Y H:i')
                            : '-';

                        return "Inicio: $inicio\nFin: $fin";
                    }),

                TextColumn::make('referencia.referencia')
                    ->label('Referencia')
                    ->formatStateUsing(function ($state, $record) {
                        $referencia = $record->referencia?->referencia ?? '';
                        $ayuntamiento = $record->referencia?->ayuntamiento ?? '';
                        $monte_parcela = $record->referencia?->monte_parcela ?? '';
                        return trim("$referencia ($ayuntamiento, $monte_parcela)");
                    })
                    ->limit(35)
                    ->tooltip(fn($record) => trim(($record->referencia?->referencia ?? '') . ' (' . ($record->referencia?->ayuntamiento ?? '') . ', ' . ($record->referencia?->monte_parcela ?? '') . ')'))
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                TextColumn::make('referencia.cliente.razon_social')
                    ->label('Cliente')
                    ->limit(25)
                    ->tooltip(fn($state) => $state)
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                TextColumn::make('usuario')
                    ->label('Usuario')
                    ->formatStateUsing(function ($state, $record) {
                        $nombre = $record->usuario?->name ?? '';
                        $apellido = $record->usuario?->apellidos ?? '';
                        $inicialApellido = $apellido ? strtoupper(substr($apellido, 0, 1)) . '.' : '';
                        return trim("$nombre $inicialApellido");
                    })
                    ->limit(20)
                    ->tooltip(fn($record) => ($record->usuario?->name ?? '') . ' ' . ($record->usuario?->apellidos ?? ''))
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                TextColumn::make('maquina')
                    ->label('Máquina')
                    ->formatStateUsing(function ($state, $record) {
                        $marca = $record->maquina?->marca ?? '';
                        $modelo = $record->maquina?->modelo ?? '';
                        return trim("$marca $modelo");
                    })
                    ->limit(25)
                    ->tooltip(fn($record) => ($record->maquina?->marca ?? '') . ' ' . ($record->maquina?->modelo ?? ''))
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('horas_rotor')
                    ->label('Horas rotor')
                    ->alignCenter(),

                TextColumn::make('produccion')
                    ->label('Producción')
                    ->alignCenter(),
            ])
            ->filters(
                [
                    Filter::make('created_at')
                        ->columns(2)
                        ->form([
                            DatePicker::make('created_from')
                                ->label('Desde'),

                            DatePicker::make('created_until')
                                ->label('Hasta'),
                        ])
                        ->query(function ($query, array $data) {
                            return $query
                                ->when($data['created_from'], fn($query, $date) => $query->whereDate('created_at', '>=', $date))
                                ->when($data['created_until'], fn($query, $date) => $query->whereDate('created_at', '<=', $date));
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
                        ->columnSpan(2)
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
                ],
                layout: FiltersLayout::AboveContent
            )
            ->filtersFormColumns(3)
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
            ->defaultSort('created_at', 'desc');
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
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $user = Filament::auth()->user();
        $rolesPermitidos = ['superadmin', 'administración', 'administrador'];

        if (!$user->hasAnyRole($rolesPermitidos)) {
            $query->where('usuario_id', $user->id);
        }

        return $query;
    }

    private static function getUsuariosPermitidosQuery()
    {
        $user = Filament::auth()->user();

        return $user->hasRole('operarios')
            ? \App\Models\User::query()->where('id', $user->id)
            : \App\Models\User::query()->whereHas('roles', fn($q) =>
                $q->whereIn('name', ['administración', 'administrador', 'operarios']));
    }
}
