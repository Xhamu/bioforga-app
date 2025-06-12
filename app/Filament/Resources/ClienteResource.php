<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteResource\Pages;
use App\Filament\Resources\ClienteResource\RelationManagers;
use App\Models\Cliente;
use App\Models\Pais;
use App\Models\Poblacion;
use App\Models\Provincia;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\File;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Gestión';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'clientes';
    public static ?string $label = 'cliente';
    public static ?string $pluralLabel = 'Clientes';

    public static function form(Form $form): Form
    {
        $ubicaciones = json_decode(File::get(resource_path('data/ubicaciones.json')), true);

        return $form
            ->schema([
                Forms\Components\Section::make('Datos')
                    ->schema([
                        // Datos de cliente
                        TextInput::make('razon_social')
                            ->label('Razón Social')
                            ->required(),
                        TextInput::make('nif')
                            ->label('NIF')
                            ->required(),
                        TextInput::make('telefono_principal')
                            ->label('Teléfono'),
                        TextInput::make('correo_principal')
                            ->label('Correo electrónico'),

                        Select::make('tipo_cliente')
                            ->label('Tipo de cliente')
                            ->searchable()
                            ->options([
                                'Suministro' => 'Suministro',
                                'Servicio' => 'Servicio',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'sm' => 1,
                        'md' => 2,
                    ]),

                Forms\Components\Section::make('Dirección fiscal')
                    ->schema([
                        // Dirección fiscal
                        Select::make('pais')
                            ->label('País')
                            ->options(fn() => Pais::orderBy('nombre')->pluck('nombre', 'id'))
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'md' => 1]),

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
                            ->label('Código postal'),
                        TextInput::make('direccion')
                            ->label('Dirección')
                            ->columnSpan([
                                'sm' => 1,
                                'md' => 2,
                            ]),
                    ])
                    ->columns([
                        'sm' => 1,
                        'md' => 2,
                    ]),

                Repeater::make('direcciones_envio')
                    ->label('Direcciones de envío')
                    ->relationship()
                    ->schema([
                        Select::make('pais')
                            ->label('País')
                            ->options(fn() => Pais::orderBy('nombre')->pluck('nombre', 'id'))
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'md' => 1]),

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
                            ->label('Código postal'),
                        TextInput::make('direccion')
                            ->live(onBlur: true)
                            ->label('Dirección')
                            ->columnSpan([
                                'sm' => 1,
                                'md' => 2,
                            ]),
                    ])
                    ->addActionLabel('Añadir dirección')
                    ->columns(2)
                    ->itemLabel(fn(array $state): ?string => $state['direccion'] ?? null),

                // Personas de contacto
                Repeater::make('personas_contacto')
                    ->label('Personas de contacto')
                    ->relationship()
                    ->schema([
                        TextInput::make('nombre_completo')
                            ->live(onBlur: true)
                            ->label('Nombre completo'),
                        TextInput::make('cargo')
                            ->label('Cargo'),
                        TextInput::make('telefono_directo')
                            ->label('Teléfono'),
                        TextInput::make('correo_electronico')
                            ->label('Correo electrónico'),
                    ])
                    ->addActionLabel('Añadir contacto')
                    ->columns(2)
                    ->itemLabel(fn(array $state): ?string => $state['nombre_completo'] ?? null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('razon_social')
                    ->label('Razón social')
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->sortable(),
                TextColumn::make('nif')
                    ->label('NIF')
                    ->searchable()
                    ->icon('heroicon-m-identification'),
                TextColumn::make('telefono_principal')
                    ->label('Teléfono')
                    ->icon('heroicon-m-phone'),
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
            'index' => Pages\ListClientes::route('/'),
            'create' => Pages\CreateCliente::route('/create'),
            'view' => Pages\ViewCliente::route('/{record}'),
            'edit' => Pages\EditCliente::route('/{record}/edit'),
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
