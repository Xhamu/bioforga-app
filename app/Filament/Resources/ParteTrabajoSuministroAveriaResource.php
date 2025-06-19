<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoSuministroAveriaResource\Pages;
use App\Filament\Resources\ParteTrabajoSuministroAveriaResource\RelationManagers;
use App\Models\ParteTrabajoSuministroAveria;
use App\Models\Taller;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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

                        // Tipo: Avería o Mantenimiento
                        Select::make('tipo')
                            ->label('Tipo')
                            ->reactive()
                            ->searchable()
                            ->options([
                                'averia' => 'Avería',
                                'mantenimiento' => 'Mantenimiento',
                            ])
                            ->required(),

                        // Máquina (todas)
                        Select::make('maquina_id')
                            ->label('Máquina')
                            ->reactive()
                            ->options(function () {
                                return \App\Models\Maquina::all()->mapWithKeys(function ($maquina) {
                                    return [$maquina->id => "{$maquina->marca} {$maquina->modelo}"];
                                })->toArray();
                            })
                            ->searchable()
                            ->required(),

                        // Trabajo realizado (se rellena según máquina y tipo)
                        Select::make('trabajo_realizado')
                            ->label('Trabajo realizado')
                            ->required()
                            ->options(function (callable $get) {
                                $maquinaId = $get('maquina_id');
                                $tipo = $get('tipo');

                                if (!$maquinaId || !$tipo) {
                                    return [];
                                }

                                $maquina = \App\Models\Maquina::find($maquinaId);
                                if (!$maquina) {
                                    return [];
                                }

                                $ids = [];

                                if ($tipo === 'averia') {
                                    $ids = is_array($maquina->averias) ? $maquina->averias : [];
                                    return \App\Models\PosibleAveria::whereIn('id', $ids)->pluck('nombre', 'id')->toArray();
                                }

                                if ($tipo === 'mantenimiento') {
                                    $ids = is_array($maquina->mantenimientos) ? $maquina->mantenimientos : [];
                                    return \App\Models\PosibleMantenimiento::whereIn('id', $ids)->pluck('nombre', 'id')->toArray();
                                }

                                return [];
                            })
                            ->reactive()
                            ->searchable()
                            ->disabled(fn(callable $get) => !$get('maquina_id') || !$get('tipo')),
                        Select::make('actuacion')
                            ->label('Actuación')
                            ->required()
                            ->options([
                                'medios_propios' => 'Por medios propios',
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

                Section::make('')
                    ->schema([
                        Placeholder::make('')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_averia) {
                                    return new HtmlString('<p>Estado actual: <strong>Sin iniciar</strong></p>');
                                }

                                $estado = $record->fecha_hora_fin_averia ? 'Finalizado' : 'Trabajando';
                                $totalMinutos = Carbon::parse($record->getRawOriginal('fecha_hora_inicio_averia'))
                                    ->diffInMinutes(
                                        $record->fecha_hora_fin_averia
                                        ? Carbon::parse($record->getRawOriginal('fecha_hora_fin_averia'))
                                        : now()
                                    );

                                $horas = floor($totalMinutos / 60);
                                $minutos = $totalMinutos % 60;

                                $emoji = match ($estado) {
                                    'Trabajando' => '🟢',
                                    'Finalizado' => '✅',
                                    default => '❓',
                                };

                                $inicio = Carbon::parse($record->getRawOriginal('fecha_hora_inicio_averia'));
                                $fin = $record->fecha_hora_fin_averia ? Carbon::parse($record->getRawOriginal('fecha_hora_fin_averia')) : null;

                                $gpsInicio = $record->gps_inicio_averia
                                    ? ' (<a href="https://maps.google.com/?q=' . $record->gps_inicio_averia . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)'
                                    : '';

                                $gpsFin = $record->gps_fin_averia
                                    ? ' (<a href="https://maps.google.com/?q=' . $record->gps_fin_averia . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)'
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
                            $record->fecha_hora_inicio_averia && !$record->fecha_hora_fin_averia
                        );
                    })
                    ->schema([
                        Actions::make([
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
                                        ->required(),

                                    View::make('livewire.location-fin-averia')->columnSpanFull(),
                                ])
                                ->action(function (array $data, $record) {
                                    $record->update([
                                        'fecha_hora_fin_averia' => now(),
                                        'gps_fin_averia' => $data['gps_fin_averia'],
                                    ]);

                                    Notification::make()
                                        ->success()
                                        ->title('Trabajo finalizado correctamente')
                                        ->send();
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

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'averia' => 'Avería',
                            'mantenimiento' => 'Mantenimiento',
                            default => ucfirst($state),
                        };
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
