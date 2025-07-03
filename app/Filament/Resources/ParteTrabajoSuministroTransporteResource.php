<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoSuministroTransporteResource\Pages;
use App\Filament\Resources\ParteTrabajoSuministroTransporteResource\RelationManagers;
use App\Filament\Resources\ParteTrabajoSuministroTransporteResource\RelationManagers\CargasRelationManager;
use App\Models\AlmacenIntermedio;
use App\Models\Camion;
use App\Models\ParteTrabajoSuministroTransporte;
use App\Models\Referencia;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\View;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ParteTrabajoSuministroTransporteResource extends Resource
{
    protected static ?string $model = ParteTrabajoSuministroTransporte::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Partes de trabajo';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'partes-trabajo-suministro-transporte';
    public static ?string $label = 'suministro del transportista';
    public static ?string $pluralLabel = 'Suministros del transportista';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos del Transporte')
                    ->schema([
                        Select::make('usuario_id')
                            ->relationship(
                                name: 'usuario',
                                titleAttribute: 'name',
                                modifyQueryUsing: function ($query) {
                                    $user = Filament::auth()->user();

                                    // Si el usuario tiene el rol de transportista, solo mostrar su propio registro
                                    if ($user->hasRole('transportista')) {
                                        $query->where('id', $user->id);
                                    } else {
                                        $query->whereHas('roles', function ($q) {
                                            $q->whereIn('name', ['administración', 'administrador', 'transportista']);
                                        });
                                    }
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->default(Filament::auth()->user()->id)
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->name . ' ' . strtoupper(substr($record->apellidos, 0, 1)) . '.')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn($state, callable $set) => $set('referencia_select', null)),

                        Select::make('camion_id')
                            ->label('Camión')
                            ->options(function (callable $get) {
                                $usuarioId = $get('usuario_id');
                                if (!$usuarioId) {
                                    return ['' => '- Selecciona primero un usuario -'];
                                }

                                $usuario = \App\Models\User::find($usuarioId);

                                if ($usuario?->proveedor_id) {
                                    // Caso con proveedor_id → buscar camiones del proveedor
                                    $camiones = \App\Models\Camion::where('proveedor_id', $usuario->proveedor_id)->get();
                                } else {
                                    // Caso sin proveedor_id → buscar camiones vinculados por camion_user
                                    $camiones = $usuario->camiones()->get();
                                }

                                if ($camiones->isEmpty()) {
                                    return ['' => '- No hay ningún camión vinculado -'];
                                }

                                return $camiones->mapWithKeys(fn($camion) => [
                                    $camion->id => mb_convert_encoding(
                                        '[' . $camion->matricula_cabeza . '] ' . $camion->marca . ' ' . $camion->modelo,
                                        'UTF-8',
                                        'UTF-8'
                                    )
                                ])->toArray();
                            })
                            ->default(function (callable $get) {
                                $usuarioId = $get('usuario_id');
                                if (!$usuarioId)
                                    return null;

                                $usuario = \App\Models\User::find($usuarioId);

                                if ($usuario?->proveedor_id) {
                                    // Caso con proveedor_id → buscar camiones del proveedor
                                    $camiones = \App\Models\Camion::where('proveedor_id', $usuario->proveedor_id)->get();
                                } else {
                                    // Caso sin proveedor_id → buscar camiones vinculados por camion_user
                                    $camiones = $usuario->camiones()->get();
                                }

                                return $camiones->count() === 1 ? $camiones->first()->id : null;
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive() // <- para que se actualice cuando cambia usuario_id
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ]),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),

                Section::make()
                    ->visible(fn($record) => static::hasCargasActions($record))
                    ->schema([
                        Grid::make(1)
                            ->schema(function ($record) {
                                if (!$record)
                                    return [];

                                if ($record->cliente_id && $record->albaran) {
                                    return [];
                                }

                                $ultimaCarga = $record->cargas()->latest()->first();

                                $acciones = [];

                                if (
                                    (!$ultimaCarga || $ultimaCarga->fecha_hora_fin_carga !== null)
                                    && is_null($record->cliente_id)
                                    && is_null($record->almacen_id)
                                ) {
                                    $acciones[] = Action::make('Iniciar carga')
                                        ->label('Iniciar carga')
                                        ->button()
                                        ->size('xl')
                                        ->modalHeading('Iniciar nueva carga')
                                        ->modalSubmitActionLabel('Iniciar')
                                        ->modalWidth('xl')
                                        ->extraAttributes(['class' => 'w-full']) // Hace que el botón ocupe todo el ancho disponible
                                        ->form([
                                            Select::make('eleccion')
                                                ->label('')
                                                ->options([
                                                    'referencia' => 'Referencia',
                                                    'almacen' => 'Almacén intermedio',
                                                ])
                                                ->searchable()
                                                ->reactive(), // Esto es necesario para reaccionar al cambio y actualizar el otro campo
                    
                                            Select::make('almacen_id')
                                                ->label('Almacén intermedio')
                                                ->options(function () use ($record) {
                                                    $usuarioId = $record?->usuario_id;

                                                    $almacenesIds = \DB::table('almacenes_users')
                                                        ->where('user_id', $usuarioId)
                                                        ->pluck('almacen_id');

                                                    $almacenes = $almacenesIds->isNotEmpty()
                                                        ? AlmacenIntermedio::whereIn('id', $almacenesIds)->get()
                                                        : AlmacenIntermedio::all();

                                                    return $almacenes->mapWithKeys(function ($almacen) {
                                                        $label = "{$almacen->referencia} ({$almacen->monte_parcela}, {$almacen->ayuntamiento})";
                                                        return [$almacen->id => mb_convert_encoding($label, 'UTF-8', 'UTF-8')];
                                                    });
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->visible(fn(callable $get) => $get('eleccion') === 'almacen'),

                                            Select::make('referencia_select')
                                                ->label('Referencia')
                                                ->options(function () use ($record) {
                                                    $usuarioId = $record?->usuario_id;

                                                    if (!$usuarioId) {
                                                        return []; // No mostrar nada si no se ha seleccionado usuario
                                                    }

                                                    $referenciasIds = \DB::table('referencias_users')
                                                        ->where('user_id', $usuarioId)
                                                        ->pluck('referencia_id');

                                                    if ($referenciasIds->isEmpty()) {
                                                        return [];
                                                    }

                                                    $referencias = Referencia::whereIn('id', $referenciasIds)
                                                        ->with('proveedor', 'cliente')
                                                        ->get();

                                                    return $referencias->mapWithKeys(function ($referencia) {
                                                        $label = "{$referencia->referencia} | " .
                                                            ($referencia->proveedor?->razon_social ?? $referencia->cliente?->razon_social ?? 'Sin razón social') .
                                                            " ({$referencia->monte_parcela}, {$referencia->ayuntamiento})";

                                                        return [$referencia->id => mb_convert_encoding($label, 'UTF-8', 'UTF-8')];
                                                    });
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->reactive() // Para que se recargue cuando cambia usuario_id
                                                ->visible(fn(callable $get) => $get('eleccion') === 'referencia'),

                                            TextInput::make('gps_inicio_carga')
                                                ->label('GPS')
                                                ->required()
                                                ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                                            View::make('livewire.location-inicio-carga')->columnSpanFull(),
                                        ])
                                        ->action(function (array $data, $record) {
                                            if ($data['eleccion'] === 'almacen') {
                                                $record->cargas()->create([
                                                    'almacen_id' => $data['almacen_id'],
                                                    'fecha_hora_inicio_carga' => now(),
                                                    'gps_inicio_carga' => $data['gps_inicio_carga'] ?? '0.0000, 0.0000',
                                                ]);
                                            } else {
                                                $record->cargas()->create([
                                                    'referencia_id' => $data['referencia_select'],
                                                    'fecha_hora_inicio_carga' => now(),
                                                    'gps_inicio_carga' => $data['gps_inicio_carga'] ?? '0.0000, 0.0000',
                                                ]);
                                            }

                                            Notification::make()
                                                ->success()
                                                ->title('Carga iniciada correctamente')
                                                ->send();

                                            return redirect(request()->header('Referer')); // Recargar página
                                        })
                                        ->color('success');
                                }

                                if ($ultimaCarga && !$ultimaCarga->fecha_hora_fin_carga) {
                                    $acciones[] = Action::make('Finalizar carga')
                                        ->label('Finalizar carga')
                                        ->button()
                                        ->size('xl')
                                        ->modalHeading('Finalizar carga en curso')
                                        ->modalSubmitActionLabel('Finalizar')
                                        ->modalWidth('xl')
                                        ->extraAttributes(['class' => 'w-full']) // Hace que el botón ocupe todo el ancho disponible
                                        ->form([
                                            TextInput::make('cantidad')
                                                ->label('Cantidad (m³)')
                                                ->required()
                                                ->step(0.01)
                                                ->dehydrateStateUsing(fn($state) => str_replace(',', '.', $state)),

                                            TextInput::make('gps_fin_carga')
                                                ->label('GPS')
                                                ->required()
                                                ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                                            View::make('livewire.location-fin-carga')->columnSpanFull(),

                                        ])
                                        ->action(function (array $data, $record) {
                                            $ultimaCarga = $record->cargas()->latest()->first();

                                            if (!$ultimaCarga || $ultimaCarga->fecha_hora_fin_carga) {
                                                Notification::make()
                                                    ->danger()
                                                    ->title('No hay ninguna carga en progreso.')
                                                    ->send();
                                                return;
                                            }

                                            $ultimaCarga->update([
                                                'fecha_hora_fin_carga' => now(),
                                                'gps_fin_carga' => $data['gps_fin_carga'] ?? '0.0000, 0.0000',
                                                'cantidad' => $data['cantidad'],
                                            ]);

                                            $record->update([
                                                'cantidad_total' => $record->cargas()->sum('cantidad'),
                                            ]);

                                            Notification::make()
                                                ->success()
                                                ->title('Carga finalizada correctamente')
                                                ->send();

                                            return redirect(request()->header('Referer')); // Recargar página
                                        })
                                        ->color('danger');
                                }

                                if (
                                    $ultimaCarga &&
                                    $ultimaCarga->fecha_hora_fin_carga !== null &&
                                    is_null($record->cliente_id) &&
                                    is_null($record->almacen_id)
                                ) {
                                    $acciones[] = Action::make('Iniciar descarga')
                                        ->label('Iniciar descarga')
                                        ->button()
                                        ->size('xl')
                                        ->modalHeading('Iniciar descarga')
                                        ->modalSubmitActionLabel('Guardar descarga')
                                        ->modalWidth('xl')
                                        ->extraAttributes(['class' => 'w-full'])
                                        ->form([
                                            Select::make('eleccion')
                                                ->label('Tipo de destino')
                                                ->searchable()
                                                ->options([
                                                    'cliente' => 'Cliente',
                                                    'almacen_intermedio' => 'Almacén intermedio',
                                                ])
                                                ->required()
                                                ->reactive(),

                                            Select::make('cliente_id')
                                                ->label('Cliente')
                                                ->options(function () {
                                                    return \App\Models\Cliente::where('tipo_cliente', 'Suministro')
                                                        ->pluck('razon_social', 'id');
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->visible(fn(callable $get) => $get('eleccion') === 'cliente'),

                                            Select::make('almacen_id')
                                                ->label('Almacén intermedio')
                                                ->options(function () {
                                                    $usuario = Auth::user();

                                                    $almacenesIds = \DB::table('almacenes_users')
                                                        ->where('user_id', $usuario->id)
                                                        ->pluck('almacen_id');

                                                    $almacenes = $almacenesIds->isNotEmpty()
                                                        ? AlmacenIntermedio::whereIn('id', $almacenesIds)->get()
                                                        : AlmacenIntermedio::all();

                                                    return $almacenes->mapWithKeys(function ($almacen) {
                                                        $label = "{$almacen->referencia} ({$almacen->monte_parcela}, {$almacen->ayuntamiento})";
                                                        return [$almacen->id => mb_convert_encoding($label, 'UTF-8', 'UTF-8')];
                                                    });
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->required(fn(callable $get) => $get('eleccion') === 'almacen_intermedio')
                                                ->visible(fn(callable $get) => $get('eleccion') === 'almacen_intermedio'),

                                            Select::make('tipo_biomasa')
                                                ->options([
                                                    'pino' => 'Pino',
                                                    'eucalipto' => 'Eucalipto',
                                                    'acacia' => 'Acacia',
                                                    'frondosa' => 'Frondosa',
                                                    'mezcla' => 'Mezcla',
                                                    'otros' => 'Otros',
                                                ])
                                                ->multiple()
                                                ->searchable()
                                                ->required(),

                                            TextInput::make('peso_neto')
                                                ->label('Peso neto (Tn)')
                                                ->numeric()
                                                ->required()
                                                ->minValue(0)
                                                ->maxValue(99)
                                                ->step(0.01),

                                            TextInput::make('cantidad_total')
                                                ->label('Cantidad total (m³)')
                                                ->numeric()
                                                ->disabled()
                                                ->helperText('Valor calculado automáticamente.')
                                                ->default(fn($record) => $record?->cargas?->sum('cantidad') ?? 0),

                                            FileUpload::make('albaran')
                                                ->label('Foto del ticket de pesada')
                                                ->disk('public')
                                                ->directory('albaranes')
                                                ->required(fn(callable $get) => $get('eleccion') === 'cliente')
                                                ->visible(fn(callable $get) => $get('eleccion') === 'cliente'),

                                            FileUpload::make('carta_porte')
                                                ->label('Carta de porte')
                                                ->disk('public')
                                                ->directory('albaranes')
                                                ->required(fn(callable $get) => $get('eleccion') === 'almacen_intermedio')
                                                ->visible(fn(callable $get) => $get('eleccion') === 'almacen_intermedio'),

                                            Textarea::make('observaciones')
                                                ->rows(4)
                                                ->maxLength(1000),
                                        ])
                                        ->action(function (array $data, $record) {
                                            $record->update([
                                                'cliente_id' => $data['eleccion'] === 'cliente' ? $data['cliente_id'] : null,
                                                'almacen_id' => $data['eleccion'] === 'almacen_intermedio' ? $data['almacen_id'] : null,
                                                'tipo_biomasa' => $data['tipo_biomasa'],
                                                'peso_neto' => $data['peso_neto'],
                                                'albaran' => $data['albaran'] ?? null,
                                                'carta_porte' => $data['carta_porte'] ?? null,
                                                'observaciones' => $data['observaciones'] ?? null,
                                            ]);

                                            Notification::make()
                                                ->success()
                                                ->title('Descarga registrada correctamente')
                                                ->send();

                                            return redirect('/partes-trabajo-suministro-transporte');
                                        })
                                        ->color('primary');
                                }

                                return [
                                    Actions::make($acciones)
                                ];
                            }),
                    ])
                    ->columns(1),

                Section::make('Datos de la descarga')
                    ->visible(fn($record) => $record && ($record->cliente_id !== null || $record->almacen_id !== null))
                    ->schema([
                        Select::make('cliente_id')
                            ->label('Cliente')
                            ->options(fn() => \App\Models\Cliente::pluck('razon_social', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn($record) => $record?->cliente_id !== null),

                        Select::make('almacen_id')
                            ->label('Almacén intermedio')
                            ->options(fn() => AlmacenIntermedio::pluck('referencia', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn($record) => $record?->almacen_id !== null),

                        Select::make('tipo_biomasa')
                            ->label('Tipo de biomasa')
                            ->options([
                                'pino' => 'Pino',
                                'eucalipto' => 'Eucalipto',
                                'acacia' => 'Acacia',
                                'frondosa' => 'Frondosa',
                                'mezcla' => 'Mezcla',
                                'otros' => 'Otros',
                            ])
                            ->multiple()
                            ->required(),

                        TextInput::make('peso_neto')
                            ->label('Peso neto (Tn)')
                            ->numeric()
                            ->required(),

                        TextInput::make('cantidad_total')
                            ->label('Cantidad total (m³)')
                            ->numeric()
                            ->required(),

                        FileUpload::make('albaran')
                            ->label('Foto del ticket de pesada')
                            ->disk('public')
                            ->directory('albaranes')
                            ->imageEditor()
                            ->openable()
                            ->required()
                            ->columnSpanFull()
                            ->visible(fn($record) => $record?->cliente_id !== null),

                        FileUpload::make('carta_porte')
                            ->label('Carta de porte')
                            ->disk('public')
                            ->directory('albaranes')
                            ->imageEditor()
                            ->openable()
                            ->required()
                            ->columnSpanFull()
                            ->visible(fn($record) => $record?->cliente_id !== null),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),

                Section::make('Observaciones')
                    ->visible(fn($record) => $record && $record->cliente_id !== null)
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

                TextColumn::make('camion')
                    ->label('Camión')
                    ->formatStateUsing(function ($state, $record) {
                        $marca = $record->camion?->marca ?? '';
                        $modelo = $record->camion?->modelo ?? '';
                        $matricula_cabeza = $record->camion?->matricula_cabeza ?? '';
                        return trim("[$matricula_cabeza] - $marca $modelo");
                    }),

                TextColumn::make('cantidad_total')
                    ->label('Cantidad total (m³)'),

                TextColumn::make('cargas_totales')
                    ->label('Cargas')
                    ->html(),

                TextColumn::make('id')
                    ->label('Destino')
                    ->formatStateUsing(function ($record) {
                        if ($record->cliente && $record->cliente->razon_social) {
                            return $record->cliente->razon_social;
                        }

                        if ($record->almacen && $record->almacen->referencia) {
                            return $record->almacen->referencia . ' (' . $record->almacen->ayuntamiento . ', ' . $record->almacen->monte_parcela . ')';
                        }

                        return '-';
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
            ->paginated(true)
            ->paginationPageOptions([15, 30, 50])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationGroup::make('Cargas', [
                CargasRelationManager::class,
            ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParteTrabajoSuministroTransportes::route('/'),
            'create' => Pages\CreateParteTrabajoSuministroTransporte::route('/create'),
            'view' => Pages\ViewParteTrabajoSuministroTransporte::route('/{record}'),
            'edit' => Pages\EditParteTrabajoSuministroTransporte::route('/{record}/edit'),
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

    public static function hasCargasActions($record): bool
    {
        if (!$record || ($record->cliente_id && $record->albaran)) {
            return false;
        }

        $ultimaCarga = $record->cargas()->latest()->first();

        return
            !$ultimaCarga || $ultimaCarga->fecha_hora_fin_carga ||

            ($ultimaCarga && !$ultimaCarga->fecha_hora_fin_carga) ||

            ($ultimaCarga && $ultimaCarga->fecha_hora_fin_carga);
    }
}
