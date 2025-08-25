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

                        Placeholder::make('')
                            ->content(function ($record) {
                                if (!$record || !$record->fecha_hora_inicio_otros) {
                                    return new HtmlString('<p>Estado actual: <strong>Sin iniciar</strong></p>');
                                }

                                $inicio = Carbon::parse($record->getRawOriginal('fecha_hora_inicio_otros'))->timezone('Europe/Madrid');
                                $fin = $record->fecha_hora_fin_otros
                                    ? Carbon::parse($record->getRawOriginal('fecha_hora_fin_otros'))->timezone('Europe/Madrid')
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

                                $gpsInicio = $record->gps_inicio_otros
                                    ? ' (<a href="https://maps.google.com/?q=' . $record->gps_inicio_otros . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)'
                                    : '';

                                $gpsFin = $record->gps_fin_otros
                                    ? ' (<a href="https://maps.google.com/?q=' . $record->gps_fin_otros . '" target="_blank" class="text-blue-600 underline">📍 Ver ubicación</a>)'
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
                    ->visible(fn($record) => $record && !empty($record->fotos))
                    ->schema([
                        FileUpload::make('fotos')
                            ->label('Fotos')
                            ->image()
                            ->multiple()
                            ->maxFiles(4)
                            ->directory('parte_trabajo_otros')
                            ->openable()
                            ->downloadable()
                            ->panelLayout('grid')
                            ->disabled()
                            ->dehydrated(false),
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
                                    Section::make('Fotos')
                                        ->schema([
                                            FileUpload::make('fotos')
                                                ->label('Fotos (máx. 4)')
                                                ->image()
                                                ->multiple()
                                                ->maxFiles(4)
                                                ->reorderable()
                                                ->openable()
                                                ->downloadable()
                                                ->directory('parte_trabajo_otros')
                                                ->acceptedFileTypes(['image/*'])
                                                ->preserveFilenames()
                                                ->panelLayout('grid')
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

                                        $record->update([
                                            'fecha_hora_fin_otros' => now('Europe/Madrid'),
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
