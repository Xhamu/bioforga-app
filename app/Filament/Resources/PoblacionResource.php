<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PoblacionResource\Pages;
use App\Filament\Resources\PoblacionResource\RelationManagers;
use App\Models\Poblacion;
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

class PoblacionResource extends Resource
{
    protected static ?string $model = Poblacion::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';
    protected static ?string $navigationGroup = 'Maestros';
    protected static ?int $navigationSort = 7;
    protected static ?string $slug = 'poblaciones';
    public static ?string $label = 'población';
    public static ?string $pluralLabel = 'Poblaciones';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos de la población')
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->rules('required')
                            ->autofocus()
                            ->validationMessages([
                                'required' => 'El nombre es obligatorio.',
                            ]),

                        TextInput::make('codigo')
                            ->label('Código')
                            ->nullable(),

                        Select::make('provincia_id')
                            ->label('Provincia')
                            ->relationship('provincia', 'nombre')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->validationMessages([
                                'required' => 'La provincia es obligatoria.',
                            ]),
                    ])
                    ->columns(2),
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
            'index' => Pages\ListPoblacions::route('/'),
            'create' => Pages\CreatePoblacion::route('/create'),
            'view' => Pages\ViewPoblacion::route('/{record}'),
            'edit' => Pages\EditPoblacion::route('/{record}/edit'),
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
