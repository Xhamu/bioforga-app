<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AcopiosObservadosResource\Pages;
use App\Filament\Resources\AcopiosObservadosResource\RelationManagers;
use App\Models\AcopiosObservados;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;

class AcopiosObservadosResource extends Resource
{
    protected static ?string $model = AcopiosObservados::class;
    protected static ?string $navigationIcon = 'heroicon-o-eye';
    protected static ?string $navigationGroup = 'Parcelas';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'acopios-observados';
    protected static ?string $pluralLabel = 'Acopios observados';
    protected static ?string $label = 'acopio';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Ubicación')
                    ->schema([
                        Forms\Components\TextInput::make('provincia')
                            ->required(),
                        Forms\Components\TextInput::make('ayuntamiento')
                            ->required(),
                        Forms\Components\TextInput::make('monte_parcela')
                            ->label('Monte / Parcela')
                            ->required(),
                    ])
                    ->columns(3),

                Section::make('Producto')
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
                            ->label('Cantidad aprox.')
                            ->numeric()
                            ->required(),
                    ])->columns(3),

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
                    ])->columns(3),

                Section::make('Observaciones')
                    ->schema([
                        Textarea::make('observaciones')->label('')->rows(4)->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ubicacion')->label('Ubicación')->searchable()->sortable(),

                TextColumn::make('producto_mostrar')->label('Especie')->sortable(),
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
            'index' => Pages\ListAcopiosObservados::route('/'),
            'create' => Pages\CreateAcopiosObservados::route('/create'),
            'view' => Pages\ViewAcopiosObservados::route('/{record}'),
            'edit' => Pages\EditAcopiosObservados::route('/{record}/edit'),
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
