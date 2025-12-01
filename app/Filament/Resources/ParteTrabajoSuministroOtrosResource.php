<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoSuministroOtrosResource\Pages;
use App\Filament\Resources\ParteTrabajoSuministroOtrosResource\RelationManagers;
use App\Models\ParteTrabajoSuministroOtros;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
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
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ParteTrabajoSuministroOtrosResource extends Resource
{
    protected static ?string $model = ParteTrabajoSuministroOtros::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Partes de trabajo';
    protected static ?int $navigationSort = 8;
    protected static ?string $slug = 'partes-trabajo-suministro-otros';
    public static ?string $label = 'otro';
    public static ?string $pluralLabel = 'Otros';
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
                            ->columnSpanFull()
                            ->default(Filament::auth()->user()->id)
                            ->required(),
                    ])
                    ->columns(3),

                Section::make('Fecha y horas')
                    ->schema([
                        DateTimePicker::make('fecha_hora_inicio_otros')
                            ->label('Hora de inicio')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_inicio_otros) {
                                    return Actions\Action::make('ver_gps_inicio_otros')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_inicio_otros, shouldOpenInNewTab: true);
                                }
                                return null;
                            })
                            ->disabled(fn() => !Filament::auth()->user()?->hasAnyRole(['superadmin', 'administración'])),

                        DateTimePicker::make('fecha_hora_fin_otros')
                            ->label('Hora de fin')
                            ->timezone('Europe/Madrid')
                            ->suffixAction(function ($record) {
                                if ($record?->gps_fin_otros) {
                                    return Actions\Action::make('ver_gps_fin_otros')
                                        ->icon('heroicon-o-map')
                                        ->tooltip('Ver ubicación en Google Maps')
                                        ->url('https://maps.google.com/?q=' . $record->gps_fin_otros, shouldOpenInNewTab: true);
                                }
                                return null;
                            })
                            ->disabled(fn() => !Filament::auth()->user()?->hasAnyRole(['superadmin', 'administración'])),

                        Placeholder::make('pausas_detalle')
                            ->label('Pausas registradas')
                            ->content(function ($record) {
                                if (!$record) {
                                    return 'Sin pausas';
                                }

                                $rows = '';
                                $index = 1;

                                // 1) MODO LEGACY: usar los campos antiguos del propio parte
                                $tieneLegacy =
                                    ($record->fecha_hora_parada_otros !== null)
                                    || ($record->fecha_hora_reanudacion_otros !== null);

                                if ($tieneLegacy) {
                                    $inicio = $record->fecha_hora_parada_otros
                                        ? $record->fecha_hora_parada_otros->copy()->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                        : '-';

                                    $fin = $record->fecha_hora_reanudacion_otros
                                        ? $record->fecha_hora_reanudacion_otros->copy()->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                        : '-';

                                    // Duración de la pausa legacy
                                    $duracionMin = 0;
                                    if ($record->fecha_hora_parada_otros && $record->fecha_hora_reanudacion_otros) {
                                        $duracionMin = $record->fecha_hora_parada_otros
                                            ->diffInMinutes($record->fecha_hora_reanudacion_otros);
                                    }
                                    $durH = intdiv($duracionMin, 60);
                                    $durM = $duracionMin % 60;
                                    $duracionStr = $duracionMin > 0 ? "{$durH}h {$durM}min" : '—';

                                    $gpsInicio = $record->gps_parada_ayudante
                                        ? '<a href="https://maps.google.com/?q=' . $record->gps_parada_ayudante . '" target="_blank" class="text-blue-600 underline">📍</a>'
                                        : '—';

                                    $gpsFin = $record->gps_reanudacion_ayudante
                                        ? '<a href="https://maps.google.com/?q=' . $record->gps_reanudacion_ayudante . '" target="_blank" class="text-blue-600 underline">📍</a>'
                                        : '—';

                                    $rows .= '
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <td class="px-3 py-2 text-center">' . $index . '</td>
                                            <td class="px-3 py-2 text-sm">' . $inicio . '</td>
                                            <td class="px-3 py-2 text-sm">' . $fin . '</td>
                                            <td class="px-3 py-2 text-sm text-center">' . $duracionStr . '</td>
                                            <td class="px-3 py-2 text-sm text-center">' . $gpsInicio . '</td>
                                            <td class="px-3 py-2 text-sm text-center">' . $gpsFin . '</td>
                                        </tr>';
                                } else {
                                    // 2) NUEVO MODELO: usar la relación pausas()
                                    $pausas = $record->pausas()
                                        ->orderBy('inicio_pausa')
                                        ->get();

                                    if ($pausas->isEmpty()) {
                                        return 'Sin pausas registradas.';
                                    }

                                    foreach ($pausas as $pausa) {
                                        $inicio = $pausa->inicio_pausa
                                            ? $pausa->inicio_pausa->copy()->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                            : '-';

                                        $fin = $pausa->fin_pausa
                                            ? $pausa->fin_pausa->copy()->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                            : '-';

                                        // Duración de la pausa
                                        $duracionMin = 0;
                                        if ($pausa->inicio_pausa && $pausa->fin_pausa) {
                                            $duracionMin = $pausa->inicio_pausa->diffInMinutes($pausa->fin_pausa);
                                        }
                                        $durH = intdiv($duracionMin, 60);
                                        $durM = $duracionMin % 60;
                                        $duracionStr = $duracionMin > 0 ? "{$durH}h {$durM}min" : '—';

                                        $gpsInicio = $pausa->gps_inicio_pausa
                                            ? '<a href="https://maps.google.com/?q=' . $pausa->gps_inicio_pausa . '" target="_blank" class="text-blue-600 underline">📍</a>'
                                            : '—';

                                        $gpsFin = $pausa->gps_fin_pausa
                                            ? '<a href="https://maps.google.com/?q=' . $pausa->gps_fin_pausa . '" target="_blank" class="text-blue-600 underline">📍</a>'
                                            : '—';

                                        $rows .= '
                                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                                <td class="px-3 py-2 text-center">' . $index++ . '</td>
                                                <td class="px-3 py-2 text-sm">' . $inicio . '</td>
                                                <td class="px-3 py-2 text-sm">' . $fin . '</td>
                                                <td class="px-3 py-2 text-sm text-center">' . $duracionStr . '</td>
                                                <td class="px-3 py-2 text-sm text-center">' . $gpsInicio . '</td>
                                                <td class="px-3 py-2 text-sm text-center">' . $gpsFin . '</td>
                                            </tr>';
                                    }
                                }

                                $html = '
                                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm mt-2">
                                        <table class="w-full text-sm text-left text-gray-700 dark:text-gray-200">
                                            <thead class="bg-gray-50 dark:bg-gray-800">
                                                <tr>
                                                    <th class="px-3 py-2 text-center w-12">#</th>
                                                    <th class="px-3 py-2">Inicio pausa</th>
                                                    <th class="px-3 py-2">Fin pausa</th>
                                                    <th class="px-3 py-2 text-center">Duración</th>
                                                    <th class="px-3 py-2 text-center">GPS inicio</th>
                                                    <th class="px-3 py-2 text-center">GPS fin</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                ' . $rows . '
                                            </tbody>
                                        </table>
                                    </div>';

                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),

                        Placeholder::make('tiempo_total')
                            ->label('Tiempo total')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_otros) {
                                    return 'Sin iniciar';
                                }

                                $minutos = $record->minutos_trabajados;
                                $horas = intdiv($minutos, 60);
                                $resto = $minutos % 60;

                                return "{$horas}h {$resto}min";
                            })
                            ->columnSpan(1),

                    ])
                    ->columns(2)
                    ->visible(
                        fn($record) =>
                        filled($record?->fecha_hora_inicio_otros)
                    ),

                Section::make('')
                    ->schema([
                        Placeholder::make('')
                            ->visible(fn($record) => $record && filled($record->descripcion))
                            ->content(function ($record) {
                                return new HtmlString('
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2 flex items-center gap-1">
                        Descripción del trabajo
                    </h3>
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                            ' . nl2br(e($record->descripcion)) . '
                        </p>
                    </div>
                </div>
            ');
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

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
                                    $record->fecha_hora_inicio_otros && !$record->fecha_hora_fin_otros
                                );
                            })->fullWidth()
                    ]),

                Section::make('Fotos')
                    ->visible(fn($record) => $record && filled($record->fecha_hora_fin_otros))
                    ->schema([
                        FileUpload::make('fotos')
                            ->label('Fotos')
                            ->image()
                            ->multiple()
                            ->maxFiles(4)
                            ->directory('parte_trabajo_otros')
                            ->openable()
                            ->downloadable()
                            ->panelLayout('grid'),
                    ]),

                Section::make()
                    ->visible(function ($record) {
                        if (!$record)
                            return false;

                        return (
                            $record->fecha_hora_inicio_otros && !$record->fecha_hora_fin_otros
                        );
                    })
                    ->schema([
                        Actions::make([
                            Action::make('Parar')
                                ->label('Parar trabajo')
                                ->color('warning')
                                ->button()
                                ->extraAttributes(['id' => 'btn-parar-trabajo', 'class' => 'w-full'])
                                ->visible(function ($record) {
                                    if (
                                        !$record ||
                                        !$record->fecha_hora_inicio_otros ||
                                        $record->fecha_hora_fin_otros
                                    ) {
                                        return false;
                                    }

                                    // No mostrar si ya hay una pausa abierta
                                    $hayPausaAbierta = $record->pausas()
                                        ->whereNull('fin_pausa')
                                        ->exists();

                                    if ($hayPausaAbierta) {
                                        return false;
                                    }

                                    $u = auth()->user();
                                    if (!$u) {
                                        return false;
                                    }

                                    $allowed = $u->hasAnyRole(['operarios', 'superadmin', 'administración', 'proveedor de servicio']);
                                    $exclude = $u->hasAllRoles(['operarios', 'técnico']);

                                    return $allowed && !$exclude;
                                })
                                ->requiresConfirmation()
                                ->form([
                                    TextInput::make('gps_inicio_pausa')
                                        ->label('GPS inicio pausa')
                                        ->required()
                                        ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                                    // Componente que rellena el campo con la ubicación del navegador
                                    View::make('livewire.location-inicio-pausa')
                                        ->columnSpanFull(),
                                ])
                                ->action(function (array $data, $record) {
                                    // Creamos una nueva pausa
                                    $record->pausas()->create([
                                        'inicio_pausa' => now(),
                                        'gps_inicio_pausa' => $data['gps_inicio_pausa'] ?? null,
                                    ]);

                                    Notification::make()
                                        ->info()
                                        ->title('Trabajo pausado')
                                        ->send();
                                }),

                            Action::make('Reanudar')
                                ->label('Reanudar trabajo')
                                ->color('info')
                                ->extraAttributes(['id' => 'btn-reanudar-trabajo', 'class' => 'w-full'])
                                ->visible(function ($record) {
                                    if (
                                        !$record ||
                                        !$record->fecha_hora_inicio_otros ||
                                        $record->fecha_hora_fin_otros
                                    ) {
                                        return false;
                                    }

                                    // Solo mostrar si hay una pausa abierta
                                    $hayPausaAbierta = $record->pausas()
                                        ->whereNull('fin_pausa')
                                        ->exists();

                                    if (!$hayPausaAbierta) {
                                        return false;
                                    }

                                    $u = auth()->user();
                                    if (!$u) {
                                        return false;
                                    }

                                    $allowed = $u->hasAnyRole(['operarios', 'superadmin', 'administración', 'proveedor de servicio']);
                                    $exclude = $u->hasAllRoles(['operarios', 'técnico']);

                                    return $allowed && !$exclude;
                                })
                                ->button()
                                ->requiresConfirmation()
                                ->form([
                                    TextInput::make('gps_fin_pausa')
                                        ->label('GPS fin pausa')
                                        ->required()
                                        ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                                    // Componente que rellena el campo con la ubicación del navegador
                                    View::make('livewire.location-fin-pausa')
                                        ->columnSpanFull(),
                                ])
                                ->action(function (array $data, $record) {
                                    $pausa = $record->pausas()
                                        ->whereNull('fin_pausa')
                                        ->latest('inicio_pausa')
                                        ->first();

                                    if (!$pausa) {
                                        Notification::make()
                                            ->danger()
                                            ->title('No hay ninguna pausa activa')
                                            ->send();

                                        return;
                                    }

                                    $pausa->update([
                                        'fin_pausa' => now(),
                                        'gps_fin_pausa' => $data['gps_fin_pausa'] ?? null,
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
                                    $record->fecha_hora_inicio_otros &&
                                    !$record->fecha_hora_fin_otros
                                )
                                ->button()
                                ->modalHeading('Finalizar trabajo')
                                ->modalDescription('Añade (si quieres) hasta 4 fotos y confirma la ubicación GPS para cerrar el trabajo.')
                                ->modalSubmitActionLabel('Finalizar')
                                ->modalWidth('xl')
                                ->form([
                                    Section::make('Fotos (máx. 4)')
                                        ->schema([
                                            FileUpload::make('fotos')
                                                ->label('')
                                                ->image()
                                                ->multiple()
                                                ->maxFiles(4)
                                                ->reorderable()
                                                ->openable()
                                                ->downloadable()
                                                ->directory('parte_trabajo_otros')
                                                ->acceptedFileTypes(['image/*'])
                                                ->preserveFilenames()
                                                ->panelLayout('grid') // ✅ válido en Filament 3
                                                ->helperText('Puedes arrastrar para reordenar. Formatos comunes de imagen, hasta 4.'),
                                        ])
                                        ->columns(1), // controla el número de columnas del grid

                                    TextInput::make('gps_fin_otros')
                                        ->label('GPS')
                                        ->required()
                                        ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                                    View::make('livewire.location-fin-otros')->columnSpanFull(),
                                ])
                                ->action(function (array $data, $record) {
                                    DB::transaction(function () use ($data, $record) {
                                        // Merge de fotos nuevas con las existentes (sin duplicados) y límite 4
                                        $existentes = collect((array) $record->fotos)->filter()->values()->all();
                                        $nuevas = collect((array) ($data['fotos'] ?? []))->filter()->values()->all();

                                        // Normaliza rutas (por si vienen como arrays con 'path' u objetos)
                                        $normalizar = function ($item) {
                                            // Si el FileUpload devuelve array/objeto, intenta extraer la ruta
                                            if (is_array($item)) {
                                                return $item['path'] ?? $item['url'] ?? Arr::first($item) ?? null;
                                            }
                                            return is_string($item) ? $item : null;
                                        };

                                        $existentes = array_values(array_filter(array_map($normalizar, $existentes)));
                                        $nuevas = array_values(array_filter(array_map($normalizar, $nuevas)));

                                        $merged = array_values(array_unique(array_merge($existentes, $nuevas)));
                                        $merged = array_slice($merged, 0, 4);

                                        // Cerrar cualquier pausa que haya quedado abierta
                                        $record->pausas()
                                            ->whereNull('fin_pausa')
                                            ->update(['fin_pausa' => now()]);

                                        $record->update([
                                            'fecha_hora_fin_otros' => now(),
                                            'gps_fin_otros' => $data['gps_fin_otros'],
                                            'fotos' => $merged,
                                        ]);
                                    });

                                    Notification::make()
                                        ->success()
                                        ->title('Trabajo finalizado correctamente')
                                        ->send();

                                    return redirect(ParteTrabajoSuministroOtrosResource::getUrl());
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
                TextColumn::make('fecha_hora_inicio_otros')
                    ->label('Fecha y hora')
                    ->weight(FontWeight::Bold)
                    ->dateTime('d/m/Y H:i')
                    ->timezone('Europe/Madrid')
                    ->sortable(),

                TextColumn::make('usuario')
                    ->label('Usuario')
                    ->formatStateUsing(function ($state, $record) {
                        $nombre = $record->usuario?->name ?? '';
                        $apellido = $record->usuario?->apellidos ?? '';
                        $inicialApellido = $apellido ? strtoupper(substr($apellido, 0, 1)) . '.' : '';
                        return trim("$nombre $inicialApellido");
                    })
                    ->weight(FontWeight::Bold),

                TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->limit(80)
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
            'index' => Pages\ListParteTrabajoSuministroOtros::route('/'),
            'create' => Pages\CreateParteTrabajoSuministroOtros::route('/create'),
            'view' => Pages\ViewParteTrabajoSuministroOtros::route('/{record}'),
            'edit' => Pages\EditParteTrabajoSuministroOtros::route('/{record}/edit'),
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
