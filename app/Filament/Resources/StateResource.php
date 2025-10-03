<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StateResource\Pages;
use App\Models\State;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Str;

class StateResource extends Resource
{
    protected static ?string $model = State::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';
    protected static ?string $navigationGroup = 'Maestros';
    protected static ?string $pluralModelLabel = 'Estados';
    protected static ?string $modelLabel = 'Estado';
    protected static ?string $slug = 'estados';
    protected static ?int $navigationSort = 50;

    /** 🔒 Ocultar del menú si no tiene rol permitido */
    public static function shouldRegisterNavigation(): bool
    {
        $user = Filament::auth()->user();
        return $user?->hasAnyRole(['superadmin', 'administración']) ?? false;
    }

    public static function form(Form $form): Form
    {
        // Opciones con chips HTML (opcional)
        $colorOptionsHtml = [
            'primary' => '<span class="inline-flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-indigo-600"></span>Primario</span>',
            'success' => '<span class="inline-flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-green-600"></span>Éxito (Verde)</span>',
            'danger' => '<span class="inline-flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-red-600"></span>Peligro (Rojo)</span>',
            'warning' => '<span class="inline-flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-amber-500"></span>Advertencia (Amarillo)</span>',
            'info' => '<span class="inline-flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-blue-600"></span>Info (Azul)</span>',
            'gray' => '<span class="inline-flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-gray-500"></span>Gris</span>',
        ];

        return $form
            ->schema([
                Forms\Components\Section::make('Identificación')
                    ->description('Datos base de la categoría/botón')
                    ->icon('heroicon-m-identification')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                $set('slug', Str::slug((string) $state));
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->helperText('Se genera automáticamente a partir del nombre.')
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(function (?string $state, Get $get) {
                                return Str::slug((string) $get('name'));
                            }),
                    ]),

                Forms\Components\Section::make('Apariencia')
                    ->description('Color e icono a mostrar')
                    ->icon('heroicon-m-swatch')
                    ->columns(2)
                    ->schema([
                        // Color SEMÁNTICO para componentes Filament (botones, etc.)
                        Forms\Components\Select::make('color')
                            ->label('Color (tema)')
                            ->options($colorOptionsHtml)
                            ->allowHtml()
                            ->searchable()
                            ->preload()
                            ->hint('Usado por botones de Filament (primary/success/etc.)'),

                        // Icono nativo + preview en vivo
                        Forms\Components\Select::make('icon')
                            ->label('Icono')
                            ->options(self::heroiconOptions())
                            ->searchable()
                            ->preload()
                            ->live() // <- para que el suffix se actualice sin guardar
                            ->suffixIcon(fn(Get $get) => $get('icon') ?: null)
                            ->helperText('Heroicons v2 mini (sin plugin).'),
                    ]),

                Forms\Components\Section::make('Estado y orden')
                    ->icon('heroicon-m-adjustments-horizontal')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0)
                            ->helperText('Orden de los botones en el dashboard.'),
                    ]),
            ]);
    }

    protected static function heroiconOptions(): array
    {
        return [
            'heroicon-m-home' => 'Home',
            'heroicon-m-user' => 'User',
            'heroicon-m-bell' => 'Campana',
            'heroicon-m-cog-6-tooth' => 'Configuración',
            'heroicon-m-chart-bar' => 'Gráfico',
            'heroicon-m-clipboard' => 'Clipboard',
            'heroicon-m-folder' => 'Carpeta',
            'heroicon-m-eye' => 'Ver',
            // añade los que necesites
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('color')
                    ->label('Color'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Solo activos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStates::route('/'),
            'create' => Pages\CreateState::route('/create'),
            'edit' => Pages\EditState::route('/{record}/edit'),
        ];
    }
}
