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


                        Placeholder::make('')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_desplazamiento) {
                                    return new HtmlString('<p>Estado actual: <strong>Sin iniciar</strong></p>');
                                }

                                $inicio = Carbon::parse($record->getRawOriginal('fecha_hora_inicio_desplazamiento'))->timezone('Europe/Madrid');
                                $fin = $record->fecha_hora_fin_desplazamiento
                                    ? Carbon::parse($record->getRawOriginal('fecha_hora_fin_desplazamiento'))->timezone('Europe/Madrid')
                                    : null;

                                $estado = $fin ? 'Finalizado' : 'Trabajando';

                                $totalMinutos = $inicio->diffInMinutes($fin ?? Carbon::now('Europe/Madrid'));

                                $horas = floor($totalMinutos / 60);
                                $minutos = $totalMinutos % 60;

                                $emoji = match ($estado) {
                                    'Trabajando' => '🟢',
                                    'Finalizado' => '✅',
                                    default => '❓',
                                };

                                $gpsInicio = $record->gps_inicio_desplazamiento
                                    ? ' (<a href="https://maps.google.com/?q=' . $record->gps_inicio_desplazamiento . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)'
                                    : '';

                                $gpsFin = $record->gps_fin_desplazamiento
                                    ? ' (<a href="https://maps.google.com/?q=' . $record->gps_fin_desplazamiento . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)'
                                    : '';

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
                            ->visible(fn() => !Filament::auth()->user()?->hasAnyRole(['superadmin', 'administración']))
                            ->columnSpanFull(),
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
                            }),

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
                            }),

                        Placeholder::make('tiempo_total_desplazamiento')
                            ->label('Tiempo total desplazamiento')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_desplazamiento) {
                                    return 'Sin iniciar';
                                }

                                $inicio = Carbon::parse($record->fecha_hora_inicio_desplazamiento)->timezone('Europe/Madrid');
                                $fin = $record->fecha_hora_fin_desplazamiento
                                    ? Carbon::parse($record->fecha_hora_fin_desplazamiento)->timezone('Europe/Madrid')
                                    : Carbon::now('Europe/Madrid');

                                $totalMinutos = $inicio->diffInMinutes($fin);
                                $horas = floor($totalMinutos / 60);
                                $minutos = $totalMinutos % 60;

                                return "{$horas}h {$minutos}min";
                            }),
                    ])
                    ->columns(2)
                    ->visible(
                        fn($record) =>
                        Filament::auth()->user()?->hasAnyRole(['superadmin', 'administración']) &&
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
