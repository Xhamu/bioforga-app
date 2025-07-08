<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoTallerVehiculosResource\Pages;
use App\Filament\Resources\ParteTrabajoTallerVehiculosResource\RelationManagers;
use App\Models\ParteTrabajoTallerVehiculos;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

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
                                $trabajoRealizado = $record->trabajo_realizado ?? '-';
                                $recambiosUtilizados = $record->recambios_utilizados ?? '-';

                                // ⚠️ Convertir IDs a nombres
                                $trabajoRealizadoIds = $record->trabajo_realizado ?? [];
                                $recambiosUtilizadosIds = $record->recambios_utilizados ?? [];

                                // Por si vienen en string JSON
                                $trabajoRealizadoIds = is_array($trabajoRealizadoIds) ? $trabajoRealizadoIds : json_decode($trabajoRealizadoIds, true);
                                $recambiosUtilizadosIds = is_array($recambiosUtilizadosIds) ? $recambiosUtilizadosIds : json_decode($recambiosUtilizadosIds, true);

                                $trabajoRealizadoNombres = \App\Models\TrabajoRealizado::whereIn('id', $trabajoRealizadoIds)->pluck('nombre')->toArray();
                                $recambiosNombres = \App\Models\RecambioUtilizado::whereIn('id', $recambiosUtilizadosIds)->pluck('nombre')->toArray();

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
                            }),

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
                            }),

                        Placeholder::make('tiempo_total_taller_vehiculos')
                            ->label('Tiempo total taller vehiculos')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_taller_vehiculos) {
                                    return 'Sin iniciar';
                                }

                                $inicio = Carbon::parse($record->fecha_hora_inicio_taller_vehiculos)->timezone('Europe/Madrid');
                                $fin = $record->fecha_hora_fin_taller_vehiculos
                                    ? Carbon::parse($record->fecha_hora_fin_taller_vehiculos)->timezone('Europe/Madrid')
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
                        filled($record?->fecha_hora_inicio_taller_vehiculos)
                    ),

                Section::make('')
                    ->schema([
                        Placeholder::make('')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_taller_vehiculos) {
                                    return new HtmlString('<p>Estado actual: <strong>Sin iniciar</strong></p>');
                                }

                                $inicio = Carbon::parse($record->getRawOriginal('fecha_hora_inicio_taller_vehiculos'))->timezone('Europe/Madrid');
                                $fin = $record->fecha_hora_fin_taller_vehiculos
                                    ? Carbon::parse($record->getRawOriginal('fecha_hora_fin_taller_vehiculos'))->timezone('Europe/Madrid')
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

                                $gpsInicio = $record->gps_inicio_taller_vehiculos
                                    ? ' (<a href="https://maps.google.com/?q=' . $record->gps_inicio_taller_vehiculos . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)'
                                    : '';

                                $gpsFin = $record->gps_fin_taller_vehiculos
                                    ? ' (<a href="https://maps.google.com/?q=' . $record->gps_fin_taller_vehiculos . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)'
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
                            ->columnSpanFull(),
                    ])
                    ->visible(fn() => !Filament::auth()->user()?->hasAnyRole(['superadmin', 'administración']))
                    ->columns(1),

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

                                    Select::make('recambios_utilizados')
                                        ->label('Recambios utilizados')
                                        ->multiple()
                                        ->searchable()
                                        ->options(\App\Models\RecambioUtilizado::pluck('nombre', 'id'))
                                        ->required(),
                                ])
                                ->action(function (array $data, $record) {
                                    $record->update([
                                        'fecha_hora_fin_taller_vehiculos' => now(),
                                        'tipo_actuacion' => $data['tipo_actuacion'],
                                        'trabajo_realizado' => $data['trabajo_realizado'],
                                        'recambios_utilizados' => $data['recambios_utilizados'],
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

                TextColumn::make('vehiculo.marca')
                    ->label('Vehículo')
                    ->searchable()
                    ->formatStateUsing(function ($state, $record) {
                        $marca = $record->vehiculo?->marca ?? '';
                        $modelo = $record->vehiculo?->modelo ?? '';
                        $matricula = ucfirst($record->vehiculo?->matricula ?? '');

                        return "{$marca} {$modelo} ({$matricula})";
                    }),

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
