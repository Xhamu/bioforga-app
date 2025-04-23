<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoSuministroOperacionMaquinaResource\Pages;
use App\Filament\Resources\ParteTrabajoSuministroOperacionMaquinaResource\RelationManagers;
use App\Models\ParteTrabajoSuministroOperacionMaquina;
use Carbon\Carbon;
use Filament\Forms\Components\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
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

class ParteTrabajoSuministroOperacionMaquinaResource extends Resource
{
    protected static ?string $model = ParteTrabajoSuministroOperacionMaquina::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Partes de trabajo';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'partes-trabajo-suministro-operacion-maquina';
    public static ?string $label = 'operación máquina';
    public static ?string $pluralLabel = 'Operaciones máquina';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos generales')
                    ->schema([
                        Select::make('usuario_id')
                            ->relationship('usuario', 'name')
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->name . ' ' . $record->apellidos)
                            ->searchable()
                            ->preload()
                            ->default(Filament::auth()->user()->id)
                            ->required(),

                        Select::make('maquina_id')
                            ->label('Máquina')
                            ->options(function () {
                                $usuario = Auth::user();

                                $maquinas = \App\Models\Maquina::where('operario_id', $usuario->id)->get();

                                if ($maquinas->isEmpty()) {
                                    return ['' => '- No hay máquinas asignadas -'];
                                }

                                return $maquinas->mapWithKeys(fn($maquina) => [
                                    $maquina->id => "{$maquina->marca} {$maquina->modelo}"
                                ])->toArray();
                            })
                            ->default(function () {
                                $usuario = Auth::user();
                                $maquinas = \App\Models\Maquina::where('operario_id', $usuario->id)->pluck('id');

                                return $maquinas->count() === 1 ? $maquinas->first() : null;
                            })
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $maquina = \App\Models\Maquina::find($state);
                                if ($maquina) {
                                    $set('tipo_trabajo', $maquina->tipo_trabajo);
                                }
                            })
                            ->searchable()
                            ->required(),

                        Select::make('tipo_trabajo')
                            ->label('Tipo de trabajo')
                            ->options([
                                'astillado' => 'Astillado',
                                'triturado' => 'Triturado',
                                'pretiturado' => 'Pretiturado',
                                'saca' => 'Saca',
                                'tala' => 'Tala',
                                'cizallado' => 'Cizallado',
                                'carga' => 'Carga',
                                'transporte' => 'Transporte',
                            ])
                            ->required()
                            ->searchable()
                            ->afterStateHydrated(function (callable $get, callable $set, $state) {
                                if ($state)
                                    return; // ya tiene valor, no hacemos nada
                    
                                $maquinaId = $get('maquina_id');

                                if ($maquinaId) {
                                    $maquina = \App\Models\Maquina::find($maquinaId);
                                    if ($maquina) {
                                        $set('tipo_trabajo', $maquina->tipo_trabajo);
                                    }
                                }
                            }),
                    ])
                    ->columns(3),

                Section::make('')
                    ->schema([
                        Placeholder::make('')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_trabajo) {
                                    return new HtmlString('<p>Estado actual: <strong>Sin iniciar</strong></p>');
                                }

                                $estado = 'Desconocido';
                                $totalMinutos = 0;

                                $inicio = Carbon::parse($record->getRawOriginal('fecha_hora_inicio_trabajo'));
                                $parada = $record->fecha_hora_parada_trabajo
                                    ? Carbon::parse($record->getRawOriginal('fecha_hora_parada_trabajo'))
                                    : null;

                                $reanudacion = $record->fecha_hora_reanudacion_trabajo
                                    ? Carbon::parse($record->getRawOriginal('fecha_hora_reanudacion_trabajo'))
                                    : null;

                                $fin = $record->fecha_hora_fin_trabajo
                                    ? Carbon::parse($record->getRawOriginal('fecha_hora_fin_trabajo'))
                                    : null;

                                if ($fin) {
                                    if ($parada && $reanudacion) {
                                        $totalMinutos = $inicio->diffInMinutes($parada) + $reanudacion->diffInMinutes($fin);
                                    } else {
                                        $totalMinutos = $inicio->diffInMinutes($fin);
                                    }
                                    $estado = 'Finalizado';
                                } elseif ($reanudacion) {
                                    $totalMinutos = $inicio->diffInMinutes($parada) + $reanudacion->diffInMinutes(now());
                                    $estado = 'Reanudado';
                                } elseif ($parada) {
                                    $totalMinutos = $inicio->diffInMinutes($parada);
                                    $estado = 'Pausado';
                                } else {
                                    $totalMinutos = $inicio->diffInMinutes(now());
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

                                $gpsInicio = $record->gps_inicio_trabajo ? ' (<a href="https://maps.google.com/?q=' . $record->gps_inicio_trabajo . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)' : '';
                                $gpsPausa = $record->gps_parada_trabajo ? ' (<a href="https://maps.google.com/?q=' . $record->gps_parada_trabajo . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)' : '';
                                $gpsReanudar = $record->gps_reanudacion_trabajo ? ' (<a href="https://maps.google.com/?q=' . $record->gps_reanudacion_trabajo . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)' : '';
                                $gpsFin = $record->gps_fin_trabajo ? ' (<a href="https://maps.google.com/?q=' . $record->gps_fin_trabajo . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)' : '';

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
                    ])
                    ->columns(1),

                Section::make()
                    ->visible(function ($record) {
                        if (!$record)
                            return false;

                        return (
                            $record->fecha_hora_inicio_trabajo && !$record->fecha_hora_parada_trabajo && !$record->fecha_hora_fin_trabajo ||

                            $record->fecha_hora_parada_trabajo && !$record->fecha_hora_reanudacion_trabajo && !$record->fecha_hora_fin_trabajo ||

                            $record->fecha_hora_inicio_trabajo && !$record->fecha_hora_fin_trabajo
                        );
                    })
                    ->schema([
                        Actions::make([
                            Action::make('Parar')
                                ->label('Parar trabajo')
                                ->color('warning')
                                ->button()
                                ->extraAttributes(['id' => 'btn-parar-trabajo', 'class' => 'w-full'])
                                ->visible(
                                    fn($record) =>
                                    $record &&
                                    $record->fecha_hora_inicio_trabajo &&
                                    !$record->fecha_hora_parada_trabajo &&
                                    !$record->fecha_hora_fin_trabajo
                                )
                                ->requiresConfirmation()
                                ->form([
                                    Hidden::make('gps_parada_trabajo'),
                                ])
                                ->action(function (array $data, $record) {
                                    $record->update([
                                        'fecha_hora_parada_trabajo' => now(),
                                        'gps_parada_trabajo' => $data['gps_parada_trabajo'],
                                    ]);

                                    Notification::make()
                                        ->info()
                                        ->title('Trabajo pausado con ubicación')
                                        ->send();
                                }),

                            Action::make('Reanudar')
                                ->label('Reanudar trabajo')
                                ->color('info')
                                ->extraAttributes(['id' => 'btn-reanudar-trabajo', 'class' => 'w-full'])
                                ->visible(
                                    fn($record) =>
                                    $record &&
                                    $record->fecha_hora_parada_trabajo &&
                                    !$record->fecha_hora_reanudacion_trabajo &&
                                    !$record->fecha_hora_fin_trabajo
                                )
                                ->button()
                                ->requiresConfirmation()
                                ->form([
                                    Hidden::make('gps_reanudacion_trabajo'),
                                ])
                                ->action(function (array $data, $record) {
                                    $record->update([
                                        'fecha_hora_reanudacion_trabajo' => now(),
                                        'gps_reanudacion_trabajo' => $data['gps_reanudacion_trabajo'],
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
                                    $record->fecha_hora_inicio_trabajo &&
                                    !$record->fecha_hora_fin_trabajo
                                )
                                ->button()
                                ->modalHeading('Finalizar trabajo')
                                ->modalSubmitActionLabel('Finalizar')
                                ->modalWidth('3xl')
                                ->form([
                                    TextInput::make('horas_encendido')->numeric()->required(),
                                    TextInput::make('horas_rotor')->numeric()->required(),
                                    TextInput::make('horas_trabajo')->numeric()->required(),
                                    TextInput::make('cantidad_producida')->numeric()->label('Cantidad producida (camiones o tn)')->required(),

                                    TextInput::make('consumo_gasoil')->numeric()->required()->label('Gasoil (L)'),
                                    TextInput::make('consumo_cuchillas')->numeric()->required()->label('Cuchillas usadas (ud)'),
                                    TextInput::make('consumo_muelas')->numeric()->required()->label('Muelas usadas (ud)'),

                                    FileUpload::make('horometro')
                                        ->label('Foto easygreen o horómetro')
                                        ->disk('public')
                                        ->directory('horometros')
                                        ->required(),

                                    TextInput::make('gps_fin_trabajo')
                                        ->label('GPS')
                                        ->required(),

                                    View::make('livewire.location-fin-trabajo'),
                                ])
                                ->action(function (array $data, $record) {
                                    $record->update([
                                        'horas_encendido' => $data['horas_encendido'],
                                        'horas_rotor' => $data['horas_rotor'],
                                        'horas_trabajo' => $data['horas_trabajo'],
                                        'cantidad_producida' => $data['cantidad_producida'],
                                        'horometro' => $data['horometro'],
                                        'consumo_gasoil' => $data['consumo_gasoil'],
                                        'consumo_cuchillas' => $data['consumo_cuchillas'],
                                        'consumo_muelas' => $data['consumo_muelas'],
                                        'fecha_hora_fin_trabajo' => now(),
                                        'gps_fin_trabajo' => $data['gps_fin_trabajo'],
                                    ]);

                                    Notification::make()
                                        ->success()
                                        ->title('Trabajo finalizado correctamente')
                                        ->send();
                                }),
                        ])
                            ->columns(4)
                    ]),

                Section::make('Resumen de trabajo')
                    ->visible(fn($record) => $record && $record->fecha_hora_fin_trabajo !== null)
                    ->schema([
                        Placeholder::make('horas_encendido')
                            ->label('Horas encendido')
                            ->content(fn($record) => $record->horas_encendido ?? '-'),

                        Placeholder::make('horas_rotor')
                            ->label('Horas rotor')
                            ->content(fn($record) => $record->horas_rotor ?? '-'),

                        Placeholder::make('horas_trabajo')
                            ->label('Horas trabajo')
                            ->content(fn($record) => $record->horas_trabajo ?? '-'),

                        Placeholder::make('consumo_gasoil')
                            ->label('Consumo de gasoil (L)')
                            ->content(fn($record) => $record->consumo_gasoil ?? '-'),

                        Placeholder::make('consumo_cuchillas')
                            ->label('Cuchillas usadas (ud)')
                            ->content(fn($record) => $record->consumo_cuchillas ?? '-'),

                        Placeholder::make('consumo_muelas')
                            ->label('Muelas usadas (ud)')
                            ->content(fn($record) => $record->consumo_muelas ?? '-'),

                        Placeholder::make('cantidad_producida')
                            ->label('Cantidad producida (camiones o tn)')
                            ->content(fn($record) => $record->cantidad_producida ?? '-'),

                        FileUpload::make('horometro')
                            ->label('Foto easygreen o horómetro')
                            ->disk('public')
                            ->directory('horometros')
                            ->imageEditor()
                            ->openable()
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Observaciones')
                    ->schema([
                        Textarea::make('observaciones')
                            ->rows(4)
                            ->maxLength(1000),
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
                    ->dateTime(),

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

                TextColumn::make('maquina')
                    ->label('Máquina')
                    ->formatStateUsing(function ($state, $record) {
                        $marca = $record->maquina?->marca ?? '';
                        $modelo = $record->maquina?->modelo ?? '';
                        $tipo_trabajo = $record->maquina?->tipo_trabajo ?? '';
                        return trim("$marca $modelo - ($tipo_trabajo)");
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
            'index' => Pages\ListParteTrabajoSuministroOperacionMaquinas::route('/'),
            'create' => Pages\CreateParteTrabajoSuministroOperacionMaquina::route('/create'),
            'view' => Pages\ViewParteTrabajoSuministroOperacionMaquina::route('/{record}'),
            'edit' => Pages\EditParteTrabajoSuministroOperacionMaquina::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

}
