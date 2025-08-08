<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TallerResource\Pages;
use App\Models\Pais;
use App\Models\Poblacion;
use App\Models\Provincia;
use App\Models\Taller;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\File;

class TallerResource extends Resource
{
    protected static ?string $model = Taller::class;

    protected static ?string $navigationGroup = 'Gestión';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'talleres';

    public static ?string $label = 'taller';

    public static ?string $pluralLabel = 'Talleres';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->autofocus()
                    ->columnSpanFull(),

                ToggleButtons::make('propio')
                    ->label('Propiedad')
                    ->inline()
                    ->options([
                        1 => 'Propio',
                        0 => 'Ajeno',
                    ])
                    ->icons([
                        1 => 'heroicon-o-check-badge',
                        0 => 'heroicon-o-user-group',
                    ])
                    ->colors([
                        1 => 'success',
                        0 => 'gray',
                    ])
                    ->required()
                    ->default(0),

                Section::make('Ubicación')
                    ->schema([
                        Select::make('pais')
                            ->label('País')
                            ->options(fn() => Pais::orderBy('nombre')->pluck('nombre', 'id'))
                            ->searchable()
                            ->required()
                            ->reactive(),

                        Select::make('provincia')
                            ->label('Provincia')
                            ->options(fn(callable $get) => Provincia::where('pais_id', $get('pais'))->orderBy('nombre')->pluck('nombre', 'id'))
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->disabled(fn(callable $get) => !$get('pais')),

                        Select::make('poblacion')
                            ->label('Población')
                            ->options(fn(callable $get) => Poblacion::where('provincia_id', $get('provincia'))->orderBy('nombre')->pluck('nombre', 'id'))
                            ->searchable()
                            ->required()
                            ->disabled(fn(callable $get) => !$get('provincia')),

                        TextInput::make('codigo_postal')
                            ->label('Código postal')
                            ->rules('required|numeric')
                            ->required(),

                        TextInput::make('direccion')
                            ->label('Dirección')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Personas de contacto')
                    ->schema([
                        Repeater::make('contactos')
                            ->relationship() // Usa la relación hasMany en el modelo Taller
                            ->schema([
                                TextInput::make('nombre')->label('Nombre')->required(),
                                TextInput::make('cargo')->label('Cargo')->maxLength(255),
                                TextInput::make('telefono')->label('Teléfono')->tel(),
                                TextInput::make('email')->label('Email')->email(),
                                Forms\Components\Toggle::make('principal')->label('Principal'),
                                Textarea::make('notas')->label('Notas')->rows(2)->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->createItemButtonLabel('Añadir contacto'),
                    ])
                    ->collapsed(false)
                    ->columnSpanFull(),

                Section::make('Observaciones')
                    ->schema([
                        Textarea::make('observaciones')->label('')->rows(4)->nullable(),
                    ]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('propio')
                    ->label('Propiedad')
                    ->formatStateUsing(fn($state) => $state ? 'Propio' : 'Ajeno')
                    ->badge()
                    ->color(fn($state) => $state ? 'success' : 'gray'),

                TextColumn::make('direccion')
                    ->icon('heroicon-m-map-pin'),

                TextColumn::make('contactos_count')
                    ->counts('contactos')
                    ->label('Contactos')
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('propio')
                    ->label('Propiedad')
                    ->options([
                        1 => 'Propio',
                        0 => 'Ajeno',
                    ])
                    ->searchable()
                    ->attribute('propio')
                    ->placeholder('Todas'),

                Tables\Filters\SelectFilter::make('provincia')
                    ->label('Provincia')
                    ->options(fn() => Provincia::orderBy('nombre')->pluck('nombre', 'id'))
                    ->searchable()
                    ->placeholder('Todas'),

                Tables\Filters\TernaryFilter::make('contactos')
                    ->label('Contactos')
                    ->placeholder('Todos')
                    ->trueLabel('Con contactos')
                    ->falseLabel('Sin contactos')
                    ->searchable()
                    ->queries(
                        true: fn($query) => $query->has('contactos'),
                        false: fn($query) => $query->doesntHave('contactos'),
                    ),

                TrashedFilter::make()
                    ->visible(fn() => Filament::auth()->user()?->hasRole('superadmin'))
                    ->columnSpanFull(),

            ], layout: Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated(true)
            ->paginationPageOptions([50, 100, 200])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // Si prefieres usar RelationManager en vez de Repeater, añádelo aquí
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTallers::route('/'),
            'create' => Pages\CreateTaller::route('/create'),
            'edit' => Pages\EditTaller::route('/{record}/edit'),
        ];
    }
}
