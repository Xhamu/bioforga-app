<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProvinciaResource\Pages;
use App\Filament\Resources\ProvinciaResource\RelationManagers;
use App\Models\Provincia;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProvinciaResource extends Resource
{
    protected static ?string $model = Provincia::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';
    protected static ?string $navigationGroup = 'Maestros';
    protected static ?int $navigationSort = 6;
    protected static ?string $slug = 'provincias';
    public static ?string $label = 'provincia';
    public static ?string $pluralLabel = 'Provincias';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos de la provincia')
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->rules('required', 'string', 'max:255')
                            ->autofocus()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ]),

                        TextInput::make('codigo')
                            ->label('Código')
                            ->nullable()
                            ->rules('nullable', 'string', 'max:10'),

                        Select::make('pais_id')
                            ->label('País')
                            ->relationship('pais', 'nombre') // Asegúrate de tener bien definida la relación en el modelo
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->validationMessages([
                                'required' => 'El país es obligatorio.',
                            ]),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);

    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('codigo')
                    ->label('Código')
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
            'index' => Pages\ListProvincias::route('/'),
            'create' => Pages\CreateProvincia::route('/create'),
            'view' => Pages\ViewProvincia::route('/{record}'),
            'edit' => Pages\EditProvincia::route('/{record}/edit'),
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
