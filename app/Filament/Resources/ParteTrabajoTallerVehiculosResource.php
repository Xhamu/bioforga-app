<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoTallerVehiculosResource\Pages;
use App\Models\ParteTrabajoTallerVehiculos;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Actions\Action as FormAction;
use Illuminate\Database\Eloquent\Model;

class ParteTrabajoTallerVehiculosResource extends Resource
{
    protected static ?string $model = ParteTrabajoTallerVehiculos::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Partes de trabajo';
    protected static ?int $navigationSort = 6;
    protected static ?string $slug = 'partes-trabajo-taller-vehiculos';
    public static ?string $label = 'taller (vehículo)';
    public static ?string $pluralLabel = 'Taller (Vehículos)';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos generales')
                    ->schema([
                        // Usuario actual (solo lectura)
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
                            ->required(),
                    ])
                    ->columns(1),

                Section::make('Datos del trabajo')
                    ->visible(fn($record) => $record && $record->fecha_hora_inicio_taller_vehiculos)
                    ->schema([
                        Placeholder::make('')
                            ->content(function ($record) {
                                if (
                                    !$record ||
                                    !$record->taller ||
                                    !$record->vehiculo_id ||
                                    !$record->kilometros
                                ) {
                                    return null;
                                }

                                $tallerNombre = $record->taller?->nombre ?? '-';
                                $vehiculo = $record->vehiculo;
                                $vehiculoLabel = $vehiculo ? "{$vehiculo->marca} {$vehiculo->modelo}" : '-';
                                $kilometros = $record->kilometros;
                                $tipoActuacion = $record->tipo_actuacion ?? '-';

                                // Convertir IDs a nombres (por si vienen array/string)
                                $trabajoRealizadoIds = is_array($record->trabajo_realizado) ? $record->trabajo_realizado : json_decode($record->trabajo_realizado ?? '[]', true);
                                $recambiosUtilizadosIds = is_array($record->recambios_utilizados) ? $record->recambios_utilizados : json_decode($record->recambios_utilizados ?? '[]', true);

                                $trabajoRealizadoNombres = \App\Models\TrabajoRealizado::whereIn('id', $trabajoRealizadoIds ?: [])->pluck('nombre')->toArray();
                                $recambiosNombres = \App\Models\RecambioUtilizado::whereIn('id', $recambiosUtilizadosIds ?: [])->pluck('nombre')->toArray();

                                $tabla = '
                                <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                                    <table class="w-full text-sm text-left text-gray-700 dark:text-gray-200">
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr class="bg-gray-50 dark:bg-gray-800">
                                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Taller</th>
                                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">' . e($tallerNombre) . '</td>
                                            </tr>
                                            <tr>
                                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Máquina</th>
                                                <td class="px-4 py-3">' . e($vehiculoLabel) . '</td>
                                            </tr>
                                            <tr>
                                                <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Kilometraje</th>
                                                <td class="px-4 py-3">' . e($kilometros) . 'km</td>
                                            </tr>';

                                if ($record->fecha_hora_fin_taller_vehiculos) {
                                    $tabla .= '
                                    <tr>
                                        <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Tipo de actuación</th>
                                        <td class="px-4 py-3">' . e(ucfirst($tipoActuacion)) . '</td>
                                    </tr>
                                    <tr>
                                        <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Trabajo realizado</th>
                                        <td class="px-4 py-3">' . e(implode(', ', $trabajoRealizadoNombres)) . '</td>
                                    </tr>
                                    <tr>
                                        <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Recambios utilizados</th>
                                        <td class="px-4 py-3">' . e(implode(', ', $recambiosNombres)) . '</td>
                                    </tr>';
                                }

                                $tabla .= '
                                        </tbody>
                                    </table>
                                </div>
                            ';

                                return new HtmlString($tabla);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Section::make('Fechas y horas')
                    ->schema([
                        DateTimePicker::make('fecha_hora_inicio_taller_vehiculos')
                            ->label('Hora de inicio taller vehiculos')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_inicio_taller_vehiculos) {
                                    return Actions\Action::make('ver_gps_inicio_taller_vehiculos')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_inicio_taller_vehiculos, shouldOpenInNewTab: true);
                                }
                                return null;
                            })
                            ->disabled(fn() => !Filament::auth()->user()?->hasAnyRole(['superadmin', 'administración'])),

                        DateTimePicker::make('fecha_hora_fin_taller_vehiculos')
                            ->label('Hora de finalización taller vehiculos')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_fin_taller_vehiculos) {
                                    return Actions\Action::make('ver_gps_fin_taller_vehiculos')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_fin_taller_vehiculos, shouldOpenInNewTab: true);
                                }
                                return null;
                            })
                            ->disabled(fn() => !Filament::auth()->user()?->hasAnyRole(['superadmin', 'administración'])),

                        Placeholder::make('pausas_detalle')
                            ->label('Pausas registradas')
                            ->content(function ($record) {
                                if (!$record) {
                                    return 'Sin pausas';
                                }

                                $rows = '';
                                $index = 1;

                                // 1) MODO LEGACY: usar los campos antiguos del propio parte
                                $tieneLegacy =
                                    ($record->fecha_hora_parada_taller_vehiculos !== null)
                                    || ($record->fecha_hora_reanudacion_taller_vehiculos !== null);

                                if ($tieneLegacy) {
                                    $inicio = $record->fecha_hora_parada_taller_vehiculos
                                        ? $record->fecha_hora_parada_taller_vehiculos->copy()->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                        : '-';

                                    $fin = $record->fecha_hora_reanudacion_taller_vehiculos
                                        ? $record->fecha_hora_reanudacion_taller_vehiculos->copy()->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                        : '-';

                                    // Duración de la pausa legacy
                                    $duracionMin = 0;
                                    if ($record->fecha_hora_parada_taller_vehiculos && $record->fecha_hora_reanudacion_taller_vehiculos) {
                                        $duracionMin = $record->fecha_hora_parada_taller_vehiculos
                                            ->diffInMinutes($record->fecha_hora_reanudacion_taller_vehiculos);
                                    }
                                    $durH = intdiv($duracionMin, 60);
                                    $durM = $duracionMin % 60;
                                    $duracionStr = $duracionMin > 0 ? "{$durH}h {$durM}min" : '—';

                                    $gpsInicio = $record->gps_parada_taller_vehiculos
                                        ? '<a href="https://maps.google.com/?q=' . $record->gps_parada_taller_vehiculos . '" target="_blank" class="text-blue-600 underline">📍</a>'
                                        : '—';

                                    $gpsFin = $record->gps_reanudacion_taller_vehiculos
                                        ? '<a href="https://maps.google.com/?q=' . $record->gps_reanudacion_taller_vehiculos . '" target="_blank" class="text-blue-600 underline">📍</a>'
                                        : '—';

                                    $rows .= '
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <td class="px-3 py-2 text-center">' . $index . '</td>
                                            <td class="px-3 py-2 text-sm">' . $inicio . '</td>
                                            <td class="px-3 py-2 text-sm">' . $fin . '</td>
                                            <td class="px-3 py-2 text-sm text-center">' . $duracionStr . '</td>
                                            <td class="px-3 py-2 text-sm text-center">' . $gpsInicio . '</td>
                                            <td class="px-3 py-2 text-sm text-center">' . $gpsFin . '</td>
                                        </tr>';
                                } else {
                                    // 2) NUEVO MODELO: usar la relación pausas()
                                    $pausas = $record->pausas()
                                        ->orderBy('inicio_pausa')
                                        ->get();

                                    if ($pausas->isEmpty()) {
                                        return 'Sin pausas registradas.';
                                    }

                                    foreach ($pausas as $pausa) {
                                        $inicio = $pausa->inicio_pausa
                                            ? $pausa->inicio_pausa->copy()->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                            : '-';

                                        $fin = $pausa->fin_pausa
                                            ? $pausa->fin_pausa->copy()->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                            : '-';

                                        // Duración de la pausa
                                        $duracionMin = 0;
                                        if ($pausa->inicio_pausa && $pausa->fin_pausa) {
                                            $duracionMin = $pausa->inicio_pausa->diffInMinutes($pausa->fin_pausa);
                                        }
                                        $durH = intdiv($duracionMin, 60);
                                        $durM = $duracionMin % 60;
                                        $duracionStr = $duracionMin > 0 ? "{$durH}h {$durM}min" : '—';

                                        $gpsInicio = $pausa->gps_inicio_pausa
                                            ? '<a href="https://maps.google.com/?q=' . $pausa->gps_inicio_pausa . '" target="_blank" class="text-blue-600 underline">📍</a>'
                                            : '—';

                                        $gpsFin = $pausa->gps_fin_pausa
                                            ? '<a href="https://maps.google.com/?q=' . $pausa->gps_fin_pausa . '" target="_blank" class="text-blue-600 underline">📍</a>'
                                            : '—';

                                        $rows .= '
                                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                                <td class="px-3 py-2 text-center">' . $index++ . '</td>
                                                <td class="px-3 py-2 text-sm">' . $inicio . '</td>
                                                <td class="px-3 py-2 text-sm">' . $fin . '</td>
                                                <td class="px-3 py-2 text-sm text-center">' . $duracionStr . '</td>
                                                <td class="px-3 py-2 text-sm text-center">' . $gpsInicio . '</td>
                                                <td class="px-3 py-2 text-sm text-center">' . $gpsFin . '</td>
                                            </tr>';
                                    }
                                }

                                $html = '
                                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm mt-2">
                                        <table class="w-full text-sm text-left text-gray-700 dark:text-gray-200">
                                            <thead class="bg-gray-50 dark:bg-gray-800">
                                                <tr>
                                                    <th class="px-3 py-2 text-center w-12">#</th>
                                                    <th class="px-3 py-2">Inicio pausa</th>
                                                    <th class="px-3 py-2">Fin pausa</th>
                                                    <th class="px-3 py-2 text-center">Duración</th>
                                                    <th class="px-3 py-2 text-center">GPS inicio</th>
                                                    <th class="px-3 py-2 text-center">GPS fin</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                ' . $rows . '
                                            </tbody>
                                        </table>
                                    </div>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),

                        Placeholder::make('tiempo_total')
                            ->label('Tiempo total')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_taller_vehiculos) {
                                    return 'Sin iniciar';
                                }

                                $minutos = $record->minutos_trabajados;
                                $horas = intdiv($minutos, 60);
                                $resto = $minutos % 60;

                                return "{$horas}h {$resto}min";
                            })
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->visible(
                        fn($record) =>
                        filled($record?->fecha_hora_inicio_taller_vehiculos)
                    ),

                // 🔼 NUEVA sección con los campos solicitados (sin tocar lo demás)
                Section::make('Detalles adicionales')
                    ->schema([
                        Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'reparado' => 'Reparado',
                                'sin_reparar' => 'Sin reparar',
                                'en_proceso' => 'En proceso',
                            ])
                            ->columnSpanFull()
                            ->required(),

                        FileUpload::make('fotos')
                            ->label('Fotos')
                            ->image()
                            ->columnSpanFull()
                            ->multiple()
                            ->maxFiles(4)
                            ->directory('taller_vehiculos/fotos'),
                    ])
                    ->visible(fn($record) => $record && $record->fecha_hora_fin_taller_vehiculos)
                    ->columns(2),

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
                                    $record->fecha_hora_inicio_taller_vehiculos && !$record->fecha_hora_fin_taller_vehiculos
                                );
                            })->fullWidth()
                    ]),

                Section::make()
                    ->visible(function ($record) {
                        if (!$record)
                            return false;

                        return (
                            $record->fecha_hora_inicio_taller_vehiculos && !$record->fecha_hora_fin_taller_vehiculos
                        );
                    })
                    ->schema([
                        Actions::make([
                            Action::make('Parar')
                                ->label('Parar trabajo')
                                ->color('warning')
                                ->button()
                                ->extraAttributes(['id' => 'btn-parar-trabajo', 'class' => 'w-full'])
                                ->visible(function ($record) {
                                    if (
                                        !$record ||
                                        !$record->fecha_hora_inicio_taller_vehiculos ||
                                        $record->fecha_hora_fin_taller_vehiculos
                                    ) {
                                        return false;
                                    }

                                    // No mostrar si ya hay una pausa abierta
                                    $hayPausaAbierta = $record->pausas()
                                        ->whereNull('fin_pausa')
                                        ->exists();

                                    if ($hayPausaAbierta) {
                                        return false;
                                    }

                                    $u = auth()->user();
                                    if (!$u) {
                                        return false;
                                    }

                                    $allowed = $u->hasAnyRole(['operarios', 'superadmin', 'administración', 'proveedor de servicio']);
                                    $exclude = $u->hasAllRoles(['operarios', 'técnico']);

                                    return $allowed && !$exclude;
                                })
                                ->requiresConfirmation()
                                ->form([
                                    TextInput::make('gps_inicio_pausa')
                                        ->label('GPS inicio pausa')
                                        ->required()
                                        ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                                    // Componente que rellena el campo con la ubicación del navegador
                                    View::make('livewire.location-inicio-pausa')
                                        ->columnSpanFull(),
                                ])
                                ->action(function (array $data, $record) {
                                    // Creamos una nueva pausa
                                    $record->pausas()->create([
                                        'inicio_pausa' => now(),
                                        'gps_inicio_pausa' => $data['gps_inicio_pausa'] ?? null,
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
                                ->visible(function ($record) {
                                    if (
                                        !$record ||
                                        !$record->fecha_hora_inicio_taller_vehiculos ||
                                        $record->fecha_hora_fin_taller_vehiculos
                                    ) {
                                        return false;
                                    }

                                    // Solo mostrar si hay una pausa abierta
                                    $hayPausaAbierta = $record->pausas()
                                        ->whereNull('fin_pausa')
                                        ->exists();

                                    if (!$hayPausaAbierta) {
                                        return false;
                                    }

                                    $u = auth()->user();
                                    if (!$u) {
                                        return false;
                                    }

                                    $allowed = $u->hasAnyRole(['operarios', 'superadmin', 'administración', 'proveedor de servicio']);
                                    $exclude = $u->hasAllRoles(['operarios', 'técnico']);

                                    return $allowed && !$exclude;
                                })
                                ->button()
                                ->requiresConfirmation()
                                ->form([
                                    TextInput::make('gps_fin_pausa')
                                        ->label('GPS fin pausa')
                                        ->required()
                                        ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                                    // Componente que rellena el campo con la ubicación del navegador
                                    View::make('livewire.location-fin-pausa')
                                        ->columnSpanFull(),
                                ])
                                ->action(function (array $data, $record) {
                                    $pausa = $record->pausas()
                                        ->whereNull('fin_pausa')
                                        ->latest('inicio_pausa')
                                        ->first();

                                    if (!$pausa) {
                                        Notification::make()
                                            ->danger()
                                            ->title('No hay ninguna pausa activa')
                                            ->send();

                                        return;
                                    }

                                    $pausa->update([
                                        'fin_pausa' => now(),
                                        'gps_fin_pausa' => $data['gps_fin_pausa'] ?? null,
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
                                ->visible(
                                    fn($record) =>
                                    $record &&
                                    $record->fecha_hora_inicio_taller_vehiculos &&
                                    !$record->fecha_hora_fin_taller_vehiculos
                                )
                                ->button()
                                ->modalHeading('Finalizar trabajo')
                                ->modalSubmitActionLabel('Finalizar')
                                ->modalWidth('xl')
                                ->form([
                                    Select::make('tipo_actuacion')
                                        ->label('Tipo de actuación')
                                        ->searchable()
                                        ->options([
                                            'reparacion' => 'Reparación',
                                            'mantenimiento' => 'Mantenimiento',
                                        ])
                                        ->required(),

                                    Select::make('trabajo_realizado')
                                        ->label('Trabajo realizado')
                                        ->multiple()
                                        ->searchable()
                                        ->options(\App\Models\TrabajoRealizado::pluck('nombre', 'id'))
                                        ->required(),

                                    // 👇 Añadimos 'ninguno' a recambios sin tocar el resto
                                    Select::make('recambios_utilizados')
                                        ->label('Recambios utilizados')
                                        ->multiple()
                                        ->searchable()
                                        ->options(['ninguno' => 'Ninguno'] + \App\Models\RecambioUtilizado::pluck('nombre', 'id')->toArray())
                                        ->required(),

                                    Select::make('estado')
                                        ->label('Estado')
                                        ->options([
                                            'reparado' => 'Reparado',
                                            'sin_reparar' => 'Sin reparar',
                                            'en_proceso' => 'En proceso',
                                        ])
                                        ->default('en_proceso')
                                        ->required(),

                                    FileUpload::make('fotos')
                                        ->label('Fotos')
                                        ->image()
                                        ->multiple()
                                        ->maxFiles(4)
                                        ->directory('taller-maquinaria')
                                        ->imagePreviewHeight('200')
                                        ->reorderable()
                                        ->panelLayout('grid')
                                        ->columnSpanFull(),
                                ])
                                ->action(function (array $data, $record) {
                                    // Cerrar cualquier pausa que haya quedado abierta
                                    $record->pausas()
                                        ->whereNull('fin_pausa')
                                        ->update(['fin_pausa' => now()]);

                                    $record->update([
                                        'fecha_hora_fin_taller_vehiculos' => now(),
                                        'tipo_actuacion' => $data['tipo_actuacion'],
                                        'trabajo_realizado' => $data['trabajo_realizado'],
                                        'recambios_utilizados' => $data['recambios_utilizados'],
                                        'estado' => $data['estado'] ?? 'en_proceso',
                                        'fotos' => $data['fotos'] ?? [],
                                    ]);

                                    Notification::make()
                                        ->success()
                                        ->title('Trabajo finalizado correctamente')
                                        ->send();

                                    return redirect(ParteTrabajoTallerVehiculosResource::getUrl());
                                }),
                        ])
                            ->columns(4)
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha_hora_inicio_taller_vehiculos')
                    ->label('Fecha y hora')
                    ->weight(FontWeight::Bold)
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Europe/Madrid')
                    ->sortable(),

                TextColumn::make('usuario')
                    ->label('Usuario')
                    ->formatStateUsing(function ($state, $record) {
                        $nombre = $record->usuario?->name ?? '';
                        $apellido = $record->usuario?->apellidos ?? '';
                        $inicialApellido = $apellido ? strtoupper(substr($apellido, 0, 1)) . '.' : '';
                        return trim("$nombre $inicialApellido");
                    })
                    ->weight(FontWeight::Bold)
                    ->tooltip(fn($record) => $record->usuario?->name . ' ' . $record->usuario?->apellidos)
                    ->searchable(['name', 'apellidos'])
                    ->sortable(),

                TextColumn::make('vehiculo.marca')
                    ->label('Vehículo')
                    ->formatStateUsing(function ($state, $record) {
                        $marca = $record->vehiculo?->marca ?? '';
                        $modelo = $record->vehiculo?->modelo ?? '';
                        $matricula = strtoupper($record->vehiculo?->matricula ?? '');
                        return "{$marca} {$modelo} ({$matricula})";
                    })
                    ->tooltip(fn($record) => "Marca: {$record->vehiculo?->marca} | Modelo: {$record->vehiculo?->modelo} | Matrícula: {$record->vehiculo?->matricula}")
                    ->searchable(['marca', 'modelo', 'matricula'])
                    ->sortable(),

                TextColumn::make('taller.nombre')
                    ->label('Taller')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('fecha')
                    ->label('Rango de fechas')
                    ->columns(2)
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['desde'] ?? null, fn($q, $date) => $q->whereDate('fecha_hora_inicio_taller_vehiculos', '>=', $date))
                            ->when($data['hasta'] ?? null, fn($q, $date) => $q->whereDate('fecha_hora_inicio_taller_vehiculos', '<=', $date));
                    })
                    ->columnSpanFull(),

                Tables\Filters\SelectFilter::make('vehiculo_id')
                    ->label('Vehículo')
                    ->relationship('vehiculo', 'marca')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->marca} {$record->modelo} ({$record->matricula})")
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('usuario_id')
                    ->label('Usuario')
                    ->relationship('usuario', 'name')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->name} {$record->apellidos}")
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('taller_id')
                    ->label('Taller')
                    ->relationship('taller', 'nombre')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TrashedFilter::make()
                    ->visible(fn() => Filament::auth()->user()?->hasRole('superadmin'))
                    ->columnSpanFull(),
            ], layout: FiltersLayout::AboveContentCollapsible)
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
            ->paginated(true)
            ->paginationPageOptions([50, 100, 200])
            ->defaultSort('fecha_hora_inicio_taller_vehiculos', 'desc');
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
            'index' => Pages\ListParteTrabajoTallerVehiculos::route('/'),
            'create' => Pages\CreateParteTrabajoTallerVehiculos::route('/create'),
            'view' => Pages\ViewParteTrabajoTallerVehiculos::route('/{record}'),
            'edit' => Pages\EditParteTrabajoTallerVehiculos::route('/{record}/edit'),
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
}
