<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReferenciaResource\Pages;
use App\Filament\Resources\ReferenciaResource\RelationManagers;
use App\Models\Referencia;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\View;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Panel;
use Jenssegers\Agent\Agent;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Radio;

class ReferenciaResource extends Resource
{
    protected static ?string $model = Referencia::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'Parcelas';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'referencias';
    protected static ?string $pluralLabel = 'Referencias';
    protected static ?string $label = 'referencia';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                View::make('livewire.tipo-select')
                    ->visible(function ($state) {
                        return !isset($state['id']);
                    })
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('referencia')
                    ->required()
                    ->reactive()
                    ->columnSpanFull(),

                Forms\Components\Select::make('tipo_servicio')
                    ->required()
                    ->searchable()
                    ->options([
                        'Astillado Suelo' => 'Astillado Suelo',
                        'Astillado Camión' => 'Astillado Camión',
                        'Triturado Suelo' => 'Triturado Suelo',
                        'Triturado Camión' => 'Triturado Camión',
                        'Saca autocargador' => 'Saca autocargador',
                    ])
                    ->columnSpanFull()
                    ->visible(function ($get) {
                        return !empty($get('referencia')) && strpos($get('referencia'), 'SU') === false;
                    }),

                Forms\Components\Select::make('formato')
                    ->nullable()
                    ->options([
                        'CA' => 'Cargadero',
                        'SA' => 'Saca',
                        'EX' => 'Explotación',
                        'OT' => 'Otros',
                    ])
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpanFull()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if ($state) {
                            $referencia = $get('referencia') ?? '';

                            preg_match('/^(?<sector>\d{2})SU(?:CA|SA|EX|OT)?(?<fecha>\d{6})(?<contador>\d{3})$/', $referencia, $matches);

                            $sector = $matches['sector'] ?? '01';
                            $fecha = $matches['fecha'] ?? now()->format('dmy');
                            $contador = $matches['contador'] ?? '01';

                            $contadorInt = (int) $contador;

                            do {
                                $contadorFormateado = str_pad($contadorInt, 3, '0', STR_PAD_LEFT);
                                $nuevaReferencia = $sector . 'SU' . $state . $fecha . $contadorFormateado;

                                $existe = Referencia::where('referencia', $nuevaReferencia)->exists();

                                $contadorInt++;
                            } while ($existe);

                            $set('referencia', $nuevaReferencia);
                        } else {
                            $set('referencia', '');
                        }
                    })
                    ->visible(function ($get) {
                        return str_contains($get('referencia'), 'SU');
                    }),

                Forms\Components\Section::make('Ubicación')
                    ->schema([
                        Forms\Components\TextInput::make('provincia')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $referenciaActual = $get('referencia') ?? '';

                                // Solo generamos si NO es tipo suministro
                                if (str_contains($referenciaActual, 'SU')) {
                                    return;
                                }

                                $provincia = strtoupper(substr($get('provincia') ?? '', 0, 2));
                                $ayuntamiento = strtoupper(substr($get('ayuntamiento') ?? '', 0, 2));
                                $fecha = now()->format('dmy');

                                $contador = 1;
                                do {
                                    $contadorStr = str_pad($contador, 3, '0', STR_PAD_LEFT);
                                    $nuevaReferencia = "{$provincia}{$ayuntamiento}{$fecha}{$contadorStr}";

                                    $existe = Referencia::where('referencia', $nuevaReferencia)->exists();
                                    $contador++;
                                } while ($existe);

                                $set('referencia', $nuevaReferencia);
                            }),

                        Forms\Components\TextInput::make('ayuntamiento')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $referenciaActual = $get('referencia') ?? '';

                                if (str_contains($referenciaActual, 'SU')) {
                                    return;
                                }

                                $provincia = strtoupper(substr($get('provincia') ?? '', 0, 2));
                                $ayuntamiento = strtoupper(substr($get('ayuntamiento') ?? '', 0, 2));
                                $fecha = now()->format('dmy');

                                $contador = 1;
                                do {
                                    $contadorStr = str_pad($contador, 3, '0', STR_PAD_LEFT);
                                    $nuevaReferencia = "{$provincia}{$ayuntamiento}{$fecha}{$contadorStr}";

                                    $existe = Referencia::where('referencia', $nuevaReferencia)->exists();
                                    $contador++;
                                } while ($existe);

                                $set('referencia', $nuevaReferencia);
                            }),
                        Forms\Components\TextInput::make('monte_parcela')
                            ->label('Monte / Parcela')
                            ->required(),
                        Forms\Components\TextInput::make('ubicacion_gps')
                            ->label('GPS'),
                        Forms\Components\TextInput::make('finca')
                            ->label('Finca')
                            ->visible(function ($get) {
                                return !empty($get('referencia')) && strpos($get('referencia'), 'SU') === false;
                            })
                            ->columnSpanFull(),
                        Forms\Components\Select::make('sector')
                            ->label('Sector')
                            ->searchable()
                            ->options([
                                '01' => 'Zona Norte',
                                '02' => 'Zona Sur',
                                '03' => 'Andalucía Oriental',
                                '04' => 'Andalucía Occidental',
                                '05' => 'Otros',
                            ])
                            ->columnSpanFull()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $referencia = $get('referencia') ?? '';

                                $referencia = preg_replace('/^(01|02|03|04|05)/', '', $referencia);

                                $set('referencia', $state . $referencia);
                            })
                            ->visible(function ($get) {
                                return !empty($get('referencia'));
                            }),
                        View::make('livewire.get-location-button')
                            ->visible(function ($state) {
                                return !isset($state['id']);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Intervinientes')
                    ->schema([
                        Forms\Components\Select::make('proveedor_id')
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->relationship(
                                'proveedor',
                                'razon_social',
                                fn(Builder $query) => $query->whereIn('tipo_servicio', ['suministro', 'servicios'])
                            )
                            ->visible(function ($get) {
                                return strpos($get('referencia'), 'SU') !== false;
                            }),

                        Forms\Components\Select::make('cliente_id')
                            ->nullable()
                            ->searchable()
                            ->preload()
                            ->relationship('cliente', 'razon_social')
                            ->visible(function ($get) {
                                return strpos($get('referencia'), 'SU') === false;
                            }),
                    ])
                    ->visible(function ($get) {
                        return !empty($get('referencia'));
                    })
                    ->columns(1),

                Forms\Components\Section::make('Producto')
                    ->schema([
                        Forms\Components\Select::make('producto_especie')
                            ->label('Especie')
                            ->searchable()
                            ->options([
                                'pino' => 'Pino',
                                'eucalipto' => 'Eucalipto',
                                'acacia' => 'Acacia',
                                'frondosa' => 'Frondosa',
                                'otros' => 'Otros',
                            ])
                            ->required(),
                        Forms\Components\Select::make('producto_tipo')
                            ->label('Tipo')
                            ->searchable()
                            ->options([
                                'entero' => 'Entero',
                                'troza' => 'Troza',
                                'tacos' => 'Tacos',
                                'puntal' => 'Puntal',
                                'copas' => 'Copas',
                                'rama' => 'Rama',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('cantidad_aprox')
                            ->label('Cantidad (aprox.)')
                            ->numeric()
                            ->required(),

                        Forms\Components\Checkbox::make('certificable')
                            ->label('¿Certificable?')
                            ->reactive(),

                        Forms\Components\Select::make('tipo_certificacion')
                            ->label('Tipo de certificación')
                            ->searchable()
                            ->options([
                                'sure_induestrial' => 'SURE - Industrial',
                                'sure_foresal' => 'SURE - Forestal',
                                'sbp' => 'SBP',
                                'pefc' => 'PEFC',
                            ])
                            ->visible(fn($get) => $get('certificable') === true)
                            ->reactive(),

                        Forms\Components\Checkbox::make('guia_sanidad')
                            ->label('¿Guía de sanidad?')
                            ->reactive(),

                    ])->columns(3)
                    ->visible(function ($get) {
                        return !empty($get('referencia'));
                    }),

                Forms\Components\Section::make('Tarifa')
                    ->schema([
                        Forms\Components\Select::make('tarifa')
                            ->label('Tarifa')
                            ->options([
                                'toneladas' => 'Toneladas',
                                'm3' => 'Metros cúbicos',
                            ])
                            ->searchable()
                            ->nullable()
                            ->reactive(), // necesario para reactividad

                        Forms\Components\TextInput::make('precio')
                            ->label('Precio')
                            ->numeric()
                            ->nullable()
                            ->reactive()
                            ->suffix(fn(callable $get) => match ($get('tarifa')) {
                                'toneladas' => '€/tonelada',
                                'm3' => '€/m³',
                                default => '€',
                            }),
                    ])->columns(2)
                    ->visible(function ($get) {
                        return !empty($get('referencia'));
                    }),

                Forms\Components\Section::make('Contacto')
                    ->schema([
                        Forms\Components\TextInput::make('contacto_nombre')
                            ->label('Nombre')
                            ->nullable(),
                        Forms\Components\TextInput::make('contacto_telefono')
                            ->label('Teléfono')
                            ->nullable(),
                        Forms\Components\TextInput::make('contacto_email')
                            ->label('Correo electrónico')
                            ->nullable(),
                    ])->columns(3)
                    ->visible(function ($get) {
                        return !empty($get('referencia'));
                    }),

                Forms\Components\Select::make('usuarios')
                    ->label('Usuarios relacionados')
                    ->multiple()
                    ->relationship(
                        name: 'usuarios',
                        titleAttribute: 'name', // columna real
                        modifyQueryUsing: fn($query) =>
                        $query->orderBy('name')
                            ->whereNull('users.deleted_at')
                            ->whereDoesntHave(
                                'roles',
                                fn($q) =>
                                $q->whereIn('name', ['superadmin'])
                            )
                    )
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return $record?->nombre_apellidos ?? '-';
                    })
                    ->preload()
                    ->searchable()
                    ->columnSpanFull()
                    ->visible(fn($get) => !empty($get('referencia'))),

                Forms\Components\Section::make('Estado')
                    ->schema([
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->searchable()
                            ->options([
                                'abierto' => 'Abierto',
                                'cerrado' => 'Cerrado',
                                'en_proceso' => 'En proceso',
                            ])
                            ->required(),

                        Forms\Components\Select::make('en_negociacion')
                            ->label('En negociación')
                            ->searchable()
                            ->options([
                                'confirmado' => 'Confirmado',
                                'sin_confirmar' => 'Sin confirmar',
                            ])
                            ->required(),

                        Forms\Components\Textarea::make('observaciones')
                            ->nullable(),
                    ])->columns(1)
                    ->visible(function ($get) {
                        return !empty($get('referencia'));
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {

        $agent = new Agent();

        if ($agent->isMobile()) {
            return $table
                ->columns([
                    TextColumn::make('referencia')
                        ->label('Referencia')
                        ->weight(FontWeight::Bold)
                        ->searchable(),

                    Panel::make([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                Stack::make([
                                    TextColumn::make('ayuntamiento')
                                        ->label('Municipio (Monte)')
                                        ->icon('heroicon-m-map-pin')
                                        ->formatStateUsing(function ($state, $record) {
                                            $monte = $record->monte_parcela;
                                            return $state . ($monte ? " ($monte)" : '');
                                        }),

                                    TextColumn::make('interviniente')
                                        ->label('Interviniente')
                                        ->icon('heroicon-m-building-office'),
                                ]),
                            ]),
                    ])->collapsed(false),
                ])
                ->filters([
                    Tables\Filters\TrashedFilter::make(),
                ])
                ->headerActions([
                    Action::make('exportar_balance_masas')
                        ->label('Balance de Masas')
                        ->icon('heroicon-m-document-arrow-down')
                        ->color('gray')
                        ->action(function () {
                            $hayDatos = \App\Models\CargaTransporte::exists();

                            if (!$hayDatos) {
                                Notification::make()
                                    ->title('Sin datos')
                                    ->body('No hay cargas registradas para exportar.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $filename = 'balance-de-masas-' . now()->format('Y-m-d') . '.xlsx';
                            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\BalanceDeMasasExport, $filename);
                        })
                        ->after(function () {
                            Notification::make()
                                ->title('Exportación iniciada')
                                ->body('El archivo "Balance de Masas" se está descargando.')
                                ->success()
                                ->send();
                        }),

                    Action::make('exportar_excel')
                        ->label('Exportar a Excel')
                        ->color('gray')
                        ->modalWidth('xl')
                        ->modalSubmitActionLabel('Exportar')
                        ->modalCancelActionLabel('Cerrar')
                        ->form([
                            Select::make('tipo_exportacion')
                                ->label('Selecciona una opción')
                                ->options([
                                    'suministro' => 'Suministro',
                                    'servicio' => 'Servicio',
                                ])
                                ->searchable()
                                ->required(),

                            TextInput::make('nombre_archivo')
                                ->label('Nombre del archivo')
                                ->default('referencias-' . now()->format('Y-m-d'))
                                ->required(),
                        ])
                        ->action(function (array $data) {
                            $tipo = $data['tipo_exportacion'];
                            $nombre = $data['nombre_archivo'] . '.xlsx';

                            return redirect()->route('referencias.export', [
                                'tipo' => $tipo,
                                'nombre' => $nombre,
                            ]);
                        })
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
                ->defaultSort('created_at', 'desc');
        } else {
            return $table
                ->columns([
                    TextColumn::make('referencia')
                        ->label('Referencia')
                        ->weight(FontWeight::Bold)
                        ->searchable(),

                    TextColumn::make('ayuntamiento')
                        ->label('Municipio (Monte)')
                        ->icon('heroicon-m-map-pin')
                        ->formatStateUsing(function ($state, $record) {
                            $monte = $record->monte_parcela;
                            return $state . ($monte ? " ($monte)" : '');
                        }),

                    TextColumn::make('interviniente')
                        ->label('Interviniente')
                        ->icon('heroicon-m-building-office'),

                    TextColumn::make('estado_mostrar')
                        ->label('Estado'),
                ])
                ->filters([
                    Tables\Filters\TrashedFilter::make(),
                ])
                ->headerActions([
                    Action::make('exportar_balance_masas')
                        ->label('Balance de Masas')
                        ->icon('heroicon-m-document-arrow-down')
                        ->color('gray')
                        ->action(function () {
                            $hayDatos = \App\Models\CargaTransporte::exists();

                            if (!$hayDatos) {
                                Notification::make()
                                    ->title('Sin datos')
                                    ->body('No hay cargas registradas para exportar.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $filename = 'balance-de-masas-' . now()->format('Y-m-d') . '.xlsx';
                            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\BalanceDeMasasExport, $filename);
                        })
                        ->after(function () {
                            Notification::make()
                                ->title('Exportación iniciada')
                                ->body('El archivo "Balance de Masas" se está descargando.')
                                ->success()
                                ->send();
                        }),

                    Action::make('exportar_excel')
                        ->label('Exportar a Excel')
                        ->color('gray')
                        ->modalWidth('xl')
                        ->modalSubmitActionLabel('Exportar')
                        ->modalCancelActionLabel('Cerrar')
                        ->form([
                            Select::make('tipo_exportacion')
                                ->label('Selecciona una opción')
                                ->options([
                                    'suministro' => 'Suministro',
                                    'servicio' => 'Servicio',
                                ])
                                ->searchable()
                                ->required(),

                            TextInput::make('nombre_archivo')
                                ->label('Nombre del archivo')
                                ->default('referencias-' . now()->format('Y-m-d'))
                                ->required(),
                        ])
                        ->action(function (array $data) {
                            $tipo = $data['tipo_exportacion'];
                            $nombre = $data['nombre_archivo'] . '.xlsx';

                            return redirect()->route('referencias.export', [
                                'tipo' => $tipo,
                                'nombre' => $nombre,
                            ]);
                        })
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
            'index' => Pages\ListReferencias::route('/'),
            'create' => Pages\CreateReferencia::route('/create'),
            'edit' => Pages\EditReferencia::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(
                !auth()->user()->hasAnyRole(['superadmin', 'administración', 'administrador']),
                fn($query) => $query->whereHas(
                    'usuarios',
                    fn($q) =>
                    $q->where('users.id', auth()->id())
                )
            )
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function generalFormSchema(): array
    {
        return [
            View::make('livewire.tipo-select')
                ->visible(function ($state) {
                    return !isset($state['id']);
                })
                ->columnSpanFull(),

            Forms\Components\TextInput::make('referencia')
                ->required()
                ->reactive()
                ->columnSpanFull(),

            Forms\Components\Select::make('tipo_servicio')
                ->required()
                ->searchable()
                ->options([
                    'Astillado Suelo' => 'Astillado Suelo',
                    'Astillado Camión' => 'Astillado Camión',
                    'Triturado Suelo' => 'Triturado Suelo',
                    'Triturado Camión' => 'Triturado Camión',
                    'Saca autocargador' => 'Saca autocargador',
                ])
                ->columnSpanFull()
                ->visible(function ($get) {
                    return !empty($get('referencia')) && strpos($get('referencia'), 'SU') === false;
                }),

            Forms\Components\Select::make('formato')
                ->nullable()
                ->options([
                    'CA' => 'Cargadero',
                    'SA' => 'Saca',
                    'EX' => 'Explotación',
                    'OT' => 'Otros',
                ])
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull()
                ->live()
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    if ($state) {
                        $referencia = $get('referencia') ?? '';

                        preg_match('/^(?<sector>\d{2})SU(?:CA|SA|EX|OT)?(?<fecha>\d{6})(?<contador>\d{3})$/', $referencia, $matches);

                        $sector = $matches['sector'] ?? '01';
                        $fecha = $matches['fecha'] ?? now()->format('dmy');
                        $contador = $matches['contador'] ?? '01';

                        $contadorInt = (int) $contador;

                        do {
                            $contadorFormateado = str_pad($contadorInt, 3, '0', STR_PAD_LEFT);
                            $nuevaReferencia = $sector . 'SU' . $state . $fecha . $contadorFormateado;

                            $existe = Referencia::where('referencia', $nuevaReferencia)->exists();

                            $contadorInt++;
                        } while ($existe);

                        $set('referencia', $nuevaReferencia);
                    } else {
                        $set('referencia', '');
                    }
                })
                ->visible(function ($get) {
                    return str_contains($get('referencia'), 'SU');
                }),

            Forms\Components\Section::make('Ubicación')
                ->schema([
                    Forms\Components\TextInput::make('provincia')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $referenciaActual = $get('referencia') ?? '';

                            // Solo generamos si NO es tipo suministro
                            if (str_contains($referenciaActual, 'SU')) {
                                return;
                            }

                            $provincia = strtoupper(substr($get('provincia') ?? '', 0, 2));
                            $ayuntamiento = strtoupper(substr($get('ayuntamiento') ?? '', 0, 2));
                            $fecha = now()->format('dmy');

                            $contador = 1;
                            do {
                                $contadorStr = str_pad($contador, 3, '0', STR_PAD_LEFT);
                                $nuevaReferencia = "{$provincia}{$ayuntamiento}{$fecha}{$contadorStr}";

                                $existe = Referencia::where('referencia', $nuevaReferencia)->exists();
                                $contador++;
                            } while ($existe);

                            $set('referencia', $nuevaReferencia);
                        }),

                    Forms\Components\TextInput::make('ayuntamiento')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $referenciaActual = $get('referencia') ?? '';

                            if (str_contains($referenciaActual, 'SU')) {
                                return;
                            }

                            $provincia = strtoupper(substr($get('provincia') ?? '', 0, 2));
                            $ayuntamiento = strtoupper(substr($get('ayuntamiento') ?? '', 0, 2));
                            $fecha = now()->format('dmy');

                            $contador = 1;
                            do {
                                $contadorStr = str_pad($contador, 3, '0', STR_PAD_LEFT);
                                $nuevaReferencia = "{$provincia}{$ayuntamiento}{$fecha}{$contadorStr}";

                                $existe = Referencia::where('referencia', $nuevaReferencia)->exists();
                                $contador++;
                            } while ($existe);

                            $set('referencia', $nuevaReferencia);
                        }),
                    Forms\Components\TextInput::make('monte_parcela')
                        ->label('Monte / Parcela')
                        ->required(),
                    Forms\Components\TextInput::make('ubicacion_gps')
                        ->label('GPS'),
                    Forms\Components\TextInput::make('finca')
                        ->label('Finca')
                        ->visible(function ($get) {
                            return !empty($get('referencia')) && strpos($get('referencia'), 'SU') === false;
                        })
                        ->columnSpanFull(),
                    Forms\Components\Select::make('sector')
                        ->label('Sector')
                        ->searchable()
                        ->options([
                            '01' => 'Zona Norte',
                            '02' => 'Zona Sur',
                            '03' => 'Andalucía Oriental',
                            '04' => 'Andalucía Occidental',
                            '05' => 'Otros',
                        ])
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            $referencia = $get('referencia') ?? '';

                            $referencia = preg_replace('/^(01|02|03|04|05)/', '', $referencia);

                            $set('referencia', $state . $referencia);
                        })
                        ->visible(function ($get) {
                            return !empty($get('referencia'));
                        }),
                    View::make('livewire.get-location-button')
                        ->visible(function ($state) {
                            return !isset($state['id']);
                        })
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('Intervinientes')
                ->schema([
                    Forms\Components\Select::make('proveedor_id')
                        ->nullable()
                        ->searchable()
                        ->preload()
                        ->relationship(
                            'proveedor',
                            'razon_social',
                            fn(Builder $query) => $query->whereIn('tipo_servicio', ['suministro', 'servicios'])
                        )
                        ->visible(function ($get) {
                            return strpos($get('referencia'), 'SU') !== false;
                        }),

                    Forms\Components\Select::make('cliente_id')
                        ->nullable()
                        ->searchable()
                        ->preload()
                        ->relationship('cliente', 'razon_social')
                        ->visible(function ($get) {
                            return strpos($get('referencia'), 'SU') === false;
                        }),
                ])
                ->visible(function ($get) {
                    return !empty($get('referencia'));
                })
                ->columns(1),

            Forms\Components\Section::make('Producto')
                ->schema([
                    Forms\Components\Select::make('producto_especie')
                        ->label('Especie')
                        ->searchable()
                        ->options([
                            'pino' => 'Pino',
                            'eucalipto' => 'Eucalipto',
                            'acacia' => 'Acacia',
                            'frondosa' => 'Frondosa',
                            'otros' => 'Otros',
                        ])
                        ->required(),
                    Forms\Components\Select::make('producto_tipo')
                        ->label('Tipo')
                        ->searchable()
                        ->options([
                            'entero' => 'Entero',
                            'troza' => 'Troza',
                            'tacos' => 'Tacos',
                            'puntal' => 'Puntal',
                            'copas' => 'Copas',
                            'rama' => 'Rama',
                        ])
                        ->required(),

                    Forms\Components\TextInput::make('cantidad_aprox')
                        ->label('Cantidad (aprox.)')
                        ->numeric()
                        ->required(),

                    Forms\Components\Checkbox::make('certificable')
                        ->label('¿Certificable?')
                        ->reactive(),

                    Forms\Components\Select::make('tipo_certificacion')
                        ->label('Tipo de certificación')
                        ->searchable()
                        ->options([
                            'sure_induestrial' => 'SURE - Industrial',
                            'sure_foresal' => 'SURE - Forestal',
                            'sbp' => 'SBP',
                            'pefc' => 'PEFC',
                        ])
                        ->visible(fn($get) => $get('certificable') === true)
                        ->reactive(),

                    Forms\Components\Checkbox::make('guia_sanidad')
                        ->label('¿Guía de sanidad?')
                        ->reactive(),

                ])->columns(3)
                ->visible(function ($get) {
                    return !empty($get('referencia'));
                }),

            Forms\Components\Section::make('Tarifa')
                ->schema([
                    Forms\Components\Select::make('tarifa')
                        ->label('Tarifa')
                        ->options([
                            'toneladas' => 'Toneladas',
                            'm3' => 'Metros cúbicos',
                        ])
                        ->searchable()
                        ->nullable()
                        ->reactive(), // necesario para reactividad

                    Forms\Components\TextInput::make('precio')
                        ->label(fn(callable $get) => match ($get('tarifa')) {
                            'toneladas' => 'Precio por tonelada',
                            'm3' => 'Precio por m³',
                            default => 'Precio',
                        })
                        ->numeric()
                        ->nullable()
                        ->reactive()
                        ->suffix(fn(callable $get) => match ($get('tarifa')) {
                            'toneladas' => '€/tonelada',
                            'm3' => '€/m³',
                            default => '€',
                        }),

                    Forms\Components\TextInput::make('precio_horas')
                        ->label('Precio por hora')
                        ->numeric()
                        ->nullable()
                        ->reactive()
                        ->suffix('€/hora')
                ])
                ->columns(3)
                ->visible(function ($get) {
                    return !empty($get('referencia'));
                }),

            Forms\Components\Section::make('Contacto')
                ->schema([
                    Forms\Components\TextInput::make('contacto_nombre')
                        ->label('Nombre')
                        ->nullable(),
                    Forms\Components\TextInput::make('contacto_telefono')
                        ->label('Teléfono')
                        ->nullable(),
                    Forms\Components\TextInput::make('contacto_email')
                        ->label('Correo electrónico')
                        ->nullable(),
                ])->columns(3)
                ->visible(function ($get) {
                    return !empty($get('referencia'));
                }),

            Forms\Components\Select::make('usuarios')
                ->label('Usuarios relacionados')
                ->multiple()
                ->relationship(
                    name: 'usuarios',
                    titleAttribute: 'name', // columna real
                    modifyQueryUsing: fn($query) =>
                    $query->orderBy('name')
                        ->whereNull('users.deleted_at')
                        ->whereDoesntHave(
                            'roles',
                            fn($q) =>
                            $q->whereIn('name', ['superadmin', 'administracion', 'administrador', 'direccion'])
                        )
                )
                ->getOptionLabelFromRecordUsing(function ($record) {
                    return $record?->nombre_apellidos ?? '-';
                })
                ->preload()
                ->searchable()
                ->columnSpanFull()
                ->visible(fn($get) => !empty($get('referencia'))),

            Forms\Components\Section::make('Estado')
                ->schema([
                    Forms\Components\Select::make('estado')
                        ->label('Estado')
                        ->searchable()
                        ->options([
                            'abierto' => 'Abierto',
                            'cerrado' => 'Cerrado',
                            'en_proceso' => 'En proceso',
                        ])
                        ->required(),

                    Forms\Components\Select::make('en_negociacion')
                        ->label('En negociación')
                        ->searchable()
                        ->options([
                            'confirmado' => 'Confirmado',
                            'sin_confirmar' => 'Sin confirmar',
                        ])
                        ->required(),

                    Forms\Components\Textarea::make('observaciones')
                        ->nullable(),
                ])->columns(1)
                ->visible(function ($get) {
                    return !empty($get('referencia'));
                }),
        ];
    }
}
