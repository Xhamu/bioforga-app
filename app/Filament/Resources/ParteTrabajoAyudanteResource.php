<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoAyudanteResource\Pages;
use App\Filament\Resources\ParteTrabajoAyudanteResource\RelationManagers;
use App\Models\ParteTrabajoAyudante;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Facades\Filament;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
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
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Actions\Action as FormAction;
use Illuminate\Database\Eloquent\Model;

class ParteTrabajoAyudanteResource extends Resource
{
    protected static ?string $model = ParteTrabajoAyudante::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Partes de trabajo';
    protected static ?int $navigationSort = 7;
    protected static ?string $slug = 'partes-trabajo-ayudantes';
    public static ?string $label = 'ayudante';
    public static ?string $pluralLabel = 'Ayudantes';
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

                                    if ($user->hasAnyRole(['operarios', 'proveedor de servicio'])) {
                                        // Mostrar solo al usuario autenticado
                                        $query->where('id', $user->id);
                                    } else {
                                        // Mostrar todos los usuarios que no sean superadmin
                                        $query->whereDoesntHave('roles', function ($q) {
                                            $q->where('name', 'superadmin');
                                        });
                                    }
                                }
                            )
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->name . ' ' . $record->apellidos)
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->default(Filament::auth()->user()->id)
                            ->required(),
                    ])
                    ->columns(3),

                Section::make('Fecha y horas')
                    ->schema([
                        DateTimePicker::make('fecha_hora_inicio_ayudante')
                            ->label('Hora de inicio')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_inicio_ayudante) {
                                    return Actions\Action::make('ver_gps_inicio_ayudante')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_inicio_ayudante, shouldOpenInNewTab: true);
                                }
                                return null;
                            })
                            ->disabled(fn() => !Filament::auth()->user()?->hasAnyRole(['superadmin', 'administración'])),

                        DateTimePicker::make('fecha_hora_fin_ayudante')
                            ->label('Hora de fin')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_fin_ayudante) {
                                    return Actions\Action::make('ver_gps_fin_ayudante')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_fin_ayudante, shouldOpenInNewTab: true);
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
                                    ($record->fecha_hora_parada_ayudante !== null)
                                    || ($record->fecha_hora_reanudacion_ayudante !== null);

                                if ($tieneLegacy) {
                                    $inicio = $record->fecha_hora_parada_ayudante
                                        ? $record->fecha_hora_parada_ayudante->copy()->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                        : '-';

                                    $fin = $record->fecha_hora_reanudacion_ayudante
                                        ? $record->fecha_hora_reanudacion_ayudante->copy()->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                        : '-';

                                    // Duración de la pausa legacy
                                    $duracionMin = 0;
                                    if ($record->fecha_hora_parada_ayudante && $record->fecha_hora_reanudacion_ayudante) {
                                        $duracionMin = $record->fecha_hora_parada_ayudante
                                            ->diffInMinutes($record->fecha_hora_reanudacion_ayudante);
                                    }
                                    $durH = intdiv($duracionMin, 60);
                                    $durM = $duracionMin % 60;
                                    $duracionStr = $duracionMin > 0 ? "{$durH}h {$durM}min" : '—';

                                    $gpsInicio = $record->gps_parada_ayudante
                                        ? '<a href="https://maps.google.com/?q=' . $record->gps_parada_ayudante . '" target="_blank" class="text-blue-600 underline">📍</a>'
                                        : '—';

                                    $gpsFin = $record->gps_reanudacion_ayudante
                                        ? '<a href="https://maps.google.com/?q=' . $record->gps_reanudacion_ayudante . '" target="_blank" class="text-blue-600 underline">📍</a>'
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
                                if (!$record || !$record->fecha_hora_inicio_ayudante) {
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
                        filled($record?->fecha_hora_inicio_ayudante)
                    ),

                Section::make('')
                    ->schema([
                        Select::make('maquina_id')
                            ->label('Máquina')
                            ->options(function () {
                                return \App\Models\Maquina::all()->pluck('modelo', 'id')->mapWithKeys(function ($modelo, $id) {
                                    $maquina = \App\Models\Maquina::find($id);
                                    return [$id => "{$maquina->marca} {$maquina->modelo}"];
                                });
                            })
                            ->visible(fn($record) => filled($record?->fecha_hora_inicio_ayudante))
                            ->hidden(fn($get) => filled($get('vehiculo_id')))
                            ->searchable()
                            ->preload(),

                        Select::make('vehiculo_id')
                            ->label('Vehículo')
                            ->options(function () {
                                return \App\Models\Vehiculo::all()->mapWithKeys(function ($vehiculo) {
                                    return [$vehiculo->id => "{$vehiculo->marca} {$vehiculo->modelo}"];
                                });
                            })
                            ->searchable()
                            ->preload()
                            ->visible(fn($record) => filled($record?->fecha_hora_inicio_ayudante))
                            ->hidden(fn($get) => filled($get('maquina_id')))
                            ->columnSpanFull(),

                        Select::make('tipologia')
                            ->label('Tipología')
                            ->relationship('tipologia', 'nombre')
                            ->searchable()
                            ->preload()
                            ->visible(fn($record) => filled($record?->fecha_hora_inicio_ayudante))
                            ->columnSpanFull(),

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
                                            $record->fecha_hora_inicio_ayudante && !$record->fecha_hora_fin_ayudante
                                        );
                                    })->fullWidth()
                            ]),
                    ])
                    ->columns(1),

                Section::make()
                    ->visible(function ($record) {
                        if (!$record)
                            return false;

                        return (
                            $record->fecha_hora_inicio_ayudante && !$record->fecha_hora_fin_ayudante
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
                                        !$record->fecha_hora_inicio_ayudante ||
                                        $record->fecha_hora_fin_ayudante
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
                                        !$record->fecha_hora_inicio_ayudante ||
                                        $record->fecha_hora_fin_ayudante
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
                                    $record->fecha_hora_inicio_ayudante &&
                                    !$record->fecha_hora_fin_ayudante
                                )
                                ->button()
                                ->modalHeading('Finalizar trabajo')
                                ->modalSubmitActionLabel('Finalizar')
                                ->modalWidth('xl')
                                ->form([
                                    TextInput::make('gps_fin_ayudante')
                                        ->label('GPS')
                                        ->required()
                                        ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                                    View::make('livewire.location-fin-ayudante')->columnSpanFull(),
                                ])
                                ->action(function (array $data, $record) {
                                    // Cerrar cualquier pausa que haya quedado abierta
                                    $record->pausas()
                                        ->whereNull('fin_pausa')
                                        ->update(['fin_pausa' => now()]);

                                    $record->update([
                                        'fecha_hora_fin_ayudante' => now(),
                                        'gps_fin_ayudante' => $data['gps_fin_ayudante'],
                                    ]);

                                    Notification::make()
                                        ->success()
                                        ->title('Trabajo finalizado correctamente')
                                        ->send();

                                    return redirect(\App\Filament\Resources\ParteTrabajoAyudanteResource::getUrl());
                                }),
                        ])->columns(4)
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha_hora_inicio_ayudante')
                    ->label('Fecha y hora')
                    ->weight(FontWeight::Bold)
                    ->formatStateUsing(fn($state) => Carbon::parse($state)->timezone('Europe/Madrid')->format('d/m/Y H:i')),

                TextColumn::make('usuario_con_medio')
                    ->label('Usuario / Medio utilizado')
                    ->html()
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('usuario', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('apellidos', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('tipologia')
                    ->label('Tipología'),

                TextColumn::make('tiempo_total')
                    ->label('Tiempo total')
                    ->getStateUsing(function ($record) {
                        if (!$record->fecha_hora_inicio_ayudante) {
                            return '-';
                        }

                        $inicio = Carbon::parse($record->getRawOriginal('fecha_hora_inicio_ayudante'))->timezone('Europe/Madrid');
                        $parada = $record->fecha_hora_parada_ayudante
                            ? Carbon::parse($record->getRawOriginal('fecha_hora_parada_ayudante'))->timezone('Europe/Madrid')
                            : null;
                        $reanudacion = $record->fecha_hora_reanudacion_ayudante
                            ? Carbon::parse($record->getRawOriginal('fecha_hora_reanudacion_ayudante'))->timezone('Europe/Madrid')
                            : null;
                        $fin = $record->fecha_hora_fin_ayudante
                            ? Carbon::parse($record->getRawOriginal('fecha_hora_fin_ayudante'))->timezone('Europe/Madrid')
                            : null;

                        $totalMinutos = 0;

                        if ($fin) {
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
                    })
                    ->sortable(),
            ])
            ->filters(
                [
                    // Filtro por rango de fechas
                    Tables\Filters\Filter::make('fecha_rango')
                        ->form([
                            Forms\Components\DatePicker::make('desde')
                                ->label('Desde'),
                            Forms\Components\DatePicker::make('hasta')
                                ->label('Hasta'),
                        ])
                        ->query(function (Builder $query, array $data) {
                            return $query
                                ->when($data['desde'], fn($q) => $q->whereDate('fecha_hora_inicio_ayudante', '>=', $data['desde']))
                                ->when($data['hasta'], fn($q) => $q->whereDate('fecha_hora_inicio_ayudante', '<=', $data['hasta']));
                        })
                        ->columns(2),

                    // Filtro por usuario
                    Tables\Filters\SelectFilter::make('usuario_id')
                        ->label('Usuario')
                        ->options(
                            \App\Models\User::selectRaw("id, CONCAT(name, ' ', apellidos) as nombre_completo")
                                ->whereDoesntHave('roles', fn($q) => $q->where('name', 'superadmin'))
                                ->orderBy('name')
                                ->pluck('nombre_completo', 'id')
                        )
                        ->preload()
                        ->searchable(),

                    // Filtro por tipología
                    Tables\Filters\SelectFilter::make('tipologia')
                        ->label('Tipología')
                        ->options(
                            \App\Models\Tipologia::orderBy('nombre')
                                ->pluck('nombre', 'id')
                        )
                        ->preload()
                        ->searchable(),

                    Tables\Filters\TrashedFilter::make()
                        ->columnSpanFull()
                        ->visible(fn() => auth()->user()?->hasRole('superadmin')),
                ],
                layout: FiltersLayout::AboveContentCollapsible
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
            ->paginated(true)
            ->paginationPageOptions([50, 100, 200])
            ->defaultSort('fecha_hora_inicio_ayudante', 'desc');
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
            'index' => Pages\ListParteTrabajoAyudantes::route('/'),
            'create' => Pages\CreateParteTrabajoAyudante::route('/create'),
            'view' => Pages\ViewParteTrabajoAyudante::route('/{record}'),
            'edit' => Pages\EditParteTrabajoAyudante::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);

        $user = Filament::auth()->user();
        $rolesPermitidos = ['superadmin', 'administración', 'administrador', 'técnico'];

        if (!$user->hasAnyRole($rolesPermitidos)) {
            $query->where('usuario_id', $user->id);
        }

        return $query;
    }
}
