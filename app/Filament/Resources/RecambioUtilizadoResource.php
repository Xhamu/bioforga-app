<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecambioUtilizadoResource\Pages;
use App\Filament\Resources\RecambioUtilizadoResource\RelationManagers;
use App\Models\RecambioUtilizado;
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

class RecambioUtilizadoResource extends Resource
{
    protected static ?string $model = RecambioUtilizado::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';
    protected static ?string $navigationGroup = 'Maestros';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'recambios';
    public static ?string $label = 'recambio';
    public static ?string $pluralLabel = 'Recambios';
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
            'index' => Pages\ListRecambioUtilizados::route('/'),
            'create' => Pages\CreateRecambioUtilizado::route('/create'),
            'view' => Pages\ViewRecambioUtilizado::route('/{record}'),
            'edit' => Pages\EditRecambioUtilizado::route('/{record}/edit'),
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
