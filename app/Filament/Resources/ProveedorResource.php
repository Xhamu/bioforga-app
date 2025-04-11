<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProveedorResource\Pages;
use App\Filament\Resources\ProveedorResource\RelationManagers;
use App\Models\Proveedor;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Jenssegers\Agent\Agent;

class ProveedorResource extends Resource
{
    protected static ?string $model = Proveedor::class;
    protected static ?string $navigationIcon = 'heroicon-o-globe-europe-africa';
    protected static ?string $navigationGroup = 'Gestión';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'proveedores';
    public static ?string $label = 'proveedor';
    public static ?string $pluralLabel = 'Proveedores';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos')
                    ->schema([
                        TextInput::make('razon_social')
                            ->label(__('Razón social'))
                            ->required()
                            ->rules('required')
                            ->autofocus()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'lg' => 1]),

                        TextInput::make('nif')
                            ->label(__('NIF'))
                            ->required()
                            ->rules('required')
                            ->autofocus()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'lg' => 1]),

                        TextInput::make('telefono')
                            ->label(__('Teléfono'))
                            ->required()
                            ->rules('required')
                            ->autofocus()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'lg' => 1]),

                        TextInput::make('email')
                            ->label(__('Correo electrónico'))
                            ->required()
                            ->rules('required')
                            ->autofocus()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'lg' => 1]),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull(),

                Section::make('Dirección fiscal')
                    ->schema([
                        Select::make('pais')
                            ->label(__('País'))
                            ->options([
                                'es' => 'España',
                                'fr' => 'Francia',
                                'it' => 'Italia',
                            ])
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        Select::make('provincia')
                            ->label(__('Provincia'))
                            ->options(fn(callable $get) => match ($get('pais')) {
                                'es' => [
                                    'madrid' => 'Madrid',
                                    'barcelona' => 'Barcelona',
                                    'sevilla' => 'Sevilla',
                                ],
                                'fr' => [
                                    'paris' => 'París',
                                    'lyon' => 'Lyon',
                                    'marseille' => 'Marsella',
                                ],
                                'it' => [
                                    'rome' => 'Roma',
                                    'milan' => 'Milán',
                                    'naples' => 'Nápoles',
                                ],
                                default => [],
                            })
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        Select::make('poblacion')
                            ->label(__('Población'))
                            ->options(fn(callable $get) => match ($get('provincia')) {
                                'madrid' => ['centro' => 'Centro', 'chamartin' => 'Chamartín'],
                                'barcelona' => ['eixample' => 'Eixample', 'gracia' => 'Gracia'],
                                'sevilla' => ['nervion' => 'Nervión', 'triana' => 'Triana'],
                                'paris' => ['louvre' => 'Louvre', 'montmartre' => 'Montmartre'],
                                'lyon' => ['presquile' => 'Presqu\'île', 'croixrousse' => 'Croix-Rousse'],
                                'marseille' => ['vieuxport' => 'Vieux-Port', 'laplaine' => 'La Plaine'],
                                'rome' => ['centro' => 'Centro', 'trastevere' => 'Trastevere'],
                                'milan' => ['duomo' => 'Duomo', 'navigli' => 'Navigli'],
                                'naples' => ['vomero' => 'Vomero', 'chiai' => 'Chiaia'],
                                default => [],
                            })
                            ->searchable()
                            ->required()
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('codigo_postal')
                            ->label(__('Código postal'))
                            ->rules('required|numeric')
                            ->required()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                                'numeric' => 'El :attribute no es válido.'
                            ])
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('direccion')
                            ->label(__('Dirección'))
                            ->required()
                            ->rules('required')
                            ->validationMessages([
                                'required' => 'La :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'md' => 2]),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull(),

                Section::make('Persona de contacto')
                    ->schema([
                        TextInput::make('nombre_contacto')
                            ->label(__('Nombre completo'))
                            ->rules('required')
                            ->required()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('cargo_contacto')
                            ->label(__('Cargo'))
                            ->rules('required')
                            ->required()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('telefono_contacto')
                            ->label(__('Teléfono'))
                            ->required()
                            ->rules('required')
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('email_contacto')
                            ->label(__('Correo electrónico'))
                            ->rules('required')
                            ->required()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'md' => 1]),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull(),

                Section::make('Usuarios relacionados')
                    ->schema([
                        View::make('filament.components.usuarios-relacionados')
                            ->viewData([
                                'proveedorId' => optional($form->getRecord())->id,
                            ])
                            ->columnSpanFull()
                            ->columns(2),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {

        $agent = new Agent();

        if ($agent->isMobile()) {
            return $table
                ->columns([
                    TextColumn::make('razon_social')
                        ->weight(FontWeight::Bold)
                        ->searchable()
                        ->sortable(),

                    Panel::make([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Stack::make([
                                    TextColumn::make('telefono')
                                        ->icon('heroicon-m-phone'),
                                    TextColumn::make('email')
                                        ->icon('heroicon-m-envelope'),
                                ]),
                            ]),
                    ])->collapsed(false),
                ])
                ->filters([
                    //
                ])
                ->actions([
                    //Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                ])
                ->bulkActions([
                    Tables\Actions\BulkActionGroup::make([
                        Tables\Actions\DeleteBulkAction::make(),
                    ]),
                ]);
        } else {
            return $table
                ->columns([
                    TextColumn::make('razon_social')
                        ->weight(FontWeight::Bold)
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('telefono')
                        ->icon('heroicon-m-phone'),
                    TextColumn::make('email')
                        ->icon('heroicon-m-envelope'),
                ])
                ->filters([
                    //
                ])
                ->actions([
                    //Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                ])
                ->bulkActions([
                    Tables\Actions\BulkActionGroup::make([
                        Tables\Actions\DeleteBulkAction::make(),
                    ]),
                ]);
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
            'index' => Pages\ListProveedors::route('/'),
            'create' => Pages\CreateProveedor::route('/create'),
            'edit' => Pages\EditProveedor::route('/{record}/edit'),
        ];
    }
}
