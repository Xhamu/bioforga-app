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
                            ->label('Usuario')
                            ->searchable()
                            ->default(Filament::auth()->user()->id)
                            ->options([
                                Filament::auth()->user()->id => Filament::auth()->user()->name . ' ' . Filament::auth()->user()->apellidos
                            ])
                            ->required()
                            ->columnSpanFull(),
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

                                $estado = $record->fecha_hora_fin_ayudante ? 'Finalizado' : 'Trabajando';
                                $totalMinutos = Carbon::parse($record->getRawOriginal('fecha_hora_inicio_ayudante'))
                                    ->diffInMinutes(
                                        $record->fecha_hora_fin_ayudante
                                        ? Carbon::parse($record->getRawOriginal('fecha_hora_fin_ayudante'))
                                        : now()
                                    );

                                $horas = floor($totalMinutos / 60);
                                $minutos = $totalMinutos % 60;

                                $emoji = match ($estado) {
                                    'Trabajando' => '🟢',
                                    'Finalizado' => '✅',
                                    default => '❓',
                                };

                                $inicio = Carbon::parse($record->getRawOriginal('fecha_hora_inicio_ayudante'));
                                $fin = $record->fecha_hora_fin_ayudante ? Carbon::parse($record->getRawOriginal('fecha_hora_fin_ayudante')) : null;

                                $gpsInicio = $record->gps_inicio_ayudante
                                    ? ' (<a href="https://maps.google.com/?q=' . $record->gps_inicio_ayudante . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)'
                                    : '';

                                $gpsFin = $record->gps_fin_ayudante
                                    ? ' (<a href="https://maps.google.com/?q=' . $record->gps_fin_ayudante . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)'
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
                            $record->fecha_hora_inicio_ayudante && !$record->fecha_hora_fin_ayudante
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
                                        ->required(),
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
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
