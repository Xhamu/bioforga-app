<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VehiculoResource\Pages;
use App\Filament\Resources\VehiculoResource\RelationManagers;
use App\Models\Vehiculo;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Jenssegers\Agent\Agent;

class VehiculoResource extends Resource
{
    protected static ?string $model = Vehiculo::class;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Gestión de flota';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'vehiculos';
    public static ?string $label = 'vehiculo';
    public static ?string $pluralLabel = 'Vehículos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos vehículo')
                    ->schema([
                        TextInput::make('marca')
                            ->label(__('Marca'))
                            ->required()
                            ->rules('required')
                            ->autofocus()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'lg' => 1]),

                        TextInput::make('modelo')
                            ->label(__('Modelo'))
                            ->required()
                            ->rules('required')
                            ->autofocus()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'lg' => 1]),

                        TextInput::make('matricula')
                            ->label(__('Matrícula'))
                            ->required()
                            ->rules('required')
                            ->autofocus()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'lg' => 2]),

                        Select::make('conductor_habitual')
                            ->label(__('Conductor habitual'))
                            ->required()
                            ->searchable()
                            ->rules('required')
                            ->options(function () {
                                return \App\Models\User::all()->pluck('name', 'id')->toArray();
                            })
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'lg' => 2]),
                    ])
                    ->columns(2)
                    ->columnSpan(2),

                TableRepeater::make('itvs')
                    ->relationship('itvs')
                    ->label('ITVs del vehículo')
                    ->addActionLabel('Añadir ITV')
                    ->headers([
                        Header::make('fecha')
                            ->label('Fecha')
                            ->align(Alignment::Center)
                            ->width('150px'),

                        Header::make('lugar')
                            ->label('Lugar')
                            ->align(Alignment::Center)
                            ->width('150px'),

                        Header::make('resultado')
                            ->label('Resultado')
                            ->align(Alignment::Center)
                            ->width('150px'),

                        Header::make('observaciones')
                            ->label('Observaciones')
                            ->align(Alignment::Center)
                            ->width('150px'),

                        Header::make('documento')
                            ->label('Documento')
                            ->align(Alignment::Center)
                            ->width('150px'),
                    ])
                    ->schema([
                        DatePicker::make('fecha')
                            ->default(now())
                            ->required(),

                        TextInput::make('lugar')
                            ->required(),

                        Select::make('resultado')
                            ->searchable()
                            ->options([
                                'Favorable' => 'Favorable',
                                'Desfavorable' => 'Desfavorable',
                                'Negativo' => 'Negativo',
                            ])
                            ->required(),

                        Textarea::make('observaciones')
                            ->nullable(),

                        FileUpload::make('documento')
                            ->disk('public')
                            ->directory('itvs')
                            ->preserveFilenames()
                            ->openable()
                            ->imageEditor()
                            ->nullable()
                            ->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg']),
                    ])
                    ->emptyLabel('Aún no se han registrado ITVs')
                    ->columnSpan('full')
                    ->defaultItems(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        $agent = new Agent();

        if ($agent->isMobile()) {
            return $table
                ->columns([

                    Panel::make([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Stack::make([
                                    TextColumn::make('marca_modelo')
                                        ->label('Marca - Modelo')
                                        ->searchable(),

                                    TextColumn::make('matricula')
                                        ->label('matricula')
                                        ->searchable(),
                                ]),
                            ]),
                    ])->collapsed(false),
                ])
                ->filters([
                    Tables\Filters\TrashedFilter::make(),
                ])
                ->actions([
                    Tables\Actions\EditAction::make(),
                ])
                ->bulkActions([
                    Tables\Actions\BulkActionGroup::make([
                        Tables\Actions\DeleteBulkAction::make(),
                        Tables\Actions\ForceDeleteBulkAction::make(),
                        Tables\Actions\RestoreBulkAction::make(),
                    ]),
                ]);
        } else {
            return $table
                ->columns([
                    TextColumn::make('marca_modelo')
                        ->label('Marca - Modelo')
                        ->searchable(),

                    TextColumn::make('matricula')
                        ->label('Matrícula')
                        ->searchable(),
                ])
                ->filters([
                    Tables\Filters\TrashedFilter::make(),
                ])
                ->actions([
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
            'index' => Pages\ListVehiculos::route('/'),
            'create' => Pages\CreateVehiculo::route('/create'),
            'edit' => Pages\EditVehiculo::route('/{record}/edit'),
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
