<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TipologiaResource\Pages;
use App\Filament\Resources\TipologiaResource\RelationManagers;
use App\Models\Tipologia;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TipologiaResource extends Resource
{
    protected static ?string $model = Tipologia::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';
    protected static ?string $navigationGroup = 'Maestros';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'tipologias';
    public static ?string $label = 'tipología';
    public static ?string $pluralLabel = 'Tipologías';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('nombre')
                    ->label(__('Nombre'))
                    ->required()
                    ->rules('required')
                    ->autofocus()
                    ->validationMessages([
                        'required' => 'El :attribute es obligatorio.',
                    ])
                    ->columnSpanFull(),

                Textarea::make('descripcion')
                    ->label(__('Descripción'))
                    ->rows(4)
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

                TextColumn::make('descripcion_corta')
                    ->label('Descripción')
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
            'index' => Pages\ListTipologias::route('/'),
            'create' => Pages\CreateTipologia::route('/create'),
            'view' => Pages\ViewTipologia::route('/{record}'),
            'edit' => Pages\EditTipologia::route('/{record}/edit'),
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