<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\Pages\ListUserActivities;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Jenssegers\Agent\Agent;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $slug = 'usuarios';
    public static ?string $label = 'usuario';
    public static ?string $pluralLabel = 'Usuarios';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos personales')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Nombre'))
                            ->rules('required')
                            ->required()
                            ->columnSpan(1)
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ]),

                        TextInput::make('apellidos')
                            ->label(__('Apellidos'))
                            ->rules('required')
                            ->required()
                            ->columnSpan(2)
                            ->validationMessages([
                                'required' => 'Los :attribute son obligatorios.',
                            ]),

                        TextInput::make('nif')
                            ->label(__('NIF'))
                            ->rules('required')
                            ->required()
                            ->columnSpan(1)
                            ->validationMessages([
                                'required' => 'El NIF es obligatorio.',
                            ]),

                        TextInput::make('email')
                            ->label(__('Correo electrónico'))
                            ->email()
                            ->columnSpan(1)
                            ->validationMessages([
                                'email' => 'No es :attribute válido.',
                            ]),

                        TextInput::make('telefono')
                            ->label('Teléfono')
                            ->nullable()
                            ->columnSpan(1),
                    ])
                    ->columns([
                        'sm' => 1,
                        'md' => 3,
                    ]),

                Forms\Components\Section::make('Empresa')
                    ->schema([
                        Checkbox::make('empresa_bioforga')
                            ->label('BIOFORGA')
                            ->default(true)
                            ->reactive(),

                        Select::make('proveedor_id')
                            ->label('Otro/a')
                            ->searchable()
                            ->preload()
                            ->options(function () {
                                return \App\Models\Proveedor::all()->pluck('razon_social', 'id')->toArray();
                            })
                            ->visible(fn($get) => !$get('empresa_bioforga')),
                    ])
                    ->columns([
                        'sm' => 1,
                        'md' => 2,
                    ]),

                Forms\Components\Section::make('Acceso')
                    ->schema([
                        TextInput::make('password')
                            ->label(__('Contraseña'))
                            ->password()
                            ->rules(function ($get) {
                                return $get('id') ? 'nullable' : 'required';
                            })
                            ->default(function ($get) {
                                return $get('password') ? '******' : '';
                            })
                            ->autocomplete('new-password')
                            ->validationMessages([
                                'required' => 'La :attribute es obligatoria.',
                            ]),

                        Forms\Components\Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {

        $agent = new Agent();

        if ($agent->isMobile()) {
            return $table
                ->defaultGroup('proveedor.razon_social')
                ->columns([
                    TextColumn::make('nombre_apellidos')
                        ->label('Nombre')
                        ->weight(FontWeight::Bold)
                        ->searchable()
                        ->sortable(),

                    Panel::make([
                        Grid::make(['default' => 1, 'md' => 2]) // 1 columna en móvil, 2 en pantallas medianas y grandes
                            ->schema([
                                Stack::make([
                                    TextColumn::make('nif')
                                        ->label('NIF')
                                        ->searchable()
                                        ->icon('heroicon-m-identification'),
                                    TextColumn::make('telefono')
                                        ->label('Teléfono')
                                        ->searchable()
                                        ->icon('heroicon-m-phone'),
                                ]),

                                /*Stack::make([
                                    TextColumn::make('proveedor.razon_social')
                                        ->label('Razón Social')
                                        ->icon('heroicon-m-user'),
                                    TextColumn::make('proveedor.email')
                                        ->label('Correo electrónico')
                                        ->icon('heroicon-m-envelope'),
                                ]),*/
                            ]),
                    ])->collapsed(false),
                ])
                ->filters([
                    //
                ])
                ->actions([
                    \STS\FilamentImpersonate\Tables\Actions\Impersonate::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
                ->bulkActions([
                    Tables\Actions\BulkActionGroup::make([
                        Tables\Actions\DeleteBulkAction::make(),
                    ]),
                ])
                ->defaultSort('created_at', 'desc');
        } else {
            return $table
                ->defaultGroup('proveedor.razon_social')
                ->columns([
                    TextColumn::make('nombre_apellidos')
                        ->label('Nombre')
                        ->weight(FontWeight::Bold)
                        ->searchable(),

                    TextColumn::make('nif')
                        ->label('NIF')
                        ->searchable()
                        ->icon('heroicon-m-identification'),

                    TextColumn::make('telefono')
                        ->label('Teléfono')
                        ->searchable()
                        ->icon('heroicon-m-phone'),
                ])
                ->filters([
                    //
                ])
                ->actions([
                    \STS\FilamentImpersonate\Tables\Actions\Impersonate::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Action::make('activities')
                        ->label('Actividad')
                        ->icon('heroicon-o-clock')
                        ->url(fn($record) => static::getUrl('activities', ['record' => $record]))
                        ->openUrlInNewTab()
                        ->visible(fn() => auth()->user()?->hasRole('superadmin')),
                ])
                ->bulkActions([
                    Tables\Actions\BulkActionGroup::make([
                        Tables\Actions\DeleteBulkAction::make(),
                    ]),
                ])
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            'activities' => ListUserActivities::route('/{record}/activities'),
        ];
    }
}
