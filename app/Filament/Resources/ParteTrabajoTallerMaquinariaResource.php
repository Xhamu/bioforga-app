<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoTallerMaquinariaResource\Pages;
use App\Filament\Resources\ParteTrabajoTallerMaquinariaResource\RelationManagers;
use App\Models\ParteTrabajoTallerMaquinaria;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
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

class ParteTrabajoTallerMaquinariaResource extends Resource
{
    protected static ?string $model = ParteTrabajoTallerMaquinaria::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Partes de trabajo';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'partes-trabajo-taller-maquinaria';
    public static ?string $label = 'taller (maquinaria)';
    public static ?string $pluralLabel = 'Taller (Maquinaria)';
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
                    ->visible(fn($record) => $record && $record->fecha_hora_inicio_taller_maquinaria)
                    ->schema([
                        Placeholder::make('')
                            ->content(function ($record) {
                                if (
                                    !$record ||
                                    !$record->taller ||
                                    !$record->maquina_id ||
                                    !$record->horas_servicio
                                ) {
                                    return null;
                                }

                                $tallerNombre = $record->taller?->nombre ?? '-';
                                $maquina = $record->maquina;
                                $maquinaLabel = $maquina ? "{$maquina->marca} {$maquina->modelo}" : '-';
                                $horas = $record->horas_servicio;
                                $tipoActuacion = $record->tipo_actuacion ?? '-';

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
                                                        <td class="px-4 py-3">' . e($maquinaLabel) . '</td>
                                                    </tr>
                                                    <tr>
                                                        <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Kilómetros</th>
                                                        <td class="px-4 py-3">' . e(number_format($horas, 0, ',', '.')) . 'km</td>
                                                    </tr>';

                                if ($record->fecha_hora_fin_taller_maquinaria) {
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

                Section::make('')
                    ->schema([
                        Placeholder::make('')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_taller_maquinaria) {
                                    return new HtmlString('<p>Estado actual: <strong>Sin iniciar</strong></p>');
                                }

                                $inicio = Carbon::parse($record->getRawOriginal('fecha_hora_inicio_taller_maquinaria'))->timezone('Europe/Madrid');
                                $fin = $record->fecha_hora_fin_taller_maquinaria
                                    ? Carbon::parse($record->getRawOriginal('fecha_hora_fin_taller_maquinaria'))->timezone('Europe/Madrid')
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

                                $gpsInicio = $record->gps_inicio_taller_maquinaria
                                    ? ' (<a href="https://maps.google.com/?q=' . $record->gps_inicio_taller_maquinaria . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)'
                                    : '';

                                $gpsFin = $record->gps_fin_taller_maquinaria
                                    ? ' (<a href="https://maps.google.com/?q=' . $record->gps_fin_taller_maquinaria . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)'
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
                    ->columns(1),

                Section::make()
                    ->visible(function ($record) {
                        if (!$record)
                            return false;

                        return (
                            $record->fecha_hora_inicio_taller_maquinaria && !$record->fecha_hora_fin_taller_maquinaria
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
                                    $record->fecha_hora_inicio_taller_maquinaria &&
                                    !$record->fecha_hora_fin_taller_maquinaria
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
                                        'fecha_hora_fin_taller_maquinaria' => now(),
                                        'tipo_actuacion' => $data['tipo_actuacion'],
                                        'trabajo_realizado' => $data['trabajo_realizado'],
                                        'recambios_utilizados' => $data['recambios_utilizados'],
                                    ]);

                                    Notification::make()
                                        ->success()
                                        ->title('Trabajo finalizado correctamente')
                                        ->send();

                                    return redirect(ParteTrabajoTallerMaquinariaResource::getUrl());
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
                TextColumn::make('created_at')
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

                TextColumn::make('maquina.marca')
                    ->label('Máquina')
                    ->searchable()
                    ->formatStateUsing(function ($state, $record) {
                        $marca = $record->maquina?->marca ?? '';
                        $modelo = $record->maquina?->modelo ?? '';
                        $tipo = ucfirst($record->maquina?->tipo_trabajo ?? '');

                        return "{$marca} {$modelo} - {$tipo}";
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
            'index' => Pages\ListParteTrabajoTallerMaquinarias::route('/'),
            'create' => Pages\CreateParteTrabajoTallerMaquinaria::route('/create'),
            'view' => Pages\ViewParteTrabajoTallerMaquinaria::route('/{record}'),
            'edit' => Pages\EditParteTrabajoTallerMaquinaria::route('/{record}/edit'),
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
