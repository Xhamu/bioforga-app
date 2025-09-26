<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoTallerMaquinariaResource\Pages;
use App\Models\ParteTrabajoTallerMaquinaria;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Actions\Action as FormAction;
use Illuminate\Database\Eloquent\Model;

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
                // ====== DATOS GENERALES ======
                Section::make('Datos generales')
                    ->schema([
                        Select::make('usuario_id')
                            ->relationship(
                                'usuario',
                                'name',
                                modifyQueryUsing: function ($query) {
                                    $user = Filament::auth()->user();
                                    if ($user->hasAnyRole(['superadmin', 'administrador', 'administración'])) {
                                        $query->whereDoesntHave('roles', fn($q) => $q->where('name', 'superadmin'));
                                    } else {
                                        $query->where('id', $user->id);
                                    }
                                }
                            )
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->name . ' ' . $record->apellidos)
                            ->searchable()
                            ->preload()
                            ->default(fn() => Filament::auth()->user()->id)
                            ->required(),
                    ])
                    ->columns(1),

                // ====== RESUMEN “DATOS DEL TRABAJO” ======
                Section::make('Datos del trabajo')
                    ->visible(fn($record) => $record && $record->fecha_hora_inicio_taller_maquinaria)
                    ->schema([
                        Placeholder::make('')
                            ->content(function ($record) {
                                if (!$record || !$record->taller || !$record->maquina_id || !$record->horas_servicio) {
                                    return null;
                                }

                                $tallerNombre = $record->taller?->nombre ?? '-';
                                $maquina = $record->maquina;
                                $maquinaLabel = $maquina ? "{$maquina->marca} {$maquina->modelo}" : '-';
                                $horas = $record->horas_servicio;
                                $tipoActuacion = $record->tipo_actuacion ?? '-';

                                // IDs → nombres, filtrando no numéricos (por "ninguno")
                                $trabajoIds = is_array($record->trabajo_realizado) ? $record->trabajo_realizado : json_decode($record->trabajo_realizado ?? '[]', true);
                                $recambioIds = is_array($record->recambios_utilizados) ? $record->recambios_utilizados : json_decode($record->recambios_utilizados ?? '[]', true);

                                $trabajoNombres = \App\Models\TrabajoRealizado::whereIn('id', array_filter($trabajoIds, 'is_numeric'))->pluck('nombre')->toArray();

                                $recambiosTexto = 'Ninguno';
                                $idsNumericos = array_filter($recambioIds, 'is_numeric');
                                if (!empty($idsNumericos)) {
                                    $recambiosNombres = \App\Models\RecambioUtilizado::whereIn('id', $idsNumericos)->pluck('nombre')->toArray();
                                    $recambiosTexto = implode(', ', $recambiosNombres) ?: 'Ninguno';
                                } elseif (empty($recambioIds)) {
                                    $recambiosTexto = 'Ninguno';
                                }

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
                                                    <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Horas de servicio</th>
                                                    <td class="px-4 py-3">' . e($horas) . ' h</td>
                                                </tr>';

                                if ($record->fecha_hora_fin_taller_maquinaria) {
                                    $tabla .= '
                                                <tr>
                                                    <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Tipo de actuación</th>
                                                    <td class="px-4 py-3">' . e(ucfirst($tipoActuacion)) . '</td>
                                                </tr>
                                                <tr>
                                                    <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Trabajo realizado</th>
                                                    <td class="px-4 py-3">' . e(implode(', ', $trabajoNombres)) . '</td>
                                                </tr>
                                                <tr>
                                                    <th class="px-4 py-3 font-medium text-gray-600 dark:text-gray-300">Recambios utilizados</th>
                                                    <td class="px-4 py-3">' . e($recambiosTexto) . '</td>
                                                </tr>';
                                }

                                $tabla .= '
                                            </tbody>
                                        </table>
                                    </div>';

                                return new HtmlString($tabla);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                // ====== ESTADO ACTUAL ======
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
                                    </div>';

                                return new HtmlString($tabla);
                            })
                            ->visible(fn() => Filament::auth()->user()?->hasAnyRole(['superadmin', 'administración']))
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                // ====== FECHAS / HORAS ======
                Section::make('Fechas y horas')
                    ->schema([
                        DateTimePicker::make('fecha_hora_inicio_taller_maquinaria')
                            ->label('Hora de inicio taller maquinaria')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_inicio_taller_maquinaria) {
                                    return Actions\Action::make('ver_gps_inicio_taller_maquinaria')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_inicio_taller_maquinaria, shouldOpenInNewTab: true);
                                }
                                return null;
                            }),

                        DateTimePicker::make('fecha_hora_fin_taller_maquinaria')
                            ->label('Hora de finalización taller maquinaria')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_fin_taller_maquinaria) {
                                    return Actions\Action::make('ver_gps_fin_taller_maquinaria')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_fin_taller_maquinaria, shouldOpenInNewTab: true);
                                }
                                return null;
                            }),

                        Placeholder::make('tiempo_total_taller_maquinaria')
                            ->label('Tiempo total taller maquinaria')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_taller_maquinaria) {
                                    return 'Sin iniciar';
                                }

                                $inicio = Carbon::parse($record->fecha_hora_inicio_taller_maquinaria)->timezone('Europe/Madrid');
                                $fin = $record->fecha_hora_fin_taller_maquinaria
                                    ? Carbon::parse($record->fecha_hora_fin_taller_maquinaria)->timezone('Europe/Madrid')
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
                        filled($record?->fecha_hora_inicio_taller_maquinaria)
                    ),

                // ====== FINALIZAR TRABAJO (Action) ======
                Section::make()
                    ->visible(fn($record) => $record && $record->fecha_hora_inicio_taller_maquinaria && !$record->fecha_hora_fin_taller_maquinaria)
                    ->schema([
                        Actions::make([
                            Action::make('Finalizar')
                                ->label('Finalizar trabajo')
                                ->color('danger')
                                ->extraAttributes(['class' => 'w-full'])
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
                                        ->options(
                                            ['ninguno' => 'Ninguno'] + \App\Models\RecambioUtilizado::pluck('nombre', 'id')->toArray()
                                        )
                                        ->required(),

                                    Select::make('estado')
                                        ->label('Estado')
                                        ->options([
                                            'reparado' => 'Reparado',
                                            'sin_reparar' => 'Sin reparar',
                                            'en_proceso' => 'En proceso',
                                        ])
                                        ->default('en_proceso')
                                        ->required(),

                                    FileUpload::make('fotos')
                                        ->label('Fotos')
                                        ->image()
                                        ->multiple()
                                        ->maxFiles(4)
                                        ->directory('taller-maquinaria')
                                        ->imagePreviewHeight('200')
                                        ->reorderable()
                                        ->panelLayout('grid')
                                        ->columnSpanFull(),
                                ])
                                ->action(function (array $data, $record) {
                                    $record->update([
                                        'fecha_hora_fin_taller_maquinaria' => now(),
                                        'tipo_actuacion' => $data['tipo_actuacion'],
                                        'trabajo_realizado' => $data['trabajo_realizado'],
                                        'recambios_utilizados' => $data['recambios_utilizados'],
                                        'estado' => $data['estado'] ?? 'en_proceso',
                                        'fotos' => $data['fotos'] ?? [],
                                    ]);

                                    Notification::make()
                                        ->success()
                                        ->title('Trabajo finalizado correctamente')
                                        ->send();

                                    return redirect(ParteTrabajoTallerMaquinariaResource::getUrl());
                                }),
                        ])->columns(4)
                    ]),

                // ====== DETALLES ADICIONALES (edición/creación sin finalizar) ======
                Section::make('Detalles adicionales')
                    ->schema([
                        Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'reparado' => 'Reparado',
                                'sin_reparar' => 'Sin reparar',
                                'en_proceso' => 'En proceso',
                            ])
                            ->default('en_proceso')
                            ->columnSpanFull()
                            ->native(false),

                        FileUpload::make('fotos')
                            ->label('Fotos')
                            ->image()
                            ->multiple()
                            ->maxFiles(4)
                            ->directory('taller-maquinaria')
                            ->imagePreviewHeight('200')
                            ->reorderable()
                            ->panelLayout('grid')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn($record) => $record && $record->fecha_hora_fin_taller_maquinaria)
                    ->columns(2),

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
                                    $record->fecha_hora_inicio_taller_maquinaria && !$record->fecha_hora_fin_taller_maquinaria
                                );
                            })->fullWidth()
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha_hora_inicio_taller_maquinaria')
                    ->label('Fecha y hora')
                    ->weight(FontWeight::Bold)
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Europe/Madrid')
                    ->sortable()
                    ->tooltip(
                        fn($record) =>
                        $record->fecha_hora_inicio_taller_maquinaria
                        ? $record->fecha_hora_inicio_taller_maquinaria->format('d/m/Y H:i')
                        : null
                    ),

                TextColumn::make('usuario')
                    ->label('Usuario')
                    ->formatStateUsing(function ($state, $record) {
                        $nombre = $record->usuario?->name ?? '';
                        $apellido = $record->usuario?->apellidos ?? '';
                        return trim("$nombre $apellido");
                    })
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->tooltip(fn($record) => $record->usuario?->email ?? ''),

                TextColumn::make('maquina.marca')
                    ->label('Máquina')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        $marca = $record->maquina?->marca ?? '';
                        $modelo = $record->maquina?->modelo ?? '';
                        $tipo = ucfirst($record->maquina?->tipo_trabajo ?? '');
                        return "{$marca} {$modelo} - {$tipo}";
                    })
                    ->tooltip(fn($record) => $record->maquina?->numero_serie ?? ''),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'reparado' => 'success',
                        'sin_reparar' => 'danger',
                        'en_proceso' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'reparado' => '✅ Reparado',
                        'sin_reparar' => '❌ Sin reparar',
                        'en_proceso' => '⏳ En proceso',
                        default => ucfirst((string) $state),
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('fecha')
                    ->columns(2)
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['desde'] ?? null, fn($q, $date) => $q->whereDate('fecha_hora_inicio_taller_maquinaria', '>=', $date))
                            ->when($data['hasta'] ?? null, fn($q, $date) => $q->whereDate('fecha_hora_inicio_taller_maquinaria', '<=', $date));
                    }),

                Tables\Filters\SelectFilter::make('maquina_id')
                    ->label('Máquina')
                    ->relationship('maquina', 'marca')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->marca} {$record->modelo}")
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('usuario_id')
                    ->label('Usuario')
                    ->relationship('usuario', 'name')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->name} {$record->apellidos}")
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('taller_id')
                    ->label('Taller')
                    ->relationship('taller', 'nombre')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TrashedFilter::make()
                    ->visible(fn() => Filament::auth()->user()?->hasRole('superadmin'))
                    ->columnSpanFull(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(2)
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->tooltip('Ver detalles'),
                Tables\Actions\EditAction::make()
                    ->tooltip('Editar registro'),
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
        return [];
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
            ->withoutGlobalScopes([SoftDeletingScope::class]);

        $user = Filament::auth()->user();
        $rolesPermitidos = ['superadmin', 'administración', 'administrador'];

        if (!$user->hasAnyRole($rolesPermitidos)) {
            $query->where('usuario_id', $user->id);
        }

        return $query;
    }
}
