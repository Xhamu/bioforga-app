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
use Filament\Forms\Components\DateTimePicker;
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
                            ->columnSpanFull()
                            ->default(Filament::auth()->user()->id)
                            ->reactive()
                            ->required(),

                        // Máquina (todas)
                        Select::make('maquina_id')
                            ->label('Máquina')
                            ->reactive()
                            ->options(function (callable $get) {
                                $usuarioId = $get('usuario_id');

                                if (!$usuarioId) {
                                    return [];
                                }

                                return \App\Models\Maquina::where('operario_id', $usuarioId)
                                    ->get()
                                    ->mapWithKeys(function ($maquina) {
                                        return [$maquina->id => "{$maquina->marca} {$maquina->modelo}"];
                                    })
                                    ->toArray();
                            })
                            ->afterStateHydrated(function (callable $get, callable $set) {
                                // Al cargar el formulario por primera vez
                                $usuarioId = $get('usuario_id');
                                if ($usuarioId) {
                                    $maquinas = \App\Models\Maquina::where('operario_id', $usuarioId)->get();
                                    $set('maquina_id', $maquinas->count() === 1 ? $maquinas->first()->id : null);
                                }
                            })
                            ->searchable()
                            ->required()
                            ->live(), // Para que reaccione a los cambios en vivo

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

                        // Trabajo realizado (se rellena según máquina y tipo)
                        Select::make('trabajo_realizado')
                            ->label(function (callable $get) {
                                return match ($get('tipo')) {
                                    'averia' => 'Tipo de avería',
                                    'mantenimiento' => 'Tipo de mantenimiento',
                                    default => 'Tipo de ...',
                                };
                            })
                            ->reactive()
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

                Section::make('')
                    ->schema([
                        Placeholder::make('')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_averia) {
                                    return new HtmlString('<p>Estado actual: <strong>Sin iniciar</strong></p>');
                                }

                                $inicio = Carbon::parse($record->getRawOriginal('fecha_hora_inicio_averia'))->timezone('Europe/Madrid');
                                $fin = $record->fecha_hora_fin_averia
                                    ? Carbon::parse($record->getRawOriginal('fecha_hora_fin_averia'))->timezone('Europe/Madrid')
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
                            ->visible(fn() => Filament::auth()->user()?->hasAnyRole(['superadmin', 'administración']))
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

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
                            }),

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
                            }),

                        Placeholder::make('tiempo_total_averia')
                            ->label('Tiempo total avería')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_averia) {
                                    return 'Sin iniciar';
                                }

                                $inicio = Carbon::parse($record->fecha_hora_inicio_averia)->timezone('Europe/Madrid');
                                $fin = $record->fecha_hora_fin_averia
                                    ? Carbon::parse($record->fecha_hora_fin_averia)->timezone('Europe/Madrid')
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
                        filled($record?->fecha_hora_inicio_averia)
                    ),

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
                                        ->required()
                                        ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

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
