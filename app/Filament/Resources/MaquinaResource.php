<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaquinaResource\Pages;
use App\Filament\Resources\MaquinaResource\RelationManagers;
use App\Models\Maquina;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Jenssegers\Agent\Agent;

class MaquinaResource extends Resource
{
    protected static ?string $model = Maquina::class;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $navigationGroup = 'Gestión de flota';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'maquinas';
    public static ?string $label = 'máquina';
    public static ?string $pluralLabel = 'Máquinas';

    public static function form(Form $form): Form
    {

        $agent = new Agent();

        if ($agent->isMobile()) {
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

                            Select::make('tipo_trabajo')
                                ->label(__('Tipo de trabajo'))
                                ->required()
                                ->searchable()
                                ->options([
                                    'astillado' => 'Astillado',
                                    'triturado' => 'Triturado',
                                    'pretiturado' => 'Pretiturado',
                                    'saca' => 'Saca',
                                    'tala' => 'Tala',
                                    'cizallado' => 'Cizallado',
                                    'carga' => 'Carga',
                                    'transporte' => 'Transporte',
                                ])
                                ->validationMessages([
                                    'required' => 'El :attribute es obligatorio.',
                                ])
                                ->columnSpan(['default' => 2, 'lg' => 1]),

                            Select::make('operario_id')
                                ->label(__('Operario'))
                                ->required()
                                ->rules('required')
                                ->searchable()
                                ->options(function () {
                                    return \App\Models\User::all()->pluck('name', 'id')->toArray();
                                })
                                ->validationMessages([
                                    'required' => 'El :attribute es obligatorio.',
                                ])
                                ->columnSpan(['default' => 2, 'lg' => 1]),
                        ])
                        ->columns(2)
                        ->columnSpan(2),

                    Section::make('Incidencias')
                        ->schema([
                            Select::make('mantenimientos')
                                ->label(__('Posibles mantenimientos'))
                                ->searchable()
                                ->multiple()
                                ->options(function () {
                                    return \App\Models\PosibleMantenimiento::all()->pluck('nombre', 'id')->toArray();
                                })
                                ->validationMessages([
                                    'required' => 'El :attribute es obligatorio.',
                                ])
                                ->columnSpan(['default' => 2, 'lg' => 1]),

                            Select::make('averias')
                                ->label(__('Posibles averías'))
                                ->searchable()
                                ->multiple()
                                ->options(function () {
                                    return \App\Models\PosibleAveria::all()->pluck('nombre', 'id')->toArray();
                                })
                                ->validationMessages([
                                    'required' => 'El :attribute es obligatorio.',
                                ])
                                ->columnSpan(['default' => 2, 'lg' => 1]),
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

                            Header::make('documento')
                                ->label('Documento')
                                ->align(Alignment::Center)
                                ->width('150px'),

                            Header::make('observaciones')
                                ->label('Observaciones')
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

                            FileUpload::make('documento')
                                ->disk('public')
                                ->directory('itvs')
                                ->preserveFilenames()
                                ->openable()
                                ->imageEditor()
                                ->nullable()
                                ->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg']),

                            Textarea::make('observaciones')
                                ->nullable(),
                        ])
                        ->emptyLabel('Aún no se han registrado ITVs')
                        ->columnSpan('full')
                        ->defaultItems(0),
                ]);
        } else {
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

                            Select::make('tipo_trabajo')
                                ->label(__('Tipo de trabajo'))
                                ->required()
                                ->searchable()
                                ->options([
                                    'astillado' => 'Astillado',
                                    'triturado' => 'Triturado',
                                    'pretiturado' => 'Pretiturado',
                                    'saca' => 'Saca',
                                    'tala' => 'Tala',
                                    'cizallado' => 'Cizallado',
                                    'carga' => 'Carga',
                                    'transporte' => 'Transporte',
                                ])
                                ->validationMessages([
                                    'required' => 'El :attribute es obligatorio.',
                                ])
                                ->columnSpan(['default' => 2, 'lg' => 1]),

                            Select::make('operario_id')
                                ->label(__('Operario'))
                                ->required()
                                ->rules('required')
                                ->searchable()
                                ->options(function () {
                                    return \App\Models\User::whereDoesntHave('roles', function ($query) {
                                        $query->where('name', 'superadmin');
                                    })
                                        ->get()
                                        ->mapWithKeys(fn($user) => [
                                            $user->id => $user->name . ' ' . $user->apellidos
                                        ])
                                        ->toArray();
                                })
                                ->validationMessages([
                                    'required' => 'El :attribute es obligatorio.',
                                ])
                                ->columnSpan(['default' => 2, 'lg' => 1]),
                        ])
                        ->columns(2)
                        ->columnSpan(2),

                    Section::make('Rendimiento y Consumo')
                        ->schema([

                            Select::make('tipo_consumo')
                                ->label(__('Tipo de consumo'))
                                ->required()
                                ->rules('required')
                                ->options([
                                    'gasoil' => 'Gasoil',
                                    'muela' => 'Muela',
                                    'cuchilla' => 'Cuchilla',
                                ])
                                ->multiple()
                                ->validationMessages([
                                    'required' => 'El :attribute es obligatorio.',
                                ]),

                            Select::make('tipo_horas')
                                ->label(__('Tipo de horas'))
                                ->required()
                                ->rules('required')
                                ->multiple()
                                ->options([
                                    'horas_encendido' => 'Horas de encendido',
                                    'horas_rotor' => 'Horas de rotor',
                                    'horas_trabajo' => 'Horas de trabajo',
                                ])
                                ->validationMessages([
                                    'required' => 'El :attribute es obligatorio.',
                                ]),
                        ])
                        ->columns(2),

                    Section::make('Incidencias')
                        ->schema([
                            Select::make('mantenimientos')
                                ->label(__('Posibles mantenimientos'))
                                ->searchable()
                                ->multiple()
                                ->options(function () {
                                    return \App\Models\PosibleMantenimiento::all()->pluck('nombre', 'id')->toArray();
                                })
                                ->validationMessages([
                                    'required' => 'El :attribute es obligatorio.',
                                ])
                                ->columnSpan(['default' => 2, 'lg' => 1]),

                            Select::make('averias')
                                ->label(__('Posibles averías'))
                                ->searchable()
                                ->multiple()
                                ->options(function () {
                                    return \App\Models\PosibleAveria::all()->pluck('nombre', 'id')->toArray();
                                })
                                ->validationMessages([
                                    'required' => 'El :attribute es obligatorio.',
                                ])
                                ->columnSpan(['default' => 2, 'lg' => 1]),
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

                            Header::make('documento')
                                ->label('Documento')
                                ->align(Alignment::Center)
                                ->width('150px'),

                            Header::make('observaciones')
                                ->label('Observaciones')
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

                            FileUpload::make('documento')
                                ->disk('public')
                                ->directory('itvs')
                                ->preserveFilenames()
                                ->openable()
                                ->imageEditor()
                                ->nullable()
                                ->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg']),

                            Textarea::make('observaciones')
                                ->nullable(),
                        ])
                        ->emptyLabel('Aún no se han registrado ITVs')
                        ->columnSpan('full')
                        ->defaultItems(0),
                ]);
        }
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
                ])
                ->paginated(true)
                ->paginationPageOptions([50, 100, 200])
                ->defaultSort('created_at', 'desc');
        } else {
            return $table
                ->columns([
                    TextColumn::make('marca_modelo')
                        ->label('Marca - Modelo')
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
                ])
                ->paginated(true)
                ->paginationPageOptions([50, 100, 200])
                ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListMaquinas::route('/'),
            'create' => Pages\CreateMaquina::route('/create'),
            'view' => Pages\ViewMaquina::route('/{record}'),
            'edit' => Pages\EditMaquina::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $user = \Filament\Facades\Filament::auth()->user();
        $rolesPermitidos = ['superadmin', 'administración', 'administrador'];

        if (!$user->hasAnyRole($rolesPermitidos)) {
            $query->where('operario_id', $user->id);
        }

        return $query;
    }
}
