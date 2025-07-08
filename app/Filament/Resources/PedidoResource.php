<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PedidoResource\Pages;
use App\Filament\Resources\PedidoResource\RelationManagers;
use App\Models\Pedido;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use Jenssegers\Agent\Agent;

class PedidoResource extends Resource
{
    protected static ?string $model = Pedido::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Gestión de pedidos';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'pedidos';
    public static ?string $label = 'pedido';
    public static ?string $pluralLabel = 'Pedidos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Pedidos')
                    ->schema([
                        Grid::make()
                            ->columns([
                                'default' => 1,
                                'md' => 2,
                            ])
                            ->schema([
                                DatePicker::make('fecha_pedido')
                                    ->label('Fecha del pedido')
                                    ->default(now())
                                    ->required()
                                    ->columnSpanFull(),

                                Select::make('operario_id')
                                    ->label('Operario')
                                    ->relationship('operario', 'name')
                                    ->default(Auth::id())
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Select::make('maquina_id')
                                    ->label('Máquina')
                                    ->relationship('maquina', 'id')
                                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->marca} {$record->modelo}")
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                TextInput::make('pieza_pedida')
                                    ->label('Pieza pedida')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('unidades')
                                    ->label('Unidades')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),

                                Select::make('estado')
                                    ->label('Estado')
                                    ->options([
                                        'pendiente' => 'Pendiente',
                                        'completado' => 'Completado',
                                        'cancelado' => 'Cancelado',
                                    ])
                                    ->columnSpanFull()
                                    ->required()
                                    ->default('pendiente')
                                    ->visibleOn('edit'), // Mostrar solo al editar
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $agent = new Agent();

        if ($agent->isMobile()) {
            return $table
                ->columns([
                    TextColumn::make('fecha_pedido')
                        ->label('Fecha')
                        ->formatStateUsing(fn($state) => \Carbon\Carbon::parse($state)->format('d/m/Y'))
                        ->sortable()
                        ->searchable(),

                    Panel::make([
                        TableGrid::make(['default' => 1, 'md' => 2]) // 1 columna en móvil, 2 en pantallas medianas y grandes
                            ->schema([
                                Stack::make([
                                    TextColumn::make('operario.name')
                                        ->label('Operario')
                                        ->formatStateUsing(function ($state, $record) {
                                            return $record->operario->name . ' ' . $record->operario->apellidos;
                                        })
                                        ->searchable(['operario.name', 'operario.apellidos']),

                                    TextColumn::make('maquina.marca')
                                        ->label('Máquina')
                                        ->formatStateUsing(function ($state, $record) {
                                            return $record->maquina->marca . ' - ' . $record->maquina->modelo;
                                        })
                                        ->searchable(['maquina.marca', 'maquina.modelo']),

                                    TextColumn::make('pieza_pedida')
                                        ->label('Pieza pedida')
                                        ->searchable(),

                                    TextColumn::make('unidades')
                                        ->label('Unidades')
                                        ->searchable(),

                                    TextColumn::make('estado_mostrar')
                                        ->label('Estado')
                                        ->html()
                                        ->searchable(),
                                ]),
                            ]),
                    ])->collapsed(false),
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
        } else {
            return $table
                ->columns([
                    TextColumn::make('fecha_pedido')
                        ->label('Fecha')
                        ->formatStateUsing(fn($state) => \Carbon\Carbon::parse($state)->format('d/m/Y'))
                        ->sortable()
                        ->searchable(),

                    TextColumn::make('operario.name')
                        ->label('Operario')
                        ->formatStateUsing(function ($state, $record) {
                            return $record->operario->name . ' ' . $record->operario->apellidos;
                        })
                        ->searchable(['operario.name', 'operario.apellidos']),

                    TextColumn::make('maquina.marca')
                        ->label('Máquina')
                        ->formatStateUsing(function ($state, $record) {
                            return $record->maquina->marca . ' - ' . $record->maquina->modelo;
                        })
                        ->searchable(['maquina.marca', 'maquina.modelo']),

                    TextColumn::make('pieza_pedida')
                        ->label('Pieza pedida')
                        ->searchable(),

                    TextColumn::make('unidades')
                        ->label('Unidades')
                        ->searchable(),

                    TextColumn::make('estado_mostrar')
                        ->label('Estado')
                        ->html()
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
            'index' => Pages\ListPedidos::route('/'),
            'create' => Pages\CreatePedido::route('/create'),
            'view' => Pages\ViewPedido::route('/{record}'),
            'activities' => Pages\ListPedidosActivities::route('/{record}/activities'),
            'edit' => Pages\EditPedido::route('/{record}/edit'),
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
            $query->where('operario_id', $user->id);
        }

        return $query;
    }
}
