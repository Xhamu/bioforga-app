<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoSuministroDesplazamientoResource\Pages;
use App\Filament\Resources\ParteTrabajoSuministroDesplazamientoResource\RelationManagers;
use App\Models\Maquina;
use App\Models\ParteTrabajoSuministroDesplazamiento;
use App\Models\Referencia;
use App\Models\Taller;
use App\Models\User;
use App\Models\Vehiculo;
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

class ParteTrabajoSuministroDesplazamientoResource extends Resource
{
    protected static ?string $model = ParteTrabajoSuministroDesplazamiento::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Partes de trabajo';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'partes-trabajo-suministro-desplazamiento';
    public static ?string $label = 'desplazamiento';
    public static ?string $pluralLabel = 'Desplazamientos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos generales')
                    ->schema([
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
                            ->default(function (callable $get) {
                                $usuarioId = $get('usuario_id');
                                if (!$usuarioId)
                                    return null;

                                $vehiculos = Vehiculo::whereJsonContains('conductor_habitual', (string) $usuarioId)->get();
                                return $vehiculos->count() === 1 ? $vehiculos->first()->id : null;
                            })
                            ->reactive()
                            ->visible(fn(callable $get) => $get('maquina_id') === null),

                        Select::make('maquina_id')
                            ->label('Máquina')
                            ->relationship(
                                name: 'maquina',
                                titleAttribute: 'marca',
                                modifyQueryUsing: fn($query, callable $get) => $query->when(
                                    $get('usuario_id'),
                                    fn($q, $usuarioId) => $q->where('operario_id', (string) $usuarioId)
                                )
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn($record) => $record->marca . ' ' . $record->modelo
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->default(function (callable $get) {
                                $usuarioId = $get('usuario_id');
                                if (!$usuarioId)
                                    return null;

                                $maquinas = Maquina::where('operario_id', (string) $usuarioId)->get();
                                return $maquinas->count() === 1 ? $maquinas->first()->id : null;
                            })
                            ->reactive()
                            ->visible(fn(callable $get) => $get('vehiculo_id') === null),
                    ])
                    ->visible(
                        fn($record) =>
                        $record &&
                        $record->fecha_hora_inicio_desplazamiento
                    )
                    ->columns(2),

                Section::make('')
                    ->schema([
                        Select::make('referencia_id')
                            ->label('Destino | Referencia')
                            ->searchable()
                            ->options(function (callable $get) {
                                $usuarioId = $get('usuario_id');

                                if (!$usuarioId) {
                                    return [];
                                }

                                $usuario = \App\Models\User::find($usuarioId);

                                return $usuario?->referencias()
                                    ->select('referencias.id', 'referencias.referencia', 'referencias.ayuntamiento', 'referencias.monte_parcela')
                                    ->get()
                                    ->mapWithKeys(function ($ref) {
                                        $label = "{$ref->referencia} | ({$ref->ayuntamiento}, {$ref->monte_parcela})";
                                        return [$ref->id => $label];
                                    }) ?? [];
                            })
                            ->visible(
                                fn(callable $get, $record) =>
                                $record &&
                                $record->fecha_hora_inicio_desplazamiento &&
                                $get('taller_id') === null
                            )
                            ->preload()
                            ->reactive(),

                        Select::make('taller_id')
                            ->label('Destino | Taller')
                            ->searchable()
                            ->options(fn() => \App\Models\Taller::pluck('nombre', 'id'))
                            ->visible(
                                fn(callable $get, $record) =>
                                $record &&
                                $record->fecha_hora_inicio_desplazamiento &&
                                $get('referencia_id') === null
                            )
                            ->preload()
                            ->reactive(),
                    ])
                    ->columns(1),

                Section::make('Fechas y horas')
                    ->schema([
                        DateTimePicker::make('fecha_hora_inicio_desplazamiento')
                            ->label('Hora de inicio desplazamiento')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_inicio_desplazamiento) {
                                    return Actions\Action::make('ver_gps_inicio_desplazamiento')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_inicio_desplazamiento, shouldOpenInNewTab: true);
                                }
                                return null;
                            })
                            ->disabled(fn() => !Filament::auth()->user()?->hasAnyRole(['superadmin', 'administración'])),

                        DateTimePicker::make('fecha_hora_fin_desplazamiento')
                            ->label('Hora de finalización desplazamiento')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_fin_desplazamiento) {
                                    return Actions\Action::make('ver_gps_fin_desplazamiento')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_fin_desplazamiento, shouldOpenInNewTab: true);
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
                                    ($record->fecha_hora_parada_desplazamiento !== null)
                                    || ($record->fecha_hora_reanudacion_desplazamiento !== null);

                                if ($tieneLegacy) {
                                    $inicio = $record->fecha_hora_parada_desplazamiento
                                        ? $record->fecha_hora_parada_desplazamiento->copy()->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                        : '-';

                                    $fin = $record->fecha_hora_reanudacion_desplazamiento
                                        ? $record->fecha_hora_reanudacion_desplazamiento->copy()->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                        : '-';

                                    // Duración de la pausa legacy
                                    $duracionMin = 0;
                                    if ($record->fecha_hora_parada_desplazamiento && $record->fecha_hora_reanudacion_desplazamiento) {
                                        $duracionMin = $record->fecha_hora_parada_desplazamiento
                                            ->diffInMinutes($record->fecha_hora_reanudacion_desplazamiento);
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
                                if (!$record || !$record->fecha_hora_inicio_desplazamiento) {
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
                        filled($record?->fecha_hora_inicio_desplazamiento)
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
                                    $record->fecha_hora_inicio_desplazamiento && !$record->fecha_hora_fin_desplazamiento
                                );
                            })->fullWidth()
                    ]),

                Section::make()
                    ->visible(function ($record) {
                        if (!$record)
                            return false;

                        return (
                            $record->fecha_hora_inicio_desplazamiento && !$record->fecha_hora_fin_desplazamiento
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
                                        !$record->fecha_hora_inicio_desplazamiento ||
                                        $record->fecha_hora_fin_desplazamiento
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
                                        !$record->fecha_hora_inicio_desplazamiento ||
                                        $record->fecha_hora_fin_desplazamiento
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
                                ->label('Finalizar desplazamiento')
                                ->color('danger')
                                ->extraAttributes(['class' => 'w-full']) // Hace que el botón ocupe todo el ancho disponible
                                ->visible(
                                    fn($record) =>
                                    $record &&
                                    $record->fecha_hora_inicio_desplazamiento &&
                                    !$record->fecha_hora_fin_desplazamiento
                                )
                                ->button()
                                ->modalHeading('Finalizar desplazamiento')
                                ->modalSubmitActionLabel('Finalizar')
                                ->modalWidth('xl')
                                ->form([
                                    TextInput::make('gps_fin_desplazamiento')
                                        ->label('GPS')
                                        ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin']))
                                        ->required(),

                                    View::make('livewire.location-fin-desplazamiento')->columnSpanFull(),
                                ])
                                ->action(function (array $data, $record) {
                                    // Cerrar cualquier pausa que haya quedado abierta
                                    $record->pausas()
                                        ->whereNull('fin_pausa')
                                        ->update(['fin_pausa' => now()]);

                                    $record->update([
                                        'fecha_hora_fin_desplazamiento' => now(),
                                        'gps_fin_desplazamiento' => $data['gps_fin_desplazamiento'],
                                    ]);

                                    Notification::make()
                                        ->success()
                                        ->title('Trabajo finalizado correctamente')
                                        ->send();

                                    return redirect(ParteTrabajoSuministroDesplazamientoResource::getUrl());
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
                TextColumn::make('fecha_hora_inicio_desplazamiento')
                    ->label('Fecha y hora')
                    ->weight(FontWeight::Bold)
                    ->dateTime()
                    ->timezone('Europe/Madrid'),

                TextColumn::make('usuario')
                    ->label('Usuario')
                    ->formatStateUsing(function ($state, $record) {
                        $nombre = $record->usuario?->name ?? '';
                        $apellido = $record->usuario?->apellidos ?? '';
                        $inicialApellido = $apellido ? strtoupper(substr($apellido, 0, 1)) . '.' : '';
                        return trim("$nombre $inicialApellido");
                    })
                    ->weight(FontWeight::Bold)
                    ->searchable(),

                TextColumn::make('destino')
                    ->label('Destino')
                    ->formatStateUsing(function ($record) {
                        if ($record->referencia_id) {
                            $referencia = Referencia::find($record->referencia_id);
                            if ($referencia) {
                                return "{$referencia->referencia} ({$referencia->ayuntamiento}, {$referencia->monte_parcela})";
                            }
                        }

                        if ($record->taller_id) {
                            $taller = Taller::find($record->taller_id);
                            if ($taller) {
                                return "Taller: {$taller->nombre}";
                            }
                        }

                        return '—';
                    })
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
            'index' => Pages\ListParteTrabajoSuministroDesplazamientos::route('/'),
            'create' => Pages\CreateParteTrabajoSuministroDesplazamiento::route('/create'),
            'view' => Pages\ViewParteTrabajoSuministroDesplazamiento::route('/{record}'),
            'edit' => Pages\EditParteTrabajoSuministroDesplazamiento::route('/{record}/edit'),
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
