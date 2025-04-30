<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlmacenIntermedioResource\Pages;
use App\Filament\Resources\AlmacenIntermedioResource\RelationManagers;
use App\Models\AlmacenIntermedio;
use Filament\Forms;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
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
                Forms\Components\TextInput::make('referencia')
                    ->required()
                    ->unique()
                    ->reactive()
                    ->default('ALM')
                    ->columnSpanFull(),

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
                    ->visible(function ($get) {
                        return !empty($get('referencia'));  // Se muestra solo si hay referencia
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
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
            ]);
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
}
