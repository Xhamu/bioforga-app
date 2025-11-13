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

        $datosVehiculo = Section::make('Datos vehículo')
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

                Select::make('tipo_horas')
                    ->label(__('Tipo de horas'))
                    //->required()
                    ->searchable()
                    ->multiple()
                    ->options([
                        'horas_encendido' => 'Horas de encendido',
                        'horas_rotor' => 'Horas de rotor',
                        'horas_trabajo' => 'Horas de trabajo',
                    ])
                    ->validationMessages([
                        'required' => 'El :attribute es obligatorio.',
                    ])
                    ->columnSpan(['default' => 2, 'lg' => 1]),

                Select::make('tipo_consumo')
                    ->label(__('Tipo de consumo'))
                    //->required()
                    ->searchable()
                    ->multiple()
                    ->options([
                        'gasoil' => 'Gasoil',
                        'cuchilla' => 'Muelas',
                        'muela' => 'Cuchillas',
                    ])
                    ->validationMessages([
                        'required' => 'El :attribute es obligatorio.',
                    ])
                    ->columnSpan(['default' => 2, 'lg' => 1]),

                Select::make('operarios')
                    ->label(__('Operarios'))
                    ->relationship(
                        'operarios',
                        'name',
                        fn($query) =>
                        $query->whereDoesntHave('roles', fn($q) => $q->where('name', 'superadmin'))
                    )
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->name} {$record->apellidos}")
                    ->multiple()
                    ->preload()
                    ->searchable()
            ])
            ->columns(2)
            ->columnSpan(2);

        $datosEspecificos = Section::make('Datos específicos')
            ->schema([
                TextInput::make('numero_bastidor')
                    ->label('Número de bastidor'),

                TextInput::make('numero_motor')
                    ->label('Número de motor'),

                TextInput::make('matricula')
                    ->label('Matrícula'),

                TextInput::make('anio_fabricacion')
                    ->label('Año de fabricación'),

                TextInput::make('color')
                    ->label('Color'),
            ])
            ->columns(2);

        $incidencias = Section::make('Incidencias')
            ->schema([
                Select::make('mantenimientos')
                    ->label(__('Posibles mantenimientos'))
                    ->searchable()
                    ->multiple()
                    ->options(fn() => \App\Models\PosibleMantenimiento::pluck('nombre', 'id')->toArray()),

                Select::make('averias')
                    ->label(__('Posibles averías'))
                    ->searchable()
                    ->multiple()
                    ->options(fn() => \App\Models\PosibleAveria::pluck('nombre', 'id')->toArray()),
            ])
            ->columns(2)
            ->columnSpan(2);

        $itvs = TableRepeater::make('itvs')
            ->relationship('itvs')
            ->label('ITVs del vehículo')
            ->addActionLabel('Añadir ITV')
            ->headers([
                Header::make('fecha')->label('Fecha')->align(Alignment::Center)->width('150px'),
                Header::make('lugar')->label('Lugar')->align(Alignment::Center)->width('150px'),
                Header::make('resultado')->label('Resultado')->align(Alignment::Center)->width('150px'),
                Header::make('documento')->label('Documento')->align(Alignment::Center)->width('150px'),
                Header::make('observaciones')->label('Observaciones')->align(Alignment::Center)->width('150px'),
            ])
            ->schema([
                DatePicker::make('fecha')->default(now())->required(),
                TextInput::make('lugar')->required(),
                Select::make('resultado')->searchable()->options([
                    'Favorable' => 'Favorable',
                    'Desfavorable' => 'Desfavorable',
                    'Negativo' => 'Negativo',
                ])->required(),
                FileUpload::make('documento')
                    ->disk('public')
                    ->directory('itvs')
                    ->preserveFilenames()
                    ->openable()
                    ->imageEditor()
                    ->nullable()
                    ->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg']),
                Textarea::make('observaciones')->nullable(),
            ])
            ->emptyLabel('Aún no se han registrado ITVs')
            ->columnSpan('full')
            ->defaultItems(0);

        return $form->schema([
            Forms\Components\Tabs::make('Tabs')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Datos vehículo')->schema([$datosVehiculo]),
                    Forms\Components\Tabs\Tab::make('Datos específicos')->schema([$datosEspecificos]),
                    Forms\Components\Tabs\Tab::make('Incidencias')->schema([$incidencias]),
                    Forms\Components\Tabs\Tab::make('ITVs')->schema([$itvs]),
                ])
                ->columnSpanFull()
                ->persistTabInQueryString(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Marca + modelo con búsqueda personalizada y copia rápida
                Tables\Columns\TextColumn::make('marca_modelo')
                    ->label('Marca - Modelo')
                    ->weight('semibold')
                    ->copyable()
                    ->limit(40)
                    // Buscamos por 'marca' o 'modelo' reales, no solo por el accessor
                    ->searchable(query: function ($query, string $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('marca', 'like', "%{$search}%")
                                ->orWhere('modelo', 'like', "%{$search}%");
                        });
                    }),

                // Tipo de trabajo como badge
                Tables\Columns\BadgeColumn::make('tipo_trabajo')
                    ->label('Tipo')
                    ->sortable()
                    ->colors([
                        'success' => 'mantenimiento',
                        'warning' => 'avería',
                        'info' => 'operación',
                    ])
                    ->formatStateUsing(fn($state) => ucfirst((string) $state))
                    ->extraAttributes(['class' => 'w-fit'])
                    ->toggleable(),

                // Año y nº de serie
                Tables\Columns\TextColumn::make('matricula')
                    ->label('Matrícula')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('numero_serie')
                    ->label('Nº serie')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->filters([
                Tables\Filters\SelectFilter::make('tipo_trabajo')
                    ->label('Tipo de trabajo')
                    ->options([
                        'mantenimiento' => 'Mantenimiento',
                        'avería' => 'Avería',
                        'operación' => 'Operación',
                    ]),

                Tables\Filters\SelectFilter::make('anio_fabricacion')
                    ->label('Año')
                    ->options(
                        fn() => \App\Models\Maquina::query()
                            ->whereNotNull('anio_fabricacion')
                            ->distinct()
                            ->orderByDesc('anio_fabricacion')
                            ->pluck('anio_fabricacion', 'anio_fabricacion')
                            ->toArray()
                    ),

                Tables\Filters\TrashedFilter::make(),
            ])->persistFiltersInSession()

            ->actions([
                Tables\Actions\EditAction::make()
                    ->icon('heroicon-o-pencil-square')
                    ->tooltip('Editar'),

                Tables\Actions\DeleteAction::make()
                    ->icon('heroicon-o-trash'),

                Tables\Actions\RestoreAction::make()
                    ->visible(fn($record) => method_exists($record, 'trashed') && $record->trashed()),

                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn($record) => method_exists($record, 'trashed') && $record->trashed()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])

            // Click en fila abre la acción de edición (sin rutas)
            ->recordAction('edit')

            ->paginated(true)
            ->paginationPageOptions([25, 50, 100, 200])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading('Aún no hay máquinas')
            ->emptyStateDescription('Crea tu primera máquina para empezar.')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()->label('Crear máquina'),
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
            $query->whereHas('operarios', fn($q) => $q->where('users.id', $user->id));
        }

        return $query;
    }
}
