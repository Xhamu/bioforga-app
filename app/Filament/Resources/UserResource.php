<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\Pages\ListUserActivities;
use App\Models\Maquina;
use App\Models\User;
use App\Models\Vehiculo;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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
use Illuminate\Support\HtmlString;
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
                            ->required()
                            ->rules('required')
                            ->columnSpan(1)
                            ->validationMessages([
                                'email' => 'No es :attribute válido.',
                                'required' => 'El correo electrónico es obligatorio.'
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
                            ->searchable()
                            ->options(
                                \Spatie\Permission\Models\Role::where('name', '!=', 'superadmin')->pluck('name', 'id')
                            ),

                        /*Forms\Components\Select::make('sector')
                            ->label('Sector')
                            ->searchable()
                            ->options([
                                '01' => 'Zona Norte',
                                '02' => 'Zona Sur',
                                '03' => 'Andalucía Oriental',
                                '04' => 'Andalucía Occidental',
                                '05' => 'Otros',
                            ])
                            ->visible(function ($get) {
                                return !empty($get('roles'));
                            }),*/
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
                        ->searchable(query: function ($query, $search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('apellidos', 'like', "%{$search}%");
                        }),

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

                            ]),
                    ])->collapsed(false),
                ])
                ->filters([
                    Tables\Filters\TrashedFilter::make(),
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
                        ->searchable(query: function ($query, $search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('apellidos', 'like', "%{$search}%");
                        }),

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
                ->headerActions([
                    Action::make('exportar_partes_trabajo')
                        ->label('Exportar partes de trabajo')
                        ->action(function () {
                            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\PartesTrabajoPorUsuarioExport, 'partes_trabajo.xlsx');
                        })
                        ->icon('heroicon-m-arrow-down-tray')
                        ->color('gray'),
                ])
                ->actions([
                    \STS\FilamentImpersonate\Tables\Actions\Impersonate::make()
                        ->after(function ($record) {
                            $suplantador = auth()->user();
                            $suplantado = $record;

                            activity()
                                ->causedBy($suplantador)
                                ->performedOn($suplantado)
                                ->withProperties([
                                    'impersonator_id' => $suplantador->id,
                                    'impersonator_name' => $suplantador->nombre_apellidos ?? $suplantador->name,
                                    'impersonated_id' => $suplantado->id,
                                    'impersonated_name' => $suplantado->nombre_apellidos ?? $suplantado->name,
                                    'ip' => request()->ip(),
                                    'user_agent' => request()->userAgent(),
                                ])
                                ->log('impersonation');
                        }),

                    Tables\Actions\EditAction::make(),

                    Tables\Actions\DeleteAction::make()
                        ->before(function ($record, $action) {
                            // Máquinas vinculadas
                            $maquinas = Maquina::where('operario_id', $record->id)->get();

                            if ($maquinas->isNotEmpty()) {
                                $listaMaquinas = $maquinas
                                    ->map(fn($m) => "<li><strong>{$m->marca} {$m->modelo}</strong></li>")
                                    ->implode('');

                                Notification::make()
                                    ->title('No se puede eliminar')
                                    ->danger()
                                    ->icon('heroicon-o-exclamation-circle')
                                    ->body(new HtmlString(
                                        "Este usuario está asignado como operario en las siguientes máquinas:<br><ul>{$listaMaquinas}</ul>No se puede eliminar mientras tenga estas vinculaciones."
                                    ))
                                    ->duration(10000)
                                    ->send();

                                $action->cancel();
                                return;
                            }

                            // Vehículos vinculados
                            $vehiculos = Vehiculo::whereJsonContains('conductor_habitual', (string) $record->id)->get();

                            if ($vehiculos->isNotEmpty()) {
                                $listaVehiculos = $vehiculos
                                    ->map(fn($v) => "<li><strong>{$v->marca} {$v->modelo} ({$v->matricula})</strong></li>")
                                    ->implode('');

                                Notification::make()
                                    ->title('No se puede eliminar')
                                    ->icon('heroicon-o-exclamation-circle')
                                    ->danger()
                                    ->body(new HtmlString(
                                        "Este usuario figura como conductor habitual en los siguientes vehículos:<br><ul>{$listaVehiculos}</ul>No se puede eliminar mientras esté asignado."
                                    ))
                                    ->duration(10000)
                                    ->send();

                                $action->cancel();
                            }
                        })
                        ->requiresConfirmation()
                        ->modalHeading('¿Estás seguro de que quieres eliminar este usuario?')
                        ->modalDescription('Esta acción no se puede deshacer. Asegúrate de que el usuario no tenga datos relacionados.'),

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

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->whereDoesntHave('roles', fn($query) => $query->where('name', 'superadmin'));
    }
}
