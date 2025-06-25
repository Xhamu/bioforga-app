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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

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
                            ->required()
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

                        Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->rows(3)
                            ->visible(fn($record) => filled($record?->fecha_hora_inicio_ayudante))
                            ->columnSpanFull(),
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

                TextColumn::make('nombre_maquina_vehiculo')
                    ->label('Medio utilizado')
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
        $rolesPermitidos = ['superadmin', 'administración', 'administrador'];

        if (!$user->hasAnyRole($rolesPermitidos)) {
            $query->where('usuario_id', $user->id);
        }

        return $query;
    }
}
