<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlmacenIntermedioResource\Pages;
use App\Models\AlmacenIntermedio;
use Filament\Forms;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Forms\Components\View;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
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
                    ->id('almacen-tabs')
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

                        /*Forms\Components\Tabs\Tab::make('Entradas')
                            ->schema([
                                Repeater::make('entradasAlmacen')
                                    ->relationship('entradasAlmacen')
                                    ->label('Entradas de material')
                                    //->orderable('fecha') // o 'id'
                                    ->defaultItems(0)
                                    ->createItemButtonLabel('Añadir entrada')
                                    ->reorderable()
                                    ->columns(12) // grid interno del repeater
                                    ->schema([
                                        // Fila 1: Fecha + Tipo
                                        DatePicker::make('fecha')
                                            ->label('Fecha')
                                            ->default(now('Europe/Madrid'))
                                            ->timezone('Europe/Madrid')
                                            ->required()
                                            ->native(false)
                                            ->columnSpan([
                                                'default' => 12,
                                                'md' => 6,
                                                'xl' => 6,
                                            ]),

                                        Select::make('tipo')
                                            ->label('Tipo')
                                            ->options([
                                                'madera' => 'Madera',
                                                'astilla' => 'Astilla',
                                            ])
                                            ->required()
                                            ->native(false)
                                            ->columnSpan([
                                                'default' => 12,
                                                'md' => 6,
                                                'xl' => 6,
                                            ]),

                                        // Fila 2: Proveedor + Transporte (empresa + matrícula)
                                        Select::make('proveedor_id')
                                            ->label('Proveedor')
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Selecciona un proveedor')
                                            ->relationship(
                                                name: 'proveedor',
                                                titleAttribute: 'razon_social',
                                                modifyQueryUsing: fn(Builder $query) => $query->orderBy('razon_social')
                                            )
                                            ->reactive() // <- para que notifique a los campos dependientes
                                            ->columnSpan([
                                                'default' => 12,
                                                'md' => 6,
                                                'xl' => 6,
                                            ]),

                                        Fieldset::make('Transporte')
                                            ->schema([
                                                // Usuario transportista dependiente del proveedor seleccionado
                                                Select::make('transportista_id')
                                                    ->label('Usuario transportista')
                                                    ->searchable()
                                                    ->preload()
                                                    ->options(function (Get $get) {
                                                        $proveedorId = $get('proveedor_id');
                                                        if (!$proveedorId) {
                                                            return [];
                                                        }

                                                        return \App\Models\User::query()
                                                            ->where('proveedor_id', $proveedorId)
                                                            ->whereHas('roles', fn(Builder $q) => $q->where('name', 'transportista'))
                                                            ->orderBy('name')
                                                            ->get()
                                                            ->mapWithKeys(fn($u) => [
                                                                $u->id => trim($u->name . ' ' . $u->apellidos),
                                                            ])
                                                            ->toArray();
                                                    })
                                                    ->disabled(fn(Get $get) => blank($get('proveedor_id')))
                                                    ->reactive()
                                                    ->required()
                                                    ->columnSpan([
                                                        'default' => 12,
                                                        'md' => 8,
                                                        'xl' => 8,
                                                    ]),

                                                // Camiones del usuario transportista seleccionado
                                                Select::make('camion_id')
                                                    ->label('Camión (matrícula)')
                                                    ->searchable()
                                                    ->preload()
                                                    ->options(function (Get $get) {
                                                        $proveedorId = $get('proveedor_id');
                                                        if (!$proveedorId)
                                                            return [];

                                                        return \App\Models\Camion::query()
                                                            ->where('proveedor_id', $proveedorId)
                                                            ->orderBy('matricula_cabeza')
                                                            ->get()
                                                            ->mapWithKeys(fn($c) => [
                                                                $c->id => $c->matricula_cabeza
                                                                    . (($c->marca || $c->modelo) ? ' · ' . trim(($c->marca ?? '') . ' ' . ($c->modelo ?? '')) : ''),
                                                            ])
                                                            ->toArray();
                                                    })
                                                    ->disabled(fn(Get $get) => blank($get('proveedor_id')))
                                                    ->required()
                                                    ->columnSpan([
                                                        'default' => 12,
                                                        'md' => 4,
                                                        'xl' => 4,
                                                    ]),
                                            ])
                                            ->columns(12)
                                            ->columnSpan([
                                                'default' => 12,
                                                'md' => 6,
                                                'xl' => 6,
                                            ]),

                                        // Fila 3: Cantidad + Especie
                                        TextInput::make('cantidad')
                                            ->label('Cantidad')
                                            ->numeric()
                                            ->minValue(0)
                                            ->step('0.001')
                                            ->required()
                                            ->suffix('t') // cámbialo a 'm³' si aplica
                                            ->helperText('Usa punto para decimales (ej. 12.500)')
                                            ->columnSpan([
                                                'default' => 12,
                                                'md' => 6,
                                                'xl' => 6,
                                            ]),

                                        Select::make('especie')
                                            ->label('Especie')
                                            ->options([
                                                'pino' => 'Pino',
                                                'eucalipto' => 'Eucalipto',
                                                'acacia' => 'Acacia',
                                                'frondosa' => 'Frondosa',
                                                'otros' => 'Otros',
                                            ])
                                            ->searchable()
                                            ->native(false)
                                            ->columnSpan([
                                                'default' => 12,
                                                'md' => 6,
                                                'xl' => 6,
                                            ]),
                                    ])
                                    ->columnSpanFull(), // el repeater ocupa todo el ancho de la Tab
                            ]),*/

                        // TAB NUEVO
                        Forms\Components\Tabs\Tab::make('Suministro almacén')
                            ->schema([
                                Forms\Components\View::make('filament.resources.referencia-resource.partials.partes-trabajo-almacen')
                                    ->viewData([
                                        'recordId' => request()->route('record'),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Prioridades de stock')
                            ->schema([
                                ViewField::make('prioridad_stock_tab')
                                    ->view('filament.resources.almacen-intermedio-resource.partials.prioridad-stock-tab')
                                    ->viewData([
                                        'recordId' => request()->route('record'),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        /*Forms\Components\Tabs\Tab::make('Stock')
                            ->schema([
                                Forms\Components\View::make('filament.resources.referencia-resource.partials.stock-almacen')
                                    ->viewData([
                                        'recordId' => request()->route('record'),
                                    ])
                                    ->columnSpanFull(),
                            ]),*/

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
