<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CamionResource\Pages;
use App\Filament\Resources\CamionResource\RelationManagers;
use App\Models\Camion;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Jenssegers\Agent\Agent;

class CamionResource extends Resource
{
    protected static ?string $model = Camion::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Gestión de flota';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'camiones';
    public static ?string $label = 'camión';
    public static ?string $pluralLabel = 'Camiones';

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

                        TextInput::make('matricula_cabeza')
                            ->label(__('Matrícula cabeza'))
                            ->required()
                            ->rules('required')
                            ->autofocus()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'lg' => 1]),

                        TextInput::make('matricula_remolque')
                            ->label(__('Matrícula remolque'))
                            ->required()
                            ->rules('required')
                            ->autofocus()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'lg' => 1]),

                        Select::make('proveedor_id')
                            ->label(__('Proveedor'))
                            ->required()
                            ->rules('required')
                            ->searchable()
                            ->options(function () {
                                return \App\Models\Proveedor::all()->pluck('razon_social', 'id')->toArray();
                            })
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'lg' => 2]),
                    ])
                    ->columns(2)
                    ->columnSpan(2),
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

                                    TextColumn::make('matricula_cabeza')
                                        ->label('Matrícula cabeza')
                                        ->searchable(),
                                    TextColumn::make('matricula_remolque')
                                        ->label('Matrícula remolque')
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

                    TextColumn::make('matricula_cabeza')
                        ->label('Matrícula cabeza')
                        ->searchable(),

                    TextColumn::make('matricula_remolque')
                        ->label('Matrícula remolque')
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
            'index' => Pages\ListCamions::route('/'),
            'create' => Pages\CreateCamion::route('/create'),
            'view' => Pages\ViewCamion::route('/{record}'),
            'edit' => Pages\EditCamion::route('/{record}/edit'),
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
