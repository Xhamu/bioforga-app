<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoSuministroDesplazamientoResource\Pages;
use App\Filament\Resources\ParteTrabajoSuministroDesplazamientoResource\RelationManagers;
use App\Models\ParteTrabajoSuministroDesplazamiento;
use App\Models\Vehiculo;
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
                            ->relationship('usuario', 'name')
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->name . ' ' . $record->apellidos)
                            ->searchable()
                            ->preload()
                            ->default(Filament::auth()->user()->id)
                            ->required(),

                        Select::make('vehiculo_id')
                            ->label('Vehículo')
                            ->relationship(
                                name: 'vehiculo',
                                titleAttribute: 'marca',
                                modifyQueryUsing: fn($query) => $query->where('conductor_habitual', Auth::id())
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn($record) => $record->marca . ' ' . $record->modelo . ' (' . $record->matricula . ')'
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->default(function () {
                                $vehiculos = Vehiculo::where('conductor_habitual', Auth::id())->get();
                                return $vehiculos->count() === 1 ? $vehiculos->first()->id : null;
                            }),

                    ])
                    ->columns(2),

                Section::make('')
                    ->schema([
                        Select::make('destino')
                            ->label('Destino')
                            ->searchable()
                            ->options([
                                'obra' => 'Obra',
                                'trabajo' => 'Trabajo',
                                'otro' => 'Otro',
                            ])
                            ->required()
                            ->nullable(),

                        Placeholder::make('')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_desplazamiento) {
                                    return new HtmlString('<p>Estado actual: <strong>Sin iniciar</strong></p>');
                                }

                                $estado = $record->fecha_hora_fin_desplazamiento ? 'Finalizado' : 'Trabajando';
                                $totalMinutos = Carbon::parse($record->getRawOriginal('fecha_hora_inicio_desplazamiento'))
                                    ->diffInMinutes(
                                        $record->fecha_hora_fin_desplazamiento
                                        ? Carbon::parse($record->getRawOriginal('fecha_hora_fin_desplazamiento'))
                                        : now()
                                    );

                                $horas = floor($totalMinutos / 60);
                                $minutos = $totalMinutos % 60;

                                $emoji = match ($estado) {
                                    'Trabajando' => '🟢',
                                    'Finalizado' => '✅',
                                    default => '❓',
                                };

                                $inicio = Carbon::parse($record->getRawOriginal('fecha_hora_inicio_desplazamiento'));
                                $fin = $record->fecha_hora_fin_desplazamiento ? Carbon::parse($record->getRawOriginal('fecha_hora_fin_desplazamiento')) : null;

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
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

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
                                ->label('Finalizar trabajo')
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
                                        ->required(),
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
            'index' => Pages\ListParteTrabajoSuministroDesplazamientos::route('/'),
            'create' => Pages\CreateParteTrabajoSuministroDesplazamiento::route('/create'),
            'view' => Pages\ViewParteTrabajoSuministroDesplazamiento::route('/{record}'),
            'edit' => Pages\EditParteTrabajoSuministroDesplazamiento::route('/{record}/edit'),
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
