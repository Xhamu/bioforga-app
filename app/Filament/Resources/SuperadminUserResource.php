<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SuperadminUserResource\Pages;
use App\Filament\Resources\SuperadminUserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SuperadminUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Superadmin';
    protected static ?string $navigationLabel = 'Usuarios';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('superadmin');
    }

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
        return $table
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
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListSuperadminUsers::route('/'),
            'create' => Pages\CreateSuperadminUser::route('/create'),
            'edit' => Pages\EditSuperadminUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('roles', fn($query) => $query->where('name', 'superadmin'));
    }
}
