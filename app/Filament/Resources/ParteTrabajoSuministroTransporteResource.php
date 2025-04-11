<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoSuministroTransporteResource\Pages;
use App\Filament\Resources\ParteTrabajoSuministroTransporteResource\RelationManagers;
use App\Models\ParteTrabajoSuministroTransporte;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\View;
use Filament\Support\Enums\Alignment;

class ParteTrabajoSuministroTransporteResource extends Resource
{
    protected static ?string $model = ParteTrabajoSuministroTransporte::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Partes de trabajo';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'partes-trabajo-suministro-transporte';
    public static ?string $label = 'suministro del transportista';
    public static ?string $pluralLabel = 'Suministros del transportista';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos del Transporte')
                    ->schema([
                        Select::make('usuario_id')
                            ->relationship('usuario', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('camion_id')
                            ->relationship('camion', 'matricula_cabeza')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),

                Section::make('Cargas realizadas')
                    ->schema([
                        TableRepeater::make('cargas')
                            ->relationship('cargas') // asegúrate que la relación se llama así en el modelo
                            ->label('')
                            ->addActionLabel('Añadir carga')
                            ->headers([
                                Header::make('referencia_id')
                                    ->label('Referencia')
                                    ->align(Alignment::Center)
                                    ->width('200px'),

                                Header::make('fecha_hora_inicio_carga')
                                    ->label('Inicio carga')
                                    ->align(Alignment::Center)
                                    ->width('200px'),

                                Header::make('gps_inicio_carga')
                                    ->label('GPS inicio')
                                    ->align(Alignment::Center)
                                    ->width('200px'),

                                Header::make('fecha_hora_fin_carga')
                                    ->label('Fin carga')
                                    ->align(Alignment::Center)
                                    ->width('200px'),

                                Header::make('gps_fin_carga')
                                    ->label('GPS fin')
                                    ->align(Alignment::Center)
                                    ->width('200px'),

                                Header::make('cantidad')
                                    ->label('Cantidad (m³)')
                                    ->align(Alignment::Center)
                                    ->width('150px'),
                            ])
                            ->schema([
                                Select::make('referencia_id')
                                    ->relationship('referencia', 'referencia')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                DateTimePicker::make('fecha_hora_inicio_carga')
                                    ->label('Inicio de la carga'),
                                TextInput::make('gps_inicio_carga')
                                    ->label('Posición inicio carga')
                                    ->suffixAction(
                                        Action::make('Capturar')
                                            ->icon('heroicon-m-map-pin')
                                            ->action('capturarGpsInicio')
                                    ),

                                DateTimePicker::make('fecha_hora_fin_carga')
                                    ->label('Fin de la carga'),

                                TextInput::make('gps_fin_carga')
                                    ->label('Posición fin carga'),

                                TextInput::make('cantidad')
                                    ->label('Cantidad (m³)')
                                    ->numeric(),
                            ])
                            ->emptyLabel('Aún no se han registrado cargas')
                            ->columnSpan('full')
                            ->defaultItems(0),
                    ]),

                Section::make('Datos de Descarga')
                    ->schema([
                        Select::make('cliente_id')
                            ->relationship('cliente', 'razon_social')
                            ->searchable()
                            ->preload(),

                        Select::make('tipo_biomasa')
                            ->options([
                                'pino' => 'Pino',
                                'eucalipto' => 'Eucalipto',
                                'acacia' => 'Acacia',
                                'frondosa' => 'Frondosa',
                                'otros' => 'Otros',
                            ])
                            ->searchable(),

                        TextInput::make('cantidad_total')
                            ->label('Cantidad total (m³)')
                            ->numeric()
                            ->disabled()
                            ->helperText('Se calculará automáticamente sumando las cargas.'),

                        FileUpload::make('albaran')
                            ->label('Foto del albarán')
                            ->disk('public')
                            ->directory('albaranes'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),

                Section::make('Observaciones')
                    ->schema([
                        Textarea::make('observaciones')
                            ->rows(4)
                            ->maxLength(1000),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
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
            'index' => Pages\ListParteTrabajoSuministroTransportes::route('/'),
            'create' => Pages\CreateParteTrabajoSuministroTransporte::route('/create'),
            'view' => Pages\ViewParteTrabajoSuministroTransporte::route('/{record}'),
            'edit' => Pages\EditParteTrabajoSuministroTransporte::route('/{record}/edit'),
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
