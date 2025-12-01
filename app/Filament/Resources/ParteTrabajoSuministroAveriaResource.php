<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoSuministroAveriaResource\Pages;
use App\Filament\Resources\ParteTrabajoSuministroAveriaResource\RelationManagers;
use App\Models\Maquina;
use App\Models\ParteTrabajoSuministroAveria;
use App\Models\Taller;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Actions\Action as FormAction;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Arr;

class ParteTrabajoSuministroAveriaResource extends Resource
{
    protected static ?string $model = ParteTrabajoSuministroAveria::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Partes de trabajo';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'partes-trabajo-suministro-averia';
    public static ?string $label = 'avería / mantenimiento';
    public static ?string $pluralLabel = 'Averías / Mantenimientos';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos generales')
                    ->schema([
                        // ── USUARIO ────────────────────────────────────────────────────────────────────
                        Select::make('usuario_id')
                            ->relationship(
                                'usuario',
                                'name',
                                modifyQueryUsing: function ($query) {
                                    $user = Filament::auth()->user();

                                    if ($user->hasAnyRole(['superadmin', 'administrador', 'administración'])) {
                                        $query->whereDoesntHave('roles', fn($q) => $q->where('name', 'superadmin'));
                                    } else {
                                        $query->where('id', $user->id);
                                    }
                                }
                            )
                            ->getOptionLabelFromRecordUsing(fn($record) => trim($record->name . ' ' . ($record->apellidos ?? '')))
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->default(fn() => Filament::auth()->id())
                            ->live() // <- importante para que dispare afterStateUpdated
                            ->afterStateUpdated(function (Set $set, $state) {
                                // Reset dependientes
                                $set('maquina_id', null);
                                $set('trabajo_realizado', null);

                                if (!$state) {
                                    return;
                                }

                                // Auto-select si solo hay 1 máquina
                                $ids = \App\Models\Maquina::where('operario_id', $state)->pluck('id');
                                if ($ids->count() === 1) {
                                    $set('maquina_id', $ids->first());
                                }
                            })
                            ->required(),

                        // ── MÁQUINA ───────────────────────────────────────────────────────────────────
                        Select::make('maquina_id')
                            ->label('Máquina')
                            ->searchable()
                            ->options(function (Get $get) {
                                $usuarioId = $get('usuario_id');
                                $operariosIds = collect($get('operarios') ?? [])->filter()->values()->all();

                                // Si tienes campo múltiple 'operarios', priorízalo; si no, usa 'usuario_id'
                                $ids = !empty($operariosIds) ? $operariosIds : ($usuarioId ? [$usuarioId] : []);

                                if (empty($ids))
                                    return [];

                                return Maquina::query()
                                    ->whereIn('operario_id', $ids) // máquinas con campo directo
                                    ->orWhereHas('operarios', fn($q) => $q->whereIn('users.id', $ids)) // máquinas vinculadas en pivot
                                    ->orderBy('marca')
                                    ->orderBy('modelo')
                                    ->get()
                                    ->mapWithKeys(fn($m) => [$m->id => "{$m->marca} {$m->modelo}"])
                                    ->toArray();
                            })
                            ->default(function (Get $get) {
                                $usuarioId = $get('usuario_id');
                                $operariosIds = collect($get('operarios') ?? [])->filter()->values()->all();
                                $ids = !empty($operariosIds) ? $operariosIds : ($usuarioId ? [$usuarioId] : []);

                                if (empty($ids))
                                    return null;

                                $maquinas = Maquina::query()
                                    ->whereIn('operario_id', $ids)
                                    ->orWhereHas('operarios', fn($q) => $q->whereIn('users.id', $ids))
                                    ->pluck('id');

                                return $maquinas->count() === 1 ? $maquinas->first() : null;
                            })
                            ->afterStateHydrated(function ($component, $state, Get $get) {
                                if (blank($state)) {
                                    $usuarioId = $get('usuario_id');
                                    $operariosIds = collect($get('operarios') ?? [])->filter()->values()->all();
                                    $ids = !empty($operariosIds) ? $operariosIds : ($usuarioId ? [$usuarioId] : []);

                                    if (!empty($ids)) {
                                        $maquinas = Maquina::query()
                                            ->whereIn('operario_id', $ids)
                                            ->orWhereHas('operarios', fn($q) => $q->whereIn('users.id', $ids))
                                            ->pluck('id');

                                        if ($maquinas->count() === 1) {
                                            $component->state($maquinas->first());
                                        }
                                    }
                                }
                            })
                            ->live()
                            ->afterStateUpdated(fn(Set $set) => $set('trabajo_realizado', null))
                            ->required(),

                        // ── TIPO (avería / mantenimiento) ─────────────────────────────────────────────
                        Select::make('tipo')
                            ->label('Tipo')
                            ->searchable()
                            ->options([
                                'averia' => 'Avería',
                                'mantenimiento' => 'Mantenimiento',
                            ])
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('trabajo_realizado', null);
                            })
                            ->required(),

                        // ── TRABAJO REALIZADO (depende de máquina + tipo) ────────────────────────────
                        Select::make('trabajo_realizado')
                            ->label(fn(Get $get) => match ($get('tipo')) {
                                'averia' => 'Tipo de avería',
                                'mantenimiento' => 'Tipo de mantenimiento',
                                default => 'Tipo…',
                            })
                            ->options(function (Get $get) {
                                $maquinaId = $get('maquina_id');
                                $tipo = $get('tipo');

                                if (!$maquinaId || !$tipo) {
                                    return [];
                                }

                                $maquina = Maquina::find($maquinaId);
                                if (!$maquina) {
                                    return [];
                                }

                                if ($tipo === 'averia') {
                                    $ids = Arr::wrap($maquina->averias);
                                    return \App\Models\PosibleAveria::whereIn('id', $ids)->pluck('nombre', 'id')->toArray();
                                }

                                if ($tipo === 'mantenimiento') {
                                    $ids = Arr::wrap($maquina->mantenimientos);
                                    return \App\Models\PosibleMantenimiento::whereIn('id', $ids)->pluck('nombre', 'id')->toArray();
                                }

                                return [];
                            })
                            ->searchable()
                            ->disabled(fn(Get $get) => !$get('maquina_id') || !$get('tipo'))
                            ->required(),

                        Select::make('actuacion')
                            ->label('Medios utilizados')
                            ->required()
                            ->options([
                                'medios_propios' => 'Taller propio',
                                'taller_externo' => 'Taller externo'
                            ])
                            ->reactive()
                            ->searchable()
                            ->disabled(fn(callable $get) => !$get('maquina_id') || !$get('tipo')),

                        Select::make('taller_externo')
                            ->label('Taller externo')
                            ->required()
                            ->options(function () {
                                return Taller::all()->pluck('nombre', 'id');
                            })
                            ->reactive()
                            ->searchable()
                            ->columnSpanFull()
                            ->hidden(fn(callable $get) => $get('actuacion') !== 'taller_externo') // Oculta si no es 'taller_externo'
                            ->disabled(fn(callable $get) => !$get('maquina_id') || !$get('tipo')),
                    ])
                    ->columns(2),

                Section::make('Fechas y horas')
                    ->schema([
                        DateTimePicker::make('fecha_hora_inicio_averia')
                            ->label('Hora de inicio avería')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_inicio_averia) {
                                    return Actions\Action::make('ver_gps_inicio_averia')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_inicio_averia, shouldOpenInNewTab: true);
                                }
                                return null;
                            })
                            // Solo editan superadmin / administración, el resto lo ve en readonly
                            ->disabled(fn() => !Filament::auth()->user()?->hasAnyRole(['superadmin', 'administración'])),

                        DateTimePicker::make('fecha_hora_fin_averia')
                            ->label('Hora de finalización avería')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_fin_averia) {
                                    return Actions\Action::make('ver_gps_fin_averia')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_fin_averia, shouldOpenInNewTab: true);
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
                                    ($record->fecha_hora_parada_averia !== null)
                                    || ($record->fecha_hora_reanudacion_averia !== null);

                                if ($tieneLegacy) {
                                    $inicio = $record->fecha_hora_parada_averia
                                        ? $record->fecha_hora_parada_averia->copy()->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                        : '-';

                                    $fin = $record->fecha_hora_reanudacion_averia
                                        ? $record->fecha_hora_reanudacion_averia->copy()->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                        : '-';

                                    // Duración de la pausa legacy
                                    $duracionMin = 0;
                                    if ($record->fecha_hora_parada_averia && $record->fecha_hora_reanudacion_averia) {
                                        $duracionMin = $record->fecha_hora_parada_averia
                                            ->diffInMinutes($record->fecha_hora_reanudacion_averia);
                                    }
                                    $durH = intdiv($duracionMin, 60);
                                    $durM = $duracionMin % 60;
                                    $duracionStr = $duracionMin > 0 ? "{$durH}h {$durM}min" : '—';

                                    $gpsInicio = $record->gps_parada_trabajo
                                        ? '<a href="https://maps.google.com/?q=' . $record->gps_parada_trabajo . '" target="_blank" class="text-blue-600 underline">📍</a>'
                                        : '—';

                                    $gpsFin = $record->gps_reanudacion_trabajo
                                        ? '<a href="https://maps.google.com/?q=' . $record->gps_reanudacion_trabajo . '" target="_blank" class="text-blue-600 underline">📍</a>'
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
                                if (!$record || !$record->fecha_hora_inicio_averia) {
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
                        filled($record?->fecha_hora_inicio_averia)
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
                                    $record->fecha_hora_inicio_averia && !$record->fecha_hora_fin_averia
                                );
                            })->fullWidth()
                    ]),

                Section::make()
                    ->visible(function ($record) {
                        if (!$record)
                            return false;

                        return (
                            $record->fecha_hora_inicio_averia && !$record->fecha_hora_fin_averia
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
                                        !$record->fecha_hora_inicio_averia ||
                                        $record->fecha_hora_fin_averia
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
                                        !$record->fecha_hora_inicio_averia ||
                                        $record->fecha_hora_fin_averia
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
                                ->extraAttributes(['class' => 'w-full']) // Hace que el botón ocupe todo el ancho disponible
                                ->visible(
                                    fn($record) =>
                                    $record &&
                                    $record->fecha_hora_inicio_averia &&
                                    !$record->fecha_hora_fin_averia
                                )
                                ->button()
                                ->modalHeading('Finalizar trabajo')
                                ->modalSubmitActionLabel('Finalizar')
                                ->modalWidth('xl')
                                ->form([
                                    TextInput::make('gps_fin_averia')
                                        ->label('GPS')
                                        ->required()
                                        ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                                    View::make('livewire.location-fin-averia')->columnSpanFull(),
                                ])
                                ->action(function (array $data, $record) {
                                    // Cerrar cualquier pausa que haya quedado abierta
                                    $record->pausas()
                                        ->whereNull('fin_pausa')
                                        ->update(['fin_pausa' => now()]);

                                    $record->update([
                                        'fecha_hora_fin_averia' => now(),
                                        'gps_fin_averia' => $data['gps_fin_averia'],
                                    ]);

                                    Notification::make()
                                        ->success()
                                        ->title('Trabajo finalizado correctamente')
                                        ->send();

                                    return redirect(ParteTrabajoSuministroAveriaResource::getUrl());
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
                TextColumn::make('fecha_hora_inicio_averia')
                    ->label('Fecha y hora')
                    ->weight(FontWeight::Bold)
                    ->formatStateUsing(
                        fn($state) => $state
                        ? Carbon::parse($state)->timezone('Europe/Madrid')->format('d/m/Y H:i')
                        : '-'
                    ),

                TextColumn::make('usuario_y_maquina')
                    ->label('Usuario / Máquina')
                    ->html()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('detalles_trabajo')
                    ->label('Detalles')
                    ->html()
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
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
            'index' => Pages\ListParteTrabajoSuministroAverias::route('/'),
            'create' => Pages\CreateParteTrabajoSuministroAveria::route('/create'),
            'view' => Pages\ViewParteTrabajoSuministroAveria::route('/{record}'),
            'edit' => Pages\EditParteTrabajoSuministroAveria::route('/{record}/edit'),
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
