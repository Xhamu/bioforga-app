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
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\File;
use Filament\Tables\Filters\Layout;


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
        $ubicaciones = json_decode(File::get(resource_path('data/ubicaciones.json')), true);

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
                            ->columnSpan(['default' => 3, 'lg' => 1]),

                        TextInput::make('email')
                            ->label(__('Correo electrónico'))
                            ->required()
                            ->rules('required')
                            ->autofocus()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 3, 'lg' => 1]),

                        Select::make('tipo_servicio')
                            ->label('Tipo de servicio')
                            ->searchable()
                            ->options([
                                'Logística' => 'Logística',
                                'Servicios maquinaria' => 'Servicios maquinaria',
                                'Combustible' => 'Combustible',
                                'Alojamiento' => 'Alojamiento',
                                'Otros' => 'Otros',
                            ])
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull(),

                Section::make('Dirección fiscal')
                    ->schema([
                        Select::make('pais')
                            ->label(__('País'))
                            ->options([
                                'es' => 'España',
                            ])
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        Select::make('provincia')
                            ->label('Provincia')
                            ->options(function () use ($ubicaciones) {
                                return collect($ubicaciones)
                                    ->flatMap(function ($ccaa) {
                                        return collect($ccaa['provinces'] ?? [])
                                            ->mapWithKeys(function ($prov) use ($ccaa) {
                                                return [$ccaa['code'] . '-' . $prov['code'] => $prov['label']];
                                            });
                                    });
                            })
                            ->searchable()
                            ->required()
                            ->reactive(),

                        Select::make('poblacion')
                            ->label('Población')
                            ->options(function (callable $get) use ($ubicaciones) {
                                $provKey = $get('provincia');
                                if (!str_contains($provKey, '-'))
                                    return [];

                                [$ccaaCode, $provCode] = explode('-', $provKey);

                                foreach ($ubicaciones as $ccaa) {
                                    if ($ccaa['code'] === $ccaaCode) {
                                        foreach ($ccaa['provinces'] as $provincia) {
                                            if ($provincia['code'] === $provCode) {
                                                return collect($provincia['towns'] ?? [])
                                                    ->mapWithKeys(fn($town) => [$town['label'] => $town['label']]);
                                            }
                                        }
                                    }
                                }

                                return [];
                            })
                            ->searchable()
                            ->required(),

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
        $ubicaciones = collect(json_decode(File::get(resource_path('data/ubicaciones.json')), true));

        $provincias = $ubicaciones->flatMap(function ($region) {
            return collect($region['provinces'] ?? []);
        });

        $provinciasOptions = $provincias->sortBy('label')->mapWithKeys(function ($provincia) {
            return [
                "{$provincia['parent_code']}-{$provincia['code']}" => $provincia['label'],
            ];
        })->toArray();

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
                ->filters(
                    [
                        SelectFilter::make('tipo_servicio')
                            ->label('Tipo de servicio')
                            ->options([
                                'Logística' => 'Logística',
                                'Servicios maquinaria' => 'Servicios maquinaria',
                                'Combustible' => 'Combustible',
                                'Alojamiento' => 'Alojamiento',
                                'Otros' => 'Otros',
                            ])
                            ->searchable()
                            ->placeholder('Todos'),
                    ],
                    layout: FiltersLayout::AboveContent
                )
                ->filtersFormColumns(1)
                ->actions([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                ])
                ->bulkActions([
                    Tables\Actions\BulkActionGroup::make([
                        Tables\Actions\DeleteBulkAction::make(),
                    ]),
                ])
                ->defaultSort('created_at', 'desc');
        } else {
            return $table
                ->columns([
                    TextColumn::make('razon_social')
                        ->weight(FontWeight::Bold)
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('telefono')
                        ->searchable()
                        ->icon('heroicon-m-phone'),
                    TextColumn::make('email')
                        ->searchable()
                        ->icon('heroicon-m-envelope'),
                ])
                ->filters(
                    [
                        SelectFilter::make('tipo_servicio')
                            ->label('Tipo de servicio')
                            ->options([
                                'Logística' => 'Logística',
                                'Servicios maquinaria' => 'Servicios maquinaria',
                                'Combustible' => 'Combustible',
                                'Alojamiento' => 'Alojamiento',
                                'Otros' => 'Otros',
                            ])
                            ->searchable()
                            ->placeholder('Todos'),

                        SelectFilter::make('provincia')
                            ->label('Provincia')
                            ->options($provinciasOptions)
                            ->searchable()
                            ->placeholder('Todas'),
                    ],
                    layout: FiltersLayout::AboveContent
                )
                ->filtersFormColumns(2)
                ->actions([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListProveedors::route('/'),
            'create' => Pages\CreateProveedor::route('/create'),
            'edit' => Pages\EditProveedor::route('/{record}/edit'),
        ];
    }
}
