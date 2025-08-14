<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlmacenIntermedioResource\Pages;
use App\Filament\Resources\AlmacenIntermedioResource\RelationManagers;
use App\Models\AlmacenIntermedio;
use Filament\Forms;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AlmacenIntermedioResource extends Resource
{
    protected static ?string $model = AlmacenIntermedio::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Parcelas';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'almacenes-intermedios';
    protected static ?string $pluralLabel = 'Almacenes intermedios';
    protected static ?string $label = 'almacén intermedio';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('AlmacenTabs')
                    ->columnSpanFull()
                    ->id('almacen-tabs')           // id único para esta Tabs
                    ->persistTab()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Datos generales')
                            ->schema([
                                Forms\Components\TextInput::make('referencia')
                                    ->label('Referencia')
                                    ->required()
                                    ->reactive()
                                    ->default('ALM')
                                    ->afterStateHydrated(function ($component, $state) {
                                        if (empty($state)) {
                                            $component->state(self::generarReferencia('', '', now()));
                                        }
                                    })
                                    ->columnSpanFull(),

                                Forms\Components\Section::make('Ubicación')
                                    ->schema([
                                        Forms\Components\TextInput::make('provincia')
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function (callable $set, $state, callable $get) {
                                                $set('referencia', self::generarReferencia($state, $get('ayuntamiento'), now()));
                                            }),
                                        Forms\Components\TextInput::make('ayuntamiento')
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function (callable $set, $state, callable $get) {
                                                $set('referencia', self::generarReferencia($get('provincia'), $state, now()));
                                            }),
                                        Forms\Components\TextInput::make('monte_parcela')
                                            ->label('Monte / Parcela')
                                            ->required(),
                                        Forms\Components\TextInput::make('ubicacion_gps')
                                            ->label('GPS'),
                                        View::make('livewire.get-location-button')
                                            ->visible(function ($state) {
                                                return !isset($state['id']);
                                            })
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),

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
                                    ->visible(fn($get) => !empty($get('referencia'))),

                                Forms\Components\Section::make('Usuarios')
                                    ->schema([
                                        Forms\Components\Select::make('usuarios')
                                            ->label('Usuarios relacionados')
                                            ->multiple()
                                            ->relationship('usuarios', 'name')
                                            ->options(fn() => \App\Models\User::orderBy('name')
                                                ->get()
                                                ->mapWithKeys(fn($user) => [
                                                    $user->id => "{$user->name} {$user->apellidos}",
                                                ])
                                                ->toArray())
                                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->name} {$record->apellidos}")
                                            ->preload()
                                            ->searchable()
                                            ->columnSpanFull()
                                            ->visible(fn($get) => !empty($get('referencia'))),
                                    ])
                                    ->columns(1)
                                    ->visible(fn($get) => !empty($get('referencia'))),
                            ]),

                        // TAB NUEVO
                        Forms\Components\Tabs\Tab::make('Partes de trabajo')
                            ->schema([
                                Forms\Components\View::make('filament.resources.referencia-resource.partials.partes-trabajo-almacen')
                                    ->viewData([
                                        'recordId' => request()->route('record'),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Stock')
                            ->schema([
                                Forms\Components\View::make('filament.resources.referencia-resource.partials.stock-almacen')
                                    ->viewData([
                                        'recordId' => request()->route('record'),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('referencia')
                    ->label('Referencia')
                    ->weight(FontWeight::Bold)
                    ->searchable(),

                TextColumn::make('provincia')
                    ->label('Provincia')
                    ->icon('heroicon-m-map-pin'),
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
            'index' => Pages\ListAlmacenIntermedios::route('/'),
            'create' => Pages\CreateAlmacenIntermedio::route('/create'),
            'view' => Pages\ViewAlmacenIntermedio::route('/{record}'),
            'edit' => Pages\EditAlmacenIntermedio::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    protected static function generarReferencia($provincia, $ayuntamiento, $fecha)
    {
        $prov = strtoupper(substr($provincia ?? '', 0, 2));
        $ayto = strtoupper(substr($ayuntamiento ?? '', 0, 2));
        $date = $fecha->format('y') . $fecha->format('m') . $fecha->format('d');

        $base = "ALM{$prov}{$ayto}{$date}";

        // Contar cuántas referencias hay hoy con este mismo prefijo
        $count = AlmacenIntermedio::whereDate('created_at', $fecha)
            ->where('referencia', 'like', $base . '%')
            ->count();

        $sufijo = str_pad($count + 1, 2, '0', STR_PAD_LEFT);

        return "{$base}{$sufijo}";
    }
}
