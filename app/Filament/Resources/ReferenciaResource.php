<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReferenciaResource\Pages;
use App\Filament\Resources\ReferenciaResource\Pages\ListReferencias;
use App\Filament\Resources\ReferenciaResource\RelationManagers;
use App\Models\Cliente;
use App\Models\Poblacion;
use App\Models\Proveedor;
use App\Models\Provincia;
use App\Models\Referencia;
use App\Models\User;
use Arr;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\View;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Panel;
use Jenssegers\Agent\Agent;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Radio;

class ReferenciaResource extends Resource
{
    protected static ?string $model = Referencia::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Parcelas';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'referencias';
    protected static ?string $pluralLabel = 'Referencias';
    protected static ?string $label = 'referencia';

    public static function form(Form $form): Form
    {
        // --- Helpers para construir referencia de forma consistente ---
        $certMap = [
            'sure_induestrial' => 'SI',
            'sure_foresal' => 'SF',
            'pefc' => 'PF',
            'sbp' => 'SB',
        ];

        /**
         * Devuelve el prefijo (todo menos el contador final de 2 dígitos)
         */
        $buildPrefix = function (callable $get) use ($certMap): string {
            $sector = (string) ($get('sector') ?? '01');
            $fecha = (string) ($get('ref_fecha_fija') ?? now()->format('dmy'));

            // Detectar SU vs Servicio por campos reales:
            $esSU = filled($get('formato')) && blank($get('tipo_servicio'));

            if ($esSU) {
                $formato = (string) ($get('formato') ?? 'CA');

                $mid = 'NO';
                // si tienes checkbox/flag de certificable, úsalo aquí
                if ($get('tipo_certificacion')) {
                    $tc = $get('tipo_certificacion');
                    if (isset($certMap[$tc])) {
                        $mid = $certMap[$tc];
                    }
                }

                // SECTOR + SU + FORMATO + MID + FECHA
                return "{$sector}SU{$formato}{$mid}{$fecha}";
            }

            // Servicio
            $prov = strtoupper(substr((string) ($get('provincia') ?? ''), 0, 2));
            $ayto = strtoupper(substr((string) ($get('ayuntamiento') ?? ''), 0, 2));

            // Iniciales cliente (o NO)
            $inic = 'NO';
            if ($clienteId = $get('cliente_id')) {
                $razon = optional(\App\Models\Cliente::find($clienteId))->razon_social;
                if ($razon) {
                    $slug = strtoupper(preg_replace('/[^A-Z]/i', '', $razon));
                    $inic = substr($slug, 0, 2) ?: 'NO';
                }
            }

            // SECTOR + PROV + AYTO + INIC + FECHA
            return "{$sector}{$prov}{$ayto}{$inic}{$fecha}";
        };

        /**
         * Setea referencia única preservando el contador actual si es posible.
         * Excluye el propio registro al comprobar unicidad.
         */
        $setUniqueRef = function (callable $set, callable $get) use ($buildPrefix) {
            $prefix = $buildPrefix($get);

            $current = (string) ($get('referencia') ?? '');
            // intenta recuperar el contador actual (dos dígitos al final)
            $contadorActual = null;
            if (preg_match('/(\d{2})$/', $current, $m)) {
                $contadorActual = (int) $m[1];
            }

            $id = $get('id'); // Hidden que añadimos

            // 1) intenta con el contador actual
            if ($contadorActual !== null) {
                $try = $prefix . str_pad((string) $contadorActual, 2, '0', STR_PAD_LEFT);
                $exists = \App\Models\Referencia::where('referencia', $try)
                    ->when($id, fn($q) => $q->where('id', '<>', $id))
                    ->exists();
                if (!$exists) {
                    $set('referencia', $try);
                    return;
                }
            }

            // 2) busca el siguiente libre
            $i = 1;
            do {
                $contador = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $ref = $prefix . $contador;

                $exists = \App\Models\Referencia::where('referencia', $ref)
                    ->when($id, fn($q) => $q->where('id', '<>', $id))
                    ->exists();

                $i++;
            } while ($exists);

            $set('referencia', $ref);
        };
        // ----------------------------------------------------------------

        return $form
            ->schema([
                // Fecha fija (no se guarda en BD)
                Forms\Components\Hidden::make('ref_fecha_fija')
                    ->dehydrated(false)
                    ->default(now()->format('dmy')),

                View::make('livewire.tipo-select')
                    ->visible(fn($state) => !isset($state['id']))
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('referencia')
                    ->required()
                    ->reactive()
                    ->columnSpanFull()
                    ->afterStateHydrated(function (callable $set, $state, ?\App\Models\Referencia $record) {
                        $ref = (string) ($state ?: ($record->referencia ?? ''));
                        if (preg_match('/(\d{6})/', $ref, $m)) {
                            $set('ref_fecha_fija', $m[1]); // ej: 110725
                        } else {
                            $set('ref_fecha_fija', now()->format('dmy'));
                        }
                    }),

                Forms\Components\Select::make('tipo_servicio')
                    ->required()
                    ->searchable()
                    ->options([
                        'Astillado Suelo' => 'Astillado Suelo',
                        'Astillado Camión' => 'Astillado Camión',
                        'Triturado Suelo' => 'Triturado Suelo',
                        'Triturado Camión' => 'Triturado Camión',
                        'Saca autocargador' => 'Saca autocargador',
                    ])
                    ->columnSpanFull()
                    ->visible(fn($get) => !empty($get('referencia')) && strpos((string) $get('referencia'), 'SU') === false),

                // FORMATO (solo SU): inserta el código de certificación entre formato y fecha
                Forms\Components\Select::make('formato')
                    ->nullable()
                    ->options([
                        'CA' => 'Cargadero',
                        'SA' => 'Saca',
                        'EX' => 'Explotación',
                        'OT' => 'Otros',
                    ])
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpanFull()
                    ->live()
                    ->afterStateUpdated(fn($state, $set, $get) => $setUniqueRef($set, $get))
                    ->visible(fn($get) => str_contains((string) $get('referencia'), 'SU')),

                Forms\Components\Section::make('Ubicación')
                    ->schema([
                        Forms\Components\TextInput::make('provincia')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn($state, $set, $get) => $setUniqueRef($set, $get)),

                        Forms\Components\TextInput::make('ayuntamiento')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn($state, $set, $get) => $setUniqueRef($set, $get)),

                        Forms\Components\TextInput::make('monte_parcela')
                            ->label('Monte / Parcela')
                            ->required(),

                        Forms\Components\TextInput::make('ubicacion_gps')
                            ->label('GPS'),

                        Forms\Components\TextInput::make('finca')
                            ->label('Finca')
                            ->visible(fn($get) => !empty($get('referencia')) && strpos((string) $get('referencia'), 'SU') === false)
                            ->columnSpanFull(),

                        // SECTOR: siempre prefijo delante (SU y servicio)
                        Forms\Components\Select::make('sector')
                            ->label('Sector')
                            ->searchable()
                            ->options([
                                '01' => 'Zona Norte Galicia',
                                '02' => 'Zona Sur Galicia',
                                '03' => 'Andalucía Oriental',
                                '04' => 'Andalucía Occidental',
                                '05' => 'Otros',
                            ])
                            ->columnSpanFull()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn($state, $set, $get) => $setUniqueRef($set, $get))
                            ->visible(fn($get) => !empty($get('referencia'))),

                        View::make('livewire.get-location-button')
                            ->visible(fn($state) => !isset($state['id']))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Intervinientes')
                    ->schema([
                        Forms\Components\Select::make('proveedor_id')
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->relationship(
                                'proveedor',
                                'razon_social',
                                fn(Builder $query) => $query->whereIn('tipo_servicio', ['suministro', 'servicios'])
                            )
                            ->visible(fn($get) => strpos((string) $get('referencia'), 'SU') !== false),

                        Forms\Components\Select::make('cliente_id')
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->relationship('cliente', 'razon_social')
                            ->afterStateUpdated(fn($state, $set, $get) => $setUniqueRef($set, $get))
                            ->visible(fn($get) => strpos((string) $get('referencia'), 'SU') === false),
                    ])
                    ->visible(fn($get) => !empty($get('referencia')))
                    ->columns(1),

                Forms\Components\Section::make('Producto')
                    ->schema([
                        Forms\Components\Select::make('producto_especie')
                            ->label('Especie')
                            ->searchable()
                            ->options([
                                'pino' => 'Pino',
                                'eucalipto' => 'Eucalipto',
                                'acacia' => 'Acacia',
                                'frondosa' => 'Frondosa',
                                'otros' => 'Otros',
                            ])
                            ->required(),

                        Forms\Components\Select::make('producto_tipo')
                            ->label('Tipo')
                            ->searchable()
                            ->options([
                                'entero' => 'Entero',
                                'troza' => 'Troza',
                                'tacos' => 'Tacos',
                                'puntal' => 'Puntal',
                                'copas' => 'Copas',
                                'rama' => 'Rama',
                                'raices' => 'Raíces',
                            ])
                            ->required(),

                        Select::make('tipo_cantidad')
                            ->label('Tipo de cantidad')
                            ->options([
                                'toneladas' => 'Toneladas',
                                'camiones' => 'Camiones',
                            ])
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('cantidad_aprox')
                            ->label('Cantidad (aprox.)')
                            ->numeric()
                            ->required(),

                        Forms\Components\Select::make('tipo_certificacion')
                            ->label('Tipo de certificación')
                            ->searchable()
                            ->options([
                                'sure_induestrial' => 'SURE - Industrial',
                                'sure_foresal' => 'SURE - Forestal',
                                'sbp' => 'SBP',
                                'pefc' => 'PEFC',
                            ])
                            ->reactive()
                            ->afterStateUpdated(fn($state, $set, $get) => $setUniqueRef($set, $get)),

                        Forms\Components\Checkbox::make('guia_sanidad')
                            ->label('¿Guía de sanidad?')
                            ->reactive(),
                    ])
                    ->columns(2)
                    ->visible(fn($get) => !empty($get('referencia'))),

                Forms\Components\Section::make('Tarifa')
                    ->schema([
                        Forms\Components\Section::make('')
                            ->schema([
                                Forms\Components\Select::make('tarifa')
                                    ->label('Tarifa')
                                    ->options([
                                        'toneladas' => 'Toneladas',
                                        'm3' => 'Metros cúbicos',
                                        'hora' => 'Hora',
                                    ])
                                    ->searchable()
                                    ->nullable()
                                    ->reactive(),

                                Forms\Components\TextInput::make('precio')
                                    ->label(fn(callable $get) => match ($get('tarifa')) {
                                        'toneladas' => 'Precio por tonelada',
                                        'm3' => 'Precio por m³',
                                        'hora' => 'Precio por hora',
                                        default => 'Precio',
                                    })
                                    ->numeric()
                                    ->nullable()
                                    ->reactive()
                                    ->suffix(fn(callable $get) => match ($get('tarifa')) {
                                        'toneladas' => '€/tonelada',
                                        'm3' => '€/m³',
                                        'hora' => '€/hora',
                                        default => '€',
                                    }),
                            ]),
                    ])
                    ->columns(3)
                    ->visible(fn($get) => !empty($get('referencia'))),

                Forms\Components\Section::make('Contacto')
                    ->schema([
                        Forms\Components\TextInput::make('contacto_nombre')
                            ->label('Nombre')
                            ->nullable(),
                        Forms\Components\TextInput::make('contacto_telefono')
                            ->label('Teléfono')
                            ->nullable(),
                        Forms\Components\TextInput::make('contacto_email')
                            ->label('Correo electrónico')
                            ->nullable(),
                    ])->columns(3)
                    ->visible(fn($get) => !empty($get('referencia'))),

                Section::make('Usuarios')
                    ->schema([
                        Forms\Components\Select::make('usuarios')
                            ->label('Usuarios relacionados')
                            ->multiple()
                            ->relationship(
                                name: 'usuarios',
                                titleAttribute: 'name',
                                modifyQueryUsing: function ($query, $get) {
                                    $query->orderBy('name')
                                        ->whereNull('users.deleted_at')
                                        ->whereDoesntHave(
                                            'roles',
                                            fn($q) => $q->where('name', 'superadmin')
                                        );
                                }
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn($record) =>
                                $record?->nombre_apellidos .
                                ($record?->proveedor_id ? ' (' . optional($record->proveedor)->razon_social . ')' : ' (Bioforga)') ?? '-'
                            )
                            ->preload()
                            ->searchable()
                            ->columnSpanFull()
                            ->visible(fn($get) => !empty($get('referencia'))),
                    ])
                    ->visible(fn($get) => !empty($get('referencia'))),

                Forms\Components\Section::make('Estado')
                    ->schema([
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->searchable()
                            ->options([
                                'abierto' => 'Abierto',
                                'en_proceso' => 'En proceso',
                                'cerrado' => 'Cerrado',
                                'cerrado_no_procede' => 'Cerrado no procede',
                            ])
                            ->required(),

                        Forms\Components\Select::make('en_negociacion')
                            ->label('En negociación')
                            ->searchable()
                            ->options([
                                'confirmado' => 'Confirmado',
                                'sin_confirmar' => 'Sin confirmar',
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('observaciones')
                            ->nullable(),
                    ])->columns(1)
                    ->visible(fn($get) => !empty($get('referencia'))),
            ]);
    }

    public static function table(Table $table): Table
    {

        $agent = new Agent();

        if ($agent->isMobile()) {
            return $table
                ->modifyQueryUsing(fn($query) => $query->withCount('usuarios'))
                ->columns([
                    Tables\Columns\IconColumn::make('tiene_usuarios')
                        ->label('')
                        ->alignCenter()
                        ->state(fn($record) => ($record->usuarios_count ?? 0) > 0)
                        ->boolean()
                        ->trueIcon('heroicon-m-user-group')
                        ->falseIcon('heroicon-m-user')
                        ->color(fn(bool $state) => $state ? 'success' : 'gray')
                        ->tooltip(
                            fn($record) => ($record->usuarios_count ?? 0) > 0
                            ? "{$record->usuarios_count} usuario(s) vinculados"
                            : 'Sin usuarios vinculados'
                        ),

                    TextColumn::make('referencia')
                        ->label('Referencia')
                        ->weight(FontWeight::Bold)
                        ->searchable(),

                    Panel::make([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Stack::make([
                                    TextColumn::make('ayuntamiento')
                                        ->label('Municipio (Monte)')
                                        ->icon('heroicon-m-map-pin')
                                        ->formatStateUsing(function ($state, $record) {
                                            $monte = $record->monte_parcela;
                                            return $state . ($monte ? " ($monte)" : '');
                                        })
                                        ->searchable(
                                            query: function ($query, string $search) {
                                                $query->where(function ($q) use ($search) {
                                                    $q->where('monte_parcela', 'like', "%{$search}%")
                                                        ->orWhere('provincia', 'like', "%{$search}%");
                                                });
                                            }
                                        ),

                                    TextColumn::make('interviniente')
                                        ->label('Interviniente')
                                        ->icon('heroicon-m-building-office'),

                                    TextColumn::make('cantidad_aprox')
                                        ->label('Cantidad (aprox.)')
                                        ->formatStateUsing(function ($record) {
                                            if ($record->cantidad_aprox === null) {
                                                return null;
                                            }

                                            // Formatear número: 2 decimales, ',' como decimal y '.' como miles
                                            $cantidad = number_format($record->cantidad_aprox, 2, ',', '.');

                                            // Añadir tipo_cantidad si existe
                                            if (!empty($record->tipo_cantidad)) {
                                                return $cantidad . ' ' . $record->tipo_cantidad;
                                            }

                                            return $cantidad;
                                        }),
                                ]),
                            ]),
                    ])->collapsed(false),
                ])
                ->persistFiltersInSession()
                ->filters(
                    [
                        Filter::make('fecha_creacion')
                            ->label('Fecha de creación')
                            ->form([
                                DatePicker::make('desde')->label('Fecha de creación - Desde'),
                                DatePicker::make('hasta')->label('Fecha de creación - Hasta'),
                            ])
                            ->columns(2)
                            ->query(function ($query, array $data) {
                                if (!empty($data['desde'])) {
                                    $query->whereDate('created_at', '>=', $data['desde']);
                                }

                                if (!empty($data['hasta'])) {
                                    $query->whereDate('created_at', '<=', $data['hasta']);
                                }

                                return $query;
                            })
                            ->columnSpanFull(),

                        SelectFilter::make('usuario')
                            ->label('Usuario')
                            ->multiple()
                            ->searchable()
                            ->placeholder('Seleccionar usuario')
                            ->options(
                                User::whereDoesntHave('roles', fn($q) => $q->where('name', 'superadmin'))
                                    ->select('id', \DB::raw("CONCAT(name, ' ', apellidos) as full_name"))
                                    ->orderBy('name')
                                    ->pluck('full_name', 'id')
                                    ->toArray()
                            )
                            ->query(function ($query, array $data) {
                                if (!empty($data['values']) && is_array($data['values'])) {
                                    $query->whereHas('usuarios', function ($q) use ($data) {
                                        $q->whereIn('users.id', $data['values']);
                                    });
                                }
                            }),

                        SelectFilter::make('tipo_referencia')
                            ->label('Tipo de referencia')
                            ->options([
                                'suministro' => 'Suministro',
                                'servicio' => 'Servicio',
                            ])
                            ->query(function ($query, array $data) {
                                if ($data['value'] === 'suministro') {
                                    return $query->whereNotNull('formato')->whereNull('tipo_servicio');
                                }

                                if ($data['value'] === 'servicio') {
                                    return $query->whereNull('formato')->whereNotNull('tipo_servicio');
                                }

                                return $query;
                            })
                            ->searchable()
                            ->placeholder('Todos'),

                        SelectFilter::make('formato')
                            ->label('Formato')
                            ->options([
                                'CA' => 'Cargador',
                                'SA' => 'Saca',
                                'EX' => 'Explotación',
                                'OT' => 'Otros',
                            ])
                            ->searchable()
                            ->placeholder('Todos')
                            ->query(function ($query, array $data) {
                                $formato = $data['value'] ?? null;

                                if ($formato) {
                                    return $query->where('formato', $formato);
                                }

                                return $query;
                            }),

                        SelectFilter::make('sector')
                            ->label('Sector')
                            ->multiple()
                            ->searchable()
                            ->options([
                                '01' => 'Zona Norte Galicia',
                                '02' => 'Zona Sur Galicia',
                                '03' => 'Andalucía Oriental',
                                '04' => 'Andalucía Occidental',
                                '05' => 'Otros',
                            ])
                            ->query(function ($query, array $data) {
                                if (!empty($data['values'])) {
                                    return $query->whereIn('sector', $data['values']);
                                }

                                return $query;
                            })
                            ->placeholder('Todos'),

                        // Sustituye tu SelectFilter::make('interviniente') por:
                        Filter::make('interviniente')
                            ->label('Tipo de interviniente')
                            ->form([
                                Radio::make('tipo')
                                    ->label('Tipo de interviniente')
                                    ->options([
                                        'proveedor' => 'Proveedor',
                                        'cliente' => 'Cliente',
                                    ])
                                    ->inline()
                                    ->reactive(),

                                Select::make('id')
                                    ->label('Interviniente')
                                    ->searchable()
                                    ->preload()
                                    ->options(function (Get $get) {
                                        if ($get('tipo') === 'proveedor') {
                                            return \App\Models\Proveedor::query()
                                                ->where(function ($q) {
                                                    $q->whereNull('tipo_servicio')
                                                        ->orWhereNotIn('tipo_servicio', ['Logística', 'logistica', 'LOGÍSTICA']);
                                                })
                                                ->orderBy('razon_social')
                                                ->pluck('razon_social', 'id')
                                                ->toArray();
                                        }
                                        if ($get('tipo') === 'cliente') {
                                            return \App\Models\Cliente::query()
                                                ->orderBy('razon_social')
                                                ->pluck('razon_social', 'id')
                                                ->toArray();
                                        }
                                        return []; // sin tipo seleccionado
                                    })
                                    ->disabled(fn(Get $get) => blank($get('tipo'))),
                            ])
                            ->query(function (Builder $query, array $data) {
                                $tipo = $data['tipo'] ?? null;
                                $id = $data['id'] ?? null;

                                if ($tipo === 'proveedor' && $id) {
                                    $query->where('proveedor_id', $id);
                                } elseif ($tipo === 'cliente' && $id) {
                                    $query->where('cliente_id', $id);
                                }
                            })
                            ->indicateUsing(function (array $data) {
                                if (!($data['tipo'] ?? null) || !($data['id'] ?? null)) {
                                    return null;
                                }

                                $etiqueta = match ($data['tipo']) {
                                    'proveedor' => optional(\App\Models\Proveedor::find($data['id']))?->razon_social,
                                    'cliente' => optional(\App\Models\Cliente::find($data['id']))?->razon_social,
                                    default => null,
                                };

                                return $etiqueta ? "Interviniente: {$etiqueta}" : null;
                            }),

                        SelectFilter::make('estado')
                            ->label('Estado')
                            ->searchable()
                            ->options([
                                'abierto' => 'Abierto',
                                'en_proceso' => 'En proceso',
                                'cerrado' => 'Cerrado',
                                'cerrado_no_procede' => 'Cerrado no procede',
                            ])
                            ->query(function ($query, array $data) {
                                if (!empty($data['value'])) {
                                    return $query->where('estado', $data['value']);
                                }

                                return $query;
                            })
                            ->placeholder('Todos'),

                        SelectFilter::make('provincia_id')
                            ->label('Provincia')
                            ->multiple()
                            ->searchable()
                            ->placeholder('Todas')
                            ->options(
                                fn() =>
                                Provincia::query()
                                    ->whereNull('deleted_at') // por SoftDeletes
                                    ->orderBy('nombre')
                                    ->pluck('nombre', 'id')
                                    ->toArray()
                            )
                            ->query(function ($query, array $data) {
                                if (!empty($data['values'])) {
                                    $query->whereIn('provincia_id', $data['values']);
                                }
                                return $query;
                            })
                            ->indicateUsing(function (array $data) {
                                if (empty($data['values']))
                                    return null;
                                $n = count($data['values']);
                                if ($n === 1) {
                                    $nombre = Provincia::withTrashed()->find($data['values'][0])?->nombre;
                                    return $nombre ? "Provincia: {$nombre}" : '1 provincia';
                                }
                                return "{$n} provincias";
                            }),

                        SelectFilter::make('poblacion_id')
                            ->label('Municipio')
                            ->multiple()
                            ->searchable()
                            ->placeholder('Todos')
                            ->options(function () {
                                // lee lo seleccionado en el filtro de provincia
                                $provinciasSeleccionadas = request()->input('tableFilters.provincia_id.values', []);

                                $q = Poblacion::query()
                                    ->whereNull('deleted_at') // por SoftDeletes
                                    ->orderBy('nombre');

                                if (!empty($provinciasSeleccionadas)) {
                                    $q->whereIn('provincia_id', $provinciasSeleccionadas);
                                }

                                return $q->pluck('nombre', 'id')->toArray();
                            })
                            ->query(function ($query, array $data) {
                                if (!empty($data['values'])) {
                                    $query->whereIn('poblacion_id', $data['values']);
                                }
                                return $query;
                            })
                            ->indicateUsing(function (array $data) {
                                if (empty($data['values']))
                                    return null;
                                $n = count($data['values']);
                                return $n === 1 ? '1 municipio' : "{$n} municipios";
                            }),
                    ],
                    layout: FiltersLayout::AboveContent
                )
                ->filtersFormColumns(2)
                ->headerActions([
                    Action::make('exportar_balance_masas')
                        ->label('Balance de Masas')
                        ->icon('heroicon-m-document-arrow-down')
                        ->color('gray')
                        ->modalWidth('md')
                        ->modalHeading('Selecciona el periodo para exportar')
                        ->modalSubmitActionLabel('Exportar')
                        ->form([
                            DatePicker::make('fecha_inicio')
                                ->label('Fecha inicio')
                                ->default(now()->subDays(30)->startOfDay())
                                ->required(),
                            DatePicker::make('fecha_fin')
                                ->label('Fecha fin')
                                ->default(now()->endOfDay())
                                ->required(),
                        ])
                        ->action(function (array $data) {
                            $fechaInicio = $data['fecha_inicio'];
                            $fechaFin = $data['fecha_fin'];

                            $hayDatos = \App\Models\CargaTransporte::whereBetween('fecha_hora_inicio_carga', [
                                $fechaInicio,
                                $fechaFin,
                            ])->exists();

                            if (!$hayDatos) {
                                Notification::make()
                                    ->title('Sin datos')
                                    ->body('No hay cargas registradas en el periodo seleccionado.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $filename = 'balance-de-masas-' . now()->format('Y-m-d') . '.xlsx';
                            return \Maatwebsite\Excel\Facades\Excel::download(
                                new \App\Exports\BalanceDeMasasExport($fechaInicio, $fechaFin),
                                $filename
                            );
                        })
                        ->after(function () {
                            Notification::make()
                                ->title('Exportación iniciada')
                                ->body('El archivo "Balance de Masas" se está descargando.')
                                ->success()
                                ->send();
                        }),

                    Action::make('exportar_excel')
                        ->label('Exportar a Excel')
                        ->color('gray')
                        ->modalWidth('xl')
                        ->modalSubmitActionLabel('Exportar')
                        ->modalCancelActionLabel('Cerrar')
                        ->form([
                            Select::make('tipo_exportacion')
                                ->label('Selecciona una opción')
                                ->options([
                                    'suministro' => 'Suministro',
                                    'servicio' => 'Servicio',
                                ])
                                ->searchable()
                                ->required(),

                            TextInput::make('nombre_archivo')
                                ->label('Nombre del archivo')
                                ->default('referencias-' . now()->format('Y-m-d'))
                                ->required(),
                        ])
                        ->action(function (array $data) {
                            $tipo = $data['tipo_exportacion'];
                            $nombre = $data['nombre_archivo'] . '.xlsx';

                            return redirect()->route('referencias.export', [
                                'tipo' => $tipo,
                                'nombre' => $nombre,
                            ]);
                        })
                ])
                ->actions([
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
        } else {
            return $table
                ->modifyQueryUsing(fn($query) => $query->withCount('usuarios'))
                ->columns([
                    Tables\Columns\IconColumn::make('tiene_usuarios')
                        ->label('')
                        ->alignCenter()
                        ->state(fn($record) => ($record->usuarios_count ?? 0) > 0)
                        ->boolean()
                        ->trueIcon('heroicon-m-user-group')
                        ->falseIcon('heroicon-m-user')
                        ->color(fn(bool $state) => $state ? 'success' : 'gray')
                        ->tooltip(
                            fn($record) => ($record->usuarios_count ?? 0) > 0
                            ? "{$record->usuarios_count} usuario(s) vinculados"
                            : 'Sin usuarios vinculados'
                        ),

                    TextColumn::make('referencia')
                        ->label('Referencia')
                        ->weight(FontWeight::Bold)
                        ->searchable(),

                    TextColumn::make('ayuntamiento')
                        ->label('Municipio (Monte)')
                        ->icon('heroicon-m-map-pin')
                        ->formatStateUsing(function ($state, $record) {
                            $monte = $record->monte_parcela;
                            return $state . ($monte ? " ($monte)" : '');
                        })
                        ->searchable(
                            query: function ($query, string $search) {
                                $query->where(function ($q) use ($search) {
                                    $q->where('monte_parcela', 'like', "%{$search}%")
                                        ->orWhere('provincia', 'like', "%{$search}%");
                                });
                            }
                        ),

                    TextColumn::make('interviniente')
                        ->label('Interviniente')
                        ->icon('heroicon-m-building-office'),

                    TextColumn::make('cantidad_aprox')
                        ->label('Cantidad (aprox.)')
                        ->formatStateUsing(function ($record) {
                            if ($record->cantidad_aprox === null) {
                                return null;
                            }

                            // Formatear número: 2 decimales, ',' como decimal y '.' como miles
                            $cantidad = number_format($record->cantidad_aprox, 2, ',', '.');

                            // Añadir tipo_cantidad si existe
                            if (!empty($record->tipo_cantidad)) {
                                return $cantidad . ' ' . $record->tipo_cantidad;
                            }

                            return $cantidad;
                        }),

                    TextColumn::make('estado')
                        ->label('Estado')
                        ->badge()
                        ->sortable()
                        ->color(fn($state) => match ($state) {
                            'abierto' => 'success',
                            'en_proceso' => 'warning',
                            'cerrado' => 'danger',
                            'cerrado_no_procede' => 'danger',
                            default => 'secondary',
                        })
                        ->formatStateUsing(fn($state) => match ($state) {
                            'abierto' => 'Abierto',
                            'en_proceso' => 'En proceso',
                            'cerrado' => 'Cerrado',
                            'cerrado_no_procede' => 'Cerrado',
                            default => ucfirst($state ?? 'Desconocido'),
                        }),

                    TextColumn::make('estado_facturacion')
                        ->label('Facturación')
                        ->badge()
                        ->formatStateUsing(fn($state) => match ($state) {
                            'completa' => 'Completa',
                            'parcial' => 'Parcial',
                            'no_facturada' => 'No facturada',
                            'no_procede' => 'No procede'
                        })
                        ->color(fn(string $state) => match ($state) {
                            'completa' => 'success',
                            'parcial' => 'warning',
                            'no_facturada' => 'gray',
                            'no_procede' => 'danger'
                        }),
                ])
                ->persistFiltersInSession()
                ->filters(
                    [
                        Filter::make('fecha_creacion')
                            ->label('Fecha de creación')
                            ->form([
                                DatePicker::make('desde')->label('Fecha de creación - Desde'),
                                DatePicker::make('hasta')->label('Fecha de creación - Hasta'),
                            ])
                            ->columns(2)
                            ->query(function ($query, array $data) {
                                if (!empty($data['desde'])) {
                                    $query->whereDate('created_at', '>=', $data['desde']);
                                }

                                if (!empty($data['hasta'])) {
                                    $query->whereDate('created_at', '<=', $data['hasta']);
                                }

                                return $query;
                            })
                            ->columnSpanFull(),

                        SelectFilter::make('usuario')
                            ->label('Usuario')
                            ->multiple()
                            ->searchable()
                            ->placeholder('Seleccionar usuario')
                            ->options(
                                User::whereDoesntHave('roles', fn($q) => $q->where('name', 'superadmin'))
                                    ->select('id', \DB::raw("CONCAT(name, ' ', apellidos) as full_name"))
                                    ->orderBy('name')
                                    ->pluck('full_name', 'id')
                                    ->toArray()
                            )
                            ->query(function ($query, array $data) {
                                if (!empty($data['values']) && is_array($data['values'])) {
                                    $query->whereHas('usuarios', function ($q) use ($data) {
                                        $q->whereIn('users.id', $data['values']);
                                    });
                                }
                            }),

                        SelectFilter::make('tipo_referencia')
                            ->label('Tipo de referencia')
                            ->options([
                                'suministro' => 'Suministro',
                                'servicio' => 'Servicio',
                            ])
                            ->query(function ($query, array $data) {
                                if ($data['value'] === 'suministro') {
                                    return $query->whereNotNull('formato')->whereNull('tipo_servicio');
                                }

                                if ($data['value'] === 'servicio') {
                                    return $query->whereNull('formato')->whereNotNull('tipo_servicio');
                                }

                                return $query;
                            })
                            ->searchable()
                            ->placeholder('Todos'),

                        SelectFilter::make('formato')
                            ->label('Formato')
                            ->options([
                                'CA' => 'Cargador',
                                'SA' => 'Saca',
                                'EX' => 'Explotación',
                                'OT' => 'Otros',
                            ])
                            ->searchable()
                            ->placeholder('Todos')
                            ->query(function ($query, array $data) {
                                $formato = $data['value'] ?? null;

                                if ($formato) {
                                    return $query->where('formato', $formato);
                                }

                                return $query;
                            }),

                        SelectFilter::make('sector')
                            ->label('Sector')
                            ->multiple()
                            ->searchable()
                            ->options([
                                '01' => 'Zona Norte Galicia',
                                '02' => 'Zona Sur Galicia',
                                '03' => 'Andalucía Oriental',
                                '04' => 'Andalucía Occidental',
                                '05' => 'Otros',
                            ])
                            ->query(function ($query, array $data) {
                                if (!empty($data['values'])) {
                                    return $query->whereIn('sector', $data['values']);
                                }

                                return $query;
                            })
                            ->placeholder('Todos'),

                        // Sustituye tu SelectFilter::make('interviniente') por:
                        Filter::make('interviniente')
                            ->label('Tipo de interviniente')
                            ->form([
                                Radio::make('tipo')
                                    ->label('Tipo de interviniente')
                                    ->options([
                                        'proveedor' => 'Proveedor',
                                        'cliente' => 'Cliente',
                                    ])
                                    ->inline()
                                    ->reactive(),

                                Select::make('id')
                                    ->label('Interviniente')
                                    ->searchable()
                                    ->preload()
                                    ->options(function (Get $get) {
                                        if ($get('tipo') === 'proveedor') {
                                            return \App\Models\Proveedor::query()
                                                ->where(function ($q) {
                                                    $q->whereNull('tipo_servicio')
                                                        ->orWhereNotIn('tipo_servicio', ['Logística', 'logistica', 'LOGÍSTICA']);
                                                })
                                                ->orderBy('razon_social')
                                                ->pluck('razon_social', 'id')
                                                ->toArray();
                                        }
                                        if ($get('tipo') === 'cliente') {
                                            return \App\Models\Cliente::query()
                                                ->orderBy('razon_social')
                                                ->pluck('razon_social', 'id')
                                                ->toArray();
                                        }
                                        return []; // sin tipo seleccionado
                                    })
                                    ->disabled(fn(Get $get) => blank($get('tipo'))),
                            ])
                            ->query(function (Builder $query, array $data) {
                                $tipo = $data['tipo'] ?? null;
                                $id = $data['id'] ?? null;

                                if ($tipo === 'proveedor' && $id) {
                                    $query->where('proveedor_id', $id);
                                } elseif ($tipo === 'cliente' && $id) {
                                    $query->where('cliente_id', $id);
                                }
                            })
                            ->indicateUsing(function (array $data) {
                                if (!($data['tipo'] ?? null) || !($data['id'] ?? null)) {
                                    return null;
                                }

                                $etiqueta = match ($data['tipo']) {
                                    'proveedor' => optional(\App\Models\Proveedor::find($data['id']))?->razon_social,
                                    'cliente' => optional(\App\Models\Cliente::find($data['id']))?->razon_social,
                                    default => null,
                                };

                                return $etiqueta ? "Interviniente: {$etiqueta}" : null;
                            }),

                        SelectFilter::make('estado')
                            ->label('Estado')
                            ->searchable()
                            ->options([
                                'abierto' => 'Abierto',
                                'en_proceso' => 'En proceso',
                                'cerrado' => 'Cerrado',
                                'cerrado_no_procede' => 'Cerrado no procede',
                            ])
                            ->query(function ($query, array $data) {
                                if (!empty($data['value'])) {
                                    return $query->where('estado', $data['value']);
                                }

                                return $query;
                            })
                            ->placeholder('Todos'),

                        SelectFilter::make('provincia')
                            ->label('Provincia')
                            ->multiple()
                            ->searchable()
                            ->placeholder('Todas')
                            ->options(
                                fn() =>
                                Referencia::query()
                                    ->whereNotNull('provincia')
                                    ->distinct()
                                    ->orderBy('provincia')
                                    ->pluck('provincia', 'provincia')
                                    ->toArray()
                            )
                            ->query(function ($query, array $data) {
                                if (!empty($data['values'])) {
                                    $query->whereIn('provincia', $data['values']);
                                }
                                return $query;
                            })
                            ->indicateUsing(function (array $data) {
                                if (empty($data['values']))
                                    return null;
                                $n = count($data['values']);
                                return $n === 1 ? "Provincia: {$data['values'][0]}" : "{$n} provincias";
                            }),

                        SelectFilter::make('ayuntamiento')
                            ->label('Municipio')
                            ->multiple()
                            ->searchable()
                            ->placeholder('Todos')
                            ->options(function () {
                                // Provincias seleccionadas en el otro filtro
                                $provSel = request()->input('tableFilters.provincia.values', []);

                                $q = Referencia::query()
                                    ->whereNotNull('ayuntamiento');

                                if (!empty($provSel)) {
                                    $q->whereIn('provincia', $provSel);
                                }

                                return $q->distinct()
                                    ->orderBy('ayuntamiento')
                                    ->pluck('ayuntamiento', 'ayuntamiento')
                                    ->toArray();
                            })
                            ->query(function ($query, array $data) {
                                if (!empty($data['values'])) {
                                    $query->whereIn('ayuntamiento', $data['values']);
                                }
                                return $query;
                            })
                            ->indicateUsing(function (array $data) {
                                if (empty($data['values']))
                                    return null;
                                $n = count($data['values']);
                                return $n === 1 ? "Municipio: {$data['values'][0]}" : "{$n} municipios";
                            }),
                    ],
                    layout: FiltersLayout::AboveContent
                )
                ->filtersFormColumns(2)
                ->headerActions([
                    Action::make('exportar_balance_masas')
                        ->label('Balance de Masas')
                        ->icon('heroicon-m-document-arrow-down')
                        ->color('gray')
                        ->modalWidth('md')
                        ->modalHeading('Selecciona el periodo para exportar')
                        ->modalSubmitActionLabel('Exportar')
                        ->form([
                            DatePicker::make('fecha_inicio')
                                ->label('Fecha inicio')
                                ->default(now()->subDays(30)->startOfDay())
                                ->required(),
                            DatePicker::make('fecha_fin')
                                ->label('Fecha fin')
                                ->default(now()->endOfDay())
                                ->required(),
                        ])
                        ->action(function (array $data) {
                            $fechaInicio = $data['fecha_inicio'];
                            $fechaFin = $data['fecha_fin'];

                            $hayDatos = \App\Models\CargaTransporte::whereBetween('fecha_hora_inicio_carga', [
                                $fechaInicio,
                                $fechaFin,
                            ])->exists();

                            if (!$hayDatos) {
                                Notification::make()
                                    ->title('Sin datos')
                                    ->body('No hay cargas registradas en el periodo seleccionado.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $filename = 'balance-de-masas-' . now()->format('Y-m-d') . '.xlsx';
                            return \Maatwebsite\Excel\Facades\Excel::download(
                                new \App\Exports\BalanceDeMasasExport($fechaInicio, $fechaFin),
                                $filename
                            );
                        })
                        ->after(function () {
                            Notification::make()
                                ->title('Exportación iniciada')
                                ->body('El archivo "Balance de Masas" se está descargando.')
                                ->success()
                                ->send();
                        }),

                    Action::make('exportar_excel')
                        ->label('Exportar a Excel')
                        ->color('gray')
                        ->modalWidth('xl')
                        ->modalSubmitActionLabel('Exportar')
                        ->modalCancelActionLabel('Cerrar')
                        ->form([
                            Select::make('tipo_exportacion')
                                ->label('Selecciona una opción')
                                ->options([
                                    'suministro' => 'Suministro',
                                    'servicio' => 'Servicio',
                                ])
                                ->searchable()
                                ->required(),

                            TextInput::make('nombre_archivo')
                                ->label('Nombre del archivo')
                                ->default('referencias-' . now()->format('Y-m-d'))
                                ->required(),
                        ])
                        ->action(function (array $data) {
                            $tipo = $data['tipo_exportacion'];
                            $nombre = $data['nombre_archivo'] . '.xlsx';

                            return redirect()->route('referencias.export', [
                                'tipo' => $tipo,
                                'nombre' => $nombre,
                            ]);
                        })
                ])
                ->actions([
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
            'index' => Pages\ListReferencias::route('/'),
            'create' => Pages\CreateReferencia::route('/create'),
            'edit' => Pages\EditReferencia::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if ($user->hasRole('técnico')) {
            $sectores = array_filter(Arr::wrap($user->sector ?? []));
            if ($sectores) {
                return parent::getEloquentQuery()
                    ->whereIn('sector', $sectores);
            }

            // si no tiene sectores asignados, que no vea nada (opcional)
            // return parent::getEloquentQuery()->whereRaw('1=0');
        }

        return parent::getEloquentQuery();
    }

    public static function generalFormSchema(): array
    {
        // --- Helpers para construir referencia de forma consistente ---
        $certMap = [
            'sure_induestrial' => 'SI',
            'sure_foresal' => 'SF',
            'pefc' => 'PF',
            'sbp' => 'SB',
        ];

        /**
         * Devuelve el prefijo (todo menos el contador final de 2 dígitos)
         */
        $buildPrefix = function (callable $get) use ($certMap): string {
            $sector = (string) ($get('sector') ?? '01');
            $fecha = (string) ($get('ref_fecha_fija') ?? now()->format('dmy'));

            // Detectar SU vs Servicio por campos reales:
            $esSU = filled($get('formato')) && blank($get('tipo_servicio'));

            if ($esSU) {
                $formato = (string) ($get('formato') ?? 'CA');

                $mid = 'NO';
                // si tienes checkbox/flag de certificable, úsalo aquí
                if ($get('tipo_certificacion')) {
                    $tc = $get('tipo_certificacion');
                    if (isset($certMap[$tc])) {
                        $mid = $certMap[$tc];
                    }
                }

                // SECTOR + SU + FORMATO + MID + FECHA
                return "{$sector}SU{$formato}{$mid}{$fecha}";
            }

            // Servicio
            $prov = strtoupper(substr((string) ($get('provincia') ?? ''), 0, 2));
            $ayto = strtoupper(substr((string) ($get('ayuntamiento') ?? ''), 0, 2));

            // Iniciales cliente (o NO)
            $inic = 'NO';
            if ($clienteId = $get('cliente_id')) {
                $razon = optional(\App\Models\Cliente::find($clienteId))->razon_social;
                if ($razon) {
                    $slug = strtoupper(preg_replace('/[^A-Z]/i', '', $razon));
                    $inic = substr($slug, 0, 2) ?: 'NO';
                }
            }

            // SECTOR + PROV + AYTO + INIC + FECHA
            return "{$sector}{$prov}{$ayto}{$inic}{$fecha}";
        };

        /**
         * Setea referencia única preservando el contador actual si es posible.
         * Excluye el propio registro al comprobar unicidad.
         */
        $setUniqueRef = function (callable $set, callable $get) use ($buildPrefix) {
            $prefix = $buildPrefix($get);

            $current = (string) ($get('referencia') ?? '');
            // intenta recuperar el contador actual (dos dígitos al final)
            $contadorActual = null;
            if (preg_match('/(\d{2})$/', $current, $m)) {
                $contadorActual = (int) $m[1];
            }

            $id = $get('id'); // Hidden que añadimos

            // 1) intenta con el contador actual
            if ($contadorActual !== null) {
                $try = $prefix . str_pad((string) $contadorActual, 2, '0', STR_PAD_LEFT);
                $exists = \App\Models\Referencia::where('referencia', $try)
                    ->when($id, fn($q) => $q->where('id', '<>', $id))
                    ->exists();
                if (!$exists) {
                    $set('referencia', $try);
                    return;
                }
            }

            // 2) busca el siguiente libre
            $i = 1;
            do {
                $contador = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
                $ref = $prefix . $contador;

                $exists = \App\Models\Referencia::where('referencia', $ref)
                    ->when($id, fn($q) => $q->where('id', '<>', $id))
                    ->exists();

                $i++;
            } while ($exists);

            $set('referencia', $ref);
        };
        // ----------------------------------------------------------------

        return [
            // Fecha fija también aquí para cuando uses este schema suelto
            Forms\Components\Hidden::make('ref_fecha_fija')
                ->dehydrated(false)
                ->default(now()->format('dmy')),

            View::make('livewire.tipo-select')
                ->visible(function ($state) {
                    return !isset($state['id']);
                })
                ->columnSpanFull(),

            Forms\Components\TextInput::make('referencia')
                ->required()
                ->reactive()
                ->columnSpanFull()
                ->afterStateHydrated(function (callable $set, $state, ?\App\Models\Referencia $record) {
                    $ref = (string) ($state ?: ($record->referencia ?? ''));
                    if (preg_match('/(\d{6})/', $ref, $m)) {
                        $set('ref_fecha_fija', $m[1]);
                    } else {
                        $set('ref_fecha_fija', now()->format('dmy'));
                    }
                }),

            Forms\Components\Select::make('tipo_servicio')
                ->required()
                ->searchable()
                ->options([
                    'Astillado Suelo' => 'Astillado Suelo',
                    'Astillado Camión' => 'Astillado Camión',
                    'Triturado Suelo' => 'Triturado Suelo',
                    'Triturado Camión' => 'Triturado Camión',
                    'Saca autocargador' => 'Saca autocargador',
                ])
                ->columnSpanFull()
                ->visible(function ($get) {
                    return !empty($get('referencia')) && strpos($get('referencia'), 'SU') === false;
                }),

            Forms\Components\Select::make('formato')
                ->nullable()
                ->options([
                    'CA' => 'Cargadero',
                    'SA' => 'Saca',
                    'EX' => 'Explotación',
                    'OT' => 'Otros',
                ])
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull()
                ->live()
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    if ($state) {
                        $referencia = $get('referencia') ?? '';

                        // Contador de 2 dígitos
                        preg_match('/^(?<sector>\d{2})SU(?:CA|SA|EX|OT)?(?<fecha>\d{6})(?<contador>\d{2})$/', $referencia, $matches);

                        $sector = $matches['sector'] ?? '01';
                        $fecha = (string) ($get('ref_fecha_fija') ?? now()->format('dmy'));
                        $contador = $matches['contador'] ?? '01';

                        $contadorInt = (int) $contador;

                        do {
                            $contadorFormateado = str_pad($contadorInt, 2, '0', STR_PAD_LEFT);
                            $nuevaReferencia = $sector . 'SU' . $state . $fecha . $contadorFormateado;

                            $existe = Referencia::where('referencia', $nuevaReferencia)->exists();

                            $contadorInt++;
                        } while ($existe);

                        $set('referencia', $nuevaReferencia);
                    } else {
                        $set('referencia', '');
                    }
                })
                ->visible(function ($get) {
                    return str_contains($get('referencia'), 'SU');
                }),

            Forms\Components\Section::make('Ubicación')
                ->schema([
                    Forms\Components\TextInput::make('provincia')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $referenciaActual = $get('referencia') ?? '';

                            // Solo generamos si NO es tipo suministro
                            if (str_contains($referenciaActual, 'SU')) {
                                return;
                            }

                            $provincia = strtoupper(substr($get('provincia') ?? '', 0, 2));
                            $ayuntamiento = strtoupper(substr($get('ayuntamiento') ?? '', 0, 2));
                            $fecha = (string) ($get('ref_fecha_fija') ?? now()->format('dmy'));

                            $contador = 1;
                            do {
                                $contadorStr = str_pad($contador, 2, '0', STR_PAD_LEFT);
                                $nuevaReferencia = "{$provincia}{$ayuntamiento}{$fecha}{$contadorStr}";

                                $existe = Referencia::where('referencia', $nuevaReferencia)->exists();
                                $contador++;
                            } while ($existe);

                            $set('referencia', $nuevaReferencia);
                        }),

                    Forms\Components\TextInput::make('ayuntamiento')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $referenciaActual = $get('referencia') ?? '';

                            if (str_contains($referenciaActual, 'SU')) {
                                return;
                            }

                            $provincia = strtoupper(substr($get('provincia') ?? '', 0, 2));
                            $ayuntamiento = strtoupper(substr($get('ayuntamiento') ?? '', 0, 2));
                            $fecha = (string) ($get('ref_fecha_fija') ?? now()->format('dmy'));

                            $contador = 1;
                            do {
                                $contadorStr = str_pad($contador, 2, '0', STR_PAD_LEFT);
                                $nuevaReferencia = "{$provincia}{$ayuntamiento}{$fecha}{$contadorStr}";

                                $existe = Referencia::where('referencia', $nuevaReferencia)->exists();
                                $contador++;
                            } while ($existe);

                            $set('referencia', $nuevaReferencia);
                        }),
                    Forms\Components\TextInput::make('monte_parcela')
                        ->label('Monte / Parcela')
                        ->required(),
                    Forms\Components\TextInput::make('ubicacion_gps')
                        ->label('GPS'),
                    Forms\Components\TextInput::make('finca')
                        ->label('Finca')
                        ->visible(function ($get) {
                            return !empty($get('referencia')) && strpos($get('referencia'), 'SU') === false;
                        })
                        ->columnSpanFull(),
                    Forms\Components\Select::make('sector')
                        ->label('Sector')
                        ->searchable()
                        ->columnSpanFull()
                        ->options([
                            '01' => 'Zona Norte Galicia',
                            '02' => 'Zona Sur Galicia',
                            '03' => 'Andalucía Oriental',
                            '04' => 'Andalucía Occidental',
                            '05' => 'Otros',
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $referencia = $get('referencia') ?? '';

                            $referencia = preg_replace('/^(01|02|03|04|05)/', '', $referencia);

                            $set('referencia', $state . $referencia);
                        })
                        ->visible(function ($get) {
                            return !empty($get('referencia'));
                        }),
                    View::make('livewire.get-location-button')
                        ->visible(function ($state) {
                            return !isset($state['id']);
                        })
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Intervinientes')
                ->schema([
                    Forms\Components\Select::make('proveedor_id')
                        ->nullable()
                        ->searchable()
                        ->preload()
                        ->relationship(
                            'proveedor',
                            'razon_social',
                            fn(Builder $query) => $query->whereIn('tipo_servicio', ['suministro', 'servicios'])
                        )
                        ->visible(function ($get) {
                            return strpos($get('referencia'), 'SU') !== false;
                        }),

                    Forms\Components\Select::make('cliente_id')
                        ->nullable()
                        ->searchable()
                        ->preload()
                        ->relationship('cliente', 'razon_social')
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $ref = $get('referencia') ?? '';

                            // Sólo aplica a NO-SU
                            if (str_contains($ref, 'SU'))
                                return;

                            $provincia = strtoupper(substr($get('provincia') ?? '', 0, 2));
                            $ayunta = strtoupper(substr($get('ayuntamiento') ?? '', 0, 2));
                            $fecha = (string) ($get('ref_fecha_fija') ?? now()->format('dmy'));

                            // Iniciales de cliente
                            $iniciales = 'NO';
                            if ($state) {
                                $razon = optional(\App\Models\Cliente::find($state))->razon_social;
                                if ($razon) {
                                    $slug = strtoupper(preg_replace('/[^A-Z]/i', '', $razon));
                                    $iniciales = substr($slug, 0, 2) ?: 'NO';
                                }
                            }

                            $contador = 1;
                            do {
                                $contadorStr = str_pad($contador, 2, '0', STR_PAD_LEFT);
                                $nueva = "{$provincia}{$ayunta}{$iniciales}{$fecha}{$contadorStr}";
                                $existe = \App\Models\Referencia::where('referencia', $nueva)->exists();
                                $contador++;
                            } while ($existe);

                            $set('referencia', $nueva);
                        })
                        ->visible(function ($get) {
                            return strpos($get('referencia'), 'SU') === false;
                        }),
                ])
                ->visible(function ($get) {
                    return !empty($get('referencia'));
                })
                ->columns(1),

            Forms\Components\Section::make('Producto')
                ->schema([
                    Forms\Components\Select::make('producto_especie')
                        ->label('Especie')
                        ->searchable()
                        ->options([
                            'pino' => 'Pino',
                            'eucalipto' => 'Eucalipto',
                            'acacia' => 'Acacia',
                            'frondosa' => 'Frondosa',
                            'otros' => 'Otros',
                        ])
                        ->required(),
                    Forms\Components\Select::make('producto_tipo')
                        ->label('Tipo')
                        ->searchable()
                        ->options([
                            'entero' => 'Entero',
                            'troza' => 'Troza',
                            'tacos' => 'Tacos',
                            'puntal' => 'Puntal',
                            'copas' => 'Copas',
                            'rama' => 'Rama',
                            'raices' => 'Raíces',
                        ])
                        ->required(),

                    Select::make('tipo_cantidad')
                        ->label('Tipo de cantidad')
                        ->options([
                            'toneladas' => 'Toneladas',
                            'camiones' => 'Camiones',
                        ])
                        ->searchable()
                        ->required(),

                    Forms\Components\TextInput::make('cantidad_aprox')
                        ->label('Cantidad (aprox.)')
                        ->numeric()
                        ->required(),

                    Forms\Components\Select::make('tipo_certificacion')
                        ->label('Tipo de certificación')
                        ->searchable()
                        ->options([
                            'sure_induestrial' => 'SURE - Industrial',
                            'sure_foresal' => 'SURE - Forestal',
                            'sbp' => 'SBP',
                            'pefc' => 'PEFC',
                        ])
                        ->reactive()
                        ->afterStateUpdated(fn($state, $set, $get) => $setUniqueRef($set, $get)),

                    Forms\Components\Checkbox::make('guia_sanidad')
                        ->label('¿Guía de sanidad?')
                        ->reactive(),

                ])->columns(2)
                ->visible(function ($get) {
                    return !empty($get('referencia'));
                }),

            Forms\Components\Section::make('Tarifa')
                ->schema([
                    Forms\Components\Section::make('')
                        ->schema([
                            Forms\Components\Select::make('tarifa')
                                ->label('Tarifa')
                                ->options([
                                    'toneladas' => 'Toneladas',
                                    'm3' => 'Metros cúbicos',
                                    'hora' => 'Hora',
                                ])
                                ->searchable()
                                ->nullable()
                                ->reactive(),

                            Forms\Components\TextInput::make('precio')
                                ->label(fn(callable $get) => match ($get('tarifa')) {
                                    'toneladas' => 'Precio por tonelada',
                                    'm3' => 'Precio por m³',
                                    'hora' => 'Precio por hora',
                                    default => 'Precio',
                                })
                                ->numeric()
                                ->nullable()
                                ->reactive()
                                ->suffix(fn(callable $get) => match ($get('tarifa')) {
                                    'toneladas' => '€/tonelada',
                                    'm3' => '€/m³',
                                    'hora' => '€/hora',
                                    default => '€',
                                }),
                        ])
                ])
                ->columns(3)
                ->visible(function ($get) {
                    return !empty($get('referencia'));
                }),

            Forms\Components\Section::make('Contacto')
                ->schema([
                    Forms\Components\TextInput::make('contacto_nombre')
                        ->label('Nombre')
                        ->nullable(),
                    Forms\Components\TextInput::make('contacto_telefono')
                        ->label('Teléfono')
                        ->nullable(),
                    Forms\Components\TextInput::make('contacto_email')
                        ->label('Correo electrónico')
                        ->nullable(),
                ])->columns(3)
                ->visible(function ($get) {
                    return !empty($get('referencia'));
                }),

            Section::make('Usuarios')
                ->schema([
                    Forms\Components\Select::make('usuarios')
                        ->label('Usuarios relacionados')
                        ->multiple()
                        ->relationship(
                            name: 'usuarios',
                            titleAttribute: 'name',
                            modifyQueryUsing: function ($query, $get) {
                                $query->orderBy('name')
                                    ->whereNull('users.deleted_at')
                                    ->whereDoesntHave(
                                        'roles',
                                        fn($q) =>
                                        $q->where('name', 'superadmin')
                                    );
                            }
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn($record) =>
                            $record?->nombre_apellidos .
                            ($record?->proveedor_id ? ' (' . optional($record->proveedor)->razon_social . ')' : ' (Bioforga)') ?? '-'
                        )
                        ->preload()
                        ->searchable()
                        ->columnSpanFull()
                        ->visible(fn($get) => !empty($get('referencia'))),
                ])
                ->visible(function ($get) {
                    return !empty($get('referencia'));
                }),

            Forms\Components\Section::make('Estado')
                ->schema([
                    Forms\Components\Select::make('estado')
                        ->label('Estado')
                        ->searchable()
                        ->options([
                            'abierto' => 'Abierto',
                            'en_proceso' => 'En proceso',
                            'cerrado' => 'Cerrado',
                            'cerrado_no_procede' => 'Cerrado no procede',
                        ])
                        ->required(),

                    Forms\Components\Select::make('en_negociacion')
                        ->label('En negociación')
                        ->searchable()
                        ->options([
                            'confirmado' => 'Confirmado',
                            'sin_confirmar' => 'Sin confirmar',
                        ])
                        ->required(),

                    Forms\Components\Textarea::make('observaciones')
                        ->nullable(),
                ])->columns(1)
                ->visible(function ($get) {
                    return !empty($get('referencia'));
                }),
        ];
    }
}
