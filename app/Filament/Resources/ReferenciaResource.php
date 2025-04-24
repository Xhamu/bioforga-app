<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReferenciaResource\Pages;
use App\Filament\Resources\ReferenciaResource\RelationManagers;
use App\Models\Referencia;
use Filament\Forms;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\View;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Panel;
use Jenssegers\Agent\Agent;

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
        return $form
            ->schema([
                View::make('livewire.tipo-select')
                    ->visible(function ($state) {
                        return !isset($state['id']);
                    })
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('referencia')
                    ->required()
                    ->unique()
                    ->reactive()
                    ->columnSpanFull(),

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

                            $referencia = preg_replace('/\b(CA|SA|EX|OT)\b/', '', $referencia);

                            if (!str_starts_with($referencia, 'SU')) {
                                $referencia = 'SU';
                            }

                            preg_match('/(\d{6})$/', $referencia, $matches);
                            $fecha = $matches[1] ?? '';

                            $nuevaReferencia = 'SU' . $state . $fecha;

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
                            ->required(),
                        Forms\Components\TextInput::make('ayuntamiento')
                            ->required(),
                        Forms\Components\TextInput::make('monte_parcela')
                            ->label('Monte / Parcela')
                            ->required(),
                        Forms\Components\TextInput::make('ubicacion_gps')
                            ->label('GPS')
                            ->required(),
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
                            ->relationship('proveedor', 'razon_social')
                            ->visible(function ($get) {
                                return strpos($get('referencia'), 'SU') !== false;
                            }),
                        Forms\Components\Select::make('cliente_id')
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->relationship('cliente', 'razon_social')
                            ->visible(function ($get) {
                                return strpos($get('referencia'), 'SU') === false;
                            }),
                    ])
                    ->visible(function ($get) {
                        return !empty($get('referencia'));  // Se muestra solo si hay referencia
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
                                'troza' => 'Troza',
                                'tacos' => 'Tacos',
                                'puntal' => 'Puntal',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('cantidad_aprox')
                            ->label('Cantidad')
                            ->numeric()
                            ->required(),
                    ])->columns(3)
                    ->visible(function ($get) {
                        return !empty($get('referencia'));  // Se muestra solo si hay referencia
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
                        return !empty($get('referencia'));  // Se muestra solo si hay referencia
                    }),

                Forms\Components\Section::make('Usuarios')
                    ->schema([
                        Forms\Components\Select::make('usuarios')
                            ->label('Usuarios relacionados')
                            ->multiple()
                            ->relationship('usuarios', 'name')
                            ->preload()
                            ->searchable()
                            ->columnSpanFull()
                            ->visible(fn($get) => !empty($get('referencia'))),
                    ])->columns(1)
                    ->visible(function ($get) {
                        return !empty($get('referencia'));  // Se muestra solo si hay referencia
                    }),

                Forms\Components\Section::make('Estado')
                    ->schema([
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->searchable()
                            ->options([
                                'abierto' => 'Abierto',
                                'cerrado' => 'Cerrado',
                                'en_proceso' => 'En proceso',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('observaciones')
                            ->nullable(),
                    ])->columns(1)
                    ->visible(function ($get) {
                        return !empty($get('referencia'));  // Se muestra solo si hay referencia
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {

        $agent = new Agent();

        if ($agent->isMobile()) {
            return $table
                ->columns([
                    TextColumn::make('referencia')
                        ->label('Referencia')
                        ->weight(FontWeight::Bold)
                        ->searchable(),

                    Panel::make([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Stack::make([
                                    TextColumn::make('provincia')
                                        ->label('Provincia')
                                        ->icon('heroicon-m-map-pin'),

                                    TextColumn::make('estado_mostrar')
                                        ->label('Estado'),
                                ]),
                            ]),
                    ])->collapsed(false),
                ])
                ->filters([
                    Tables\Filters\TrashedFilter::make(),
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
                ->defaultSort('created_at', 'desc');
        } else {
            return $table
                ->columns([
                    TextColumn::make('referencia')
                        ->label('Referencia')
                        ->weight(FontWeight::Bold)
                        ->searchable(),
                    TextColumn::make('provincia')
                        ->label('Provincia')
                        ->icon('heroicon-m-map-pin'),

                    TextColumn::make('estado_mostrar')
                        ->label('Estado'),
                ])
                ->filters([
                    Tables\Filters\TrashedFilter::make(),
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
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
