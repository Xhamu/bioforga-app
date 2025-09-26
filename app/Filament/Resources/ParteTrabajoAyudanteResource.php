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
                    ->visible(
                        fn($record) =>
                        $record
                        && filled($record->fecha_hora_fin_ayudante)
                        || auth()->user()->hasAnyRole(['superadmin', 'administración'])
                    )
                    ->schema([
                        DateTimePicker::make('fecha_hora_inicio_ayudante')
                            ->label('Hora de inicio')
                            ->seconds(false)
                            ->timezone('Europe/Madrid')
                            ->closeOnDateSelection()
                            ->live(),

                        DateTimePicker::make('fecha_hora_parada_ayudante')
                            ->label('Hora de parada')
                            ->seconds(false)
                            ->timezone('Europe/Madrid')
                            ->closeOnDateSelection()
                            ->live(),

                        DateTimePicker::make('fecha_hora_reanudacion_ayudante')
                            ->label('Hora de reanudación')
                            ->seconds(false)
                            ->timezone('Europe/Madrid')
                            ->closeOnDateSelection()
                            ->live(),

                        DateTimePicker::make('fecha_hora_fin_ayudante')
                            ->label('Hora de finalización')
                            ->seconds(false)
                            ->timezone('Europe/Madrid')
                            ->closeOnDateSelection()
                            ->live()
                            ->rule('after:fecha_hora_inicio_ayudante'),
                    ])
                    ->columns(2),

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

                        Placeholder::make('')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_ayudante) {
                                    return new HtmlString('<p>Estado actual: <strong>Sin iniciar</strong></p>');
                                }

                                $estado = 'Desconocido';
                                $totalMinutos = 0;

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

                                $gpsInicio = $record->gps_inicio_ayudante ? ' (<a href="https://maps.google.com/?q=' . $record->gps_inicio_ayudante . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)' : '';
                                $gpsPausa = $record->gps_parada_ayudante ? ' (<a href="https://maps.google.com/?q=' . $record->gps_parada_ayudante . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)' : '';
                                $gpsReanudar = $record->gps_reanudacion_ayudante ? ' (<a href="https://maps.google.com/?q=' . $record->gps_reanudacion_ayudante . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)' : '';
                                $gpsFin = $record->gps_fin_ayudante ? ' (<a href="https://maps.google.com/?q=' . $record->gps_fin_ayudante . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)' : '';

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
                            $record->fecha_hora_inicio_ayudante && !$record->fecha_hora_parada_ayudante && !$record->fecha_hora_fin_ayudante ||

                            $record->fecha_hora_parada_ayudante && !$record->fecha_hora_reanudacion_ayudante && !$record->fecha_hora_fin_ayudante ||

                            $record->fecha_hora_inicio_ayudante && !$record->fecha_hora_fin_ayudante
                        );
                    })
                    ->schema([
                        Actions::make([
                            Action::make('Parar')
                                ->label('Parar trabajo')
                                ->color('warning')
                                ->button()
                                ->extraAttributes(['id' => 'btn-parar-ayudante', 'class' => 'w-full'])
                                ->visible(
                                    fn($record) =>
                                    $record &&
                                    $record->fecha_hora_inicio_ayudante &&
                                    !$record->fecha_hora_parada_ayudante &&
                                    !$record->fecha_hora_fin_ayudante
                                )
                                ->requiresConfirmation()
                                ->form([
                                    Hidden::make('gps_parada_ayudante'),
                                ])
                                ->action(function (array $data, $record) {
                                    $record->update([
                                        'fecha_hora_parada_ayudante' => now(),
                                        'gps_parada_ayudante' => $data['gps_parada_ayudante'],
                                    ]);

                                    Notification::make()
                                        ->info()
                                        ->title('Trabajo pausado')
                                        ->send();
                                }),

                            Action::make('Reanudar')
                                ->label('Reanudar trabajo')
                                ->color('info')
                                ->extraAttributes(['id' => 'btn-reanudar-ayudante', 'class' => 'w-full'])
                                ->visible(
                                    fn($record) =>
                                    $record &&
                                    $record->fecha_hora_parada_ayudante &&
                                    !$record->fecha_hora_reanudacion_ayudante &&
                                    !$record->fecha_hora_fin_ayudante
                                )
                                ->button()
                                ->requiresConfirmation()
                                ->form([
                                    Hidden::make('gps_reanudacion_ayudante'),
                                ])
                                ->action(function (array $data, $record) {
                                    $record->update([
                                        'fecha_hora_reanudacion_ayudante' => now(),
                                        'gps_reanudacion_ayudante' => $data['gps_reanudacion_ayudante'],
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
