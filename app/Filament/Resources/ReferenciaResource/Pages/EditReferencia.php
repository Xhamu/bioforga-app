<?php

namespace App\Filament\Resources\ReferenciaResource\Pages;

use App\Filament\Resources\ReferenciaResource;
use App\Models\Referencia;
use Filament\Actions;
use Filament\Forms\Components\Repeater;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;
use Filament\Forms\Components\Tabs;
use App\Filament\Resources\ReferenciaResource as RefRes;
use Illuminate\Support\Facades\Auth;


class EditReferencia extends EditRecord
{
    protected static string $resource = ReferenciaResource::class;

    protected string $estadoAnterior;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Tabs::make('Formulario')
                ->id('referencia-tabs')
                ->persistTab()
                ->tabs([
                    Tabs\Tab::make('General')
                        ->schema(ReferenciaResource::generalFormSchema()),

                    Tabs\Tab::make('Partes de trabajo')
                        ->schema([
                            Forms\Components\View::make('filament.resources.referencia-resource.partials.partes-trabajo')
                                ->viewData([
                                    'partesTransporteAgrupados' => \App\Models\CargaTransporte::with([
                                        'parteTrabajoSuministroTransporte.cliente',
                                        'parteTrabajoSuministroTransporte.almacen',
                                        'parteTrabajoSuministroTransporte.cargas',
                                        'referencia',
                                    ])
                                        ->where('referencia_id', $this->record?->id) // solo cargas de ESTA referencia
                                        ->whereNull('deleted_at')
                                        ->orderBy('fecha_hora_inicio_carga', 'asc')
                                        ->get()
                                        ->groupBy('parte_trabajo_suministro_transporte_id')
                                        ->map(function ($cargasDeEstaRef) {
                                            $parte = $cargasDeEstaRef->first()->parteTrabajoSuministroTransporte;

                                            // m³ de esta referencia dentro del parte (ya filtrado arriba a esta ref)
                                            $m3DeEstaRef = (float) $cargasDeEstaRef->sum('cantidad');

                                            // m³ totales del parte (todas sus cargas, cualquiera que sea la referencia)
                                            $totalM3Parte = (float) ($parte?->cargas?->sum('cantidad') ?? 0);

                                            // peso total del parte
                                            $pesoNetoParte = is_null($parte?->peso_neto) ? null : (float) $parte->peso_neto;

                                            // Regla de 3: peso proporcional de ESTA referencia en este parte
                                            $pesoNetoRef = null;
                                            if ($pesoNetoParte !== null) {
                                                if ($totalM3Parte > 0) {
                                                    $pesoNetoRef = ($m3DeEstaRef / $totalM3Parte) * $pesoNetoParte;
                                                } else {
                                                    // Si por lo que sea no hay total m³, mostramos el total del parte
                                                    $pesoNetoRef = $pesoNetoParte;
                                                }
                                            }

                                            return (object) [
                                                'id' => $parte?->id,
                                                'referencias' => $cargasDeEstaRef->pluck('referencia.referencia')->filter()->unique()->values(),
                                                'cliente' => $parte?->cliente?->razon_social ?? null,
                                                'almacen' => $parte?->almacen?->referencia ?? null,
                                                'inicio' => $cargasDeEstaRef->min('fecha_hora_inicio_carga'),
                                                'fin' => $cargasDeEstaRef->max('fecha_hora_fin_carga'),
                                                'cantidad_total' => $m3DeEstaRef,
                                                'cargas' => $cargasDeEstaRef,
                                                'peso_neto_ref' => $pesoNetoRef,
                                            ];
                                        })
                                        ->values(),
                                    'partesMaquina' => $this->record?->partesMaquina
                                        ->sortBy('fecha_hora_inicio_trabajo')
                                        ->values() ?? collect(),
                                ])
                                ->columnSpanFull(),
                        ]),

                    Tabs\Tab::make('Facturación')
                        ->schema([
                            Forms\Components\Section::make('Estado de facturación')
                                ->description('Indica el estado de la facturación de esta referencia.')
                                ->schema([
                                    Forms\Components\Select::make('estado_facturacion')
                                        ->label('')
                                        ->native(false)
                                        ->options([
                                            'completa' => 'Completa',
                                            'parcial' => 'Parcial',
                                            'no_facturada' => 'No facturada',
                                            'no_procede' => 'No procede',
                                        ])
                                        ->helperText('Este estado resume la situación total de facturación de la referencia.')
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                            // Cuando pasa a "completa" o "parcial" y no hay facturas,
                                            // crear automáticamente una línea vacía en el repeater.
                                            if (in_array($state, ['completa', 'parcial'], true)) {
                                                $facturas = $get('facturas') ?? [];

                                                if (empty($facturas)) {
                                                    // Una fila vacía para que el usuario la rellene
                                                    $set('facturas', [
                                                        [
                                                            'numero' => null,
                                                            'fecha' => now()->toDateString(),
                                                            'tipo' => null,
                                                            'importe' => null,
                                                            'cantidad' => null,
                                                            'importe_sin_iva' => null,
                                                            'notas' => null,
                                                        ],
                                                    ]);
                                                }
                                            }
                                        }),
                                ])
                                ->columns(1),

                            Forms\Components\Section::make('Facturas asociadas')
                                ->description('Registra aquí las facturas vinculadas a esta referencia.')
                                ->schema([
                                    Forms\Components\Repeater::make('facturas')
                                        ->relationship()
                                        ->label('Facturas')
                                        ->addActionLabel('Añadir factura')
                                        ->collapsible()
                                        ->itemLabel(function (array $state): string {
                                            $num = $state['numero'] ?? null;
                                            $fecha = $state['fecha'] ?? null;

                                            if ($num && $fecha) {
                                                try {
                                                    $f = \Carbon\Carbon::parse($fecha)->format('d/m/Y');
                                                } catch (\Throwable $e) {
                                                    $f = $fecha;
                                                }

                                                return "Factura {$num} ({$f})";
                                            }

                                            if ($num) {
                                                return "Factura {$num}";
                                            }

                                            return 'Nueva factura';
                                        })
                                        ->schema([
                                            Forms\Components\Grid::make()
                                                ->columns([
                                                    'default' => 1,
                                                    'sm' => 2,
                                                    'lg' => 3,
                                                ])
                                                ->schema([
                                                    Forms\Components\TextInput::make('numero')
                                                        ->label('Número de factura')
                                                        ->maxLength(50)
                                                        ->nullable(),

                                                    Forms\Components\DatePicker::make('fecha')
                                                        ->label('Fecha')
                                                        ->default(now())
                                                        ->nullable()
                                                        ->native(false),

                                                    Forms\Components\Select::make('tipo')
                                                        ->label('Tipo de facturación')
                                                        ->options([
                                                            'horas' => 'Horas',
                                                            'toneladas' => 'Toneladas (Tn)',
                                                        ])
                                                        ->searchable()
                                                        ->nullable()
                                                        ->reactive(),
                                                ]),

                                            Forms\Components\Grid::make()
                                                ->columns([
                                                    'default' => 1,
                                                    'sm' => 3,
                                                ])
                                                ->schema([
                                                    Forms\Components\TextInput::make('importe')
                                                        ->label(fn(callable $get) => match ($get('tipo')) {
                                                            'horas' => 'Importe por hora',
                                                            'toneladas' => 'Importe por tonelada',
                                                            default => 'Importe unitario',
                                                        })
                                                        ->numeric()
                                                        ->step('0.01')
                                                        ->nullable()
                                                        ->suffix(function (callable $get) {
                                                            return match ($get('tipo')) {
                                                                'horas' => '€/hora',
                                                                'toneladas' => '€/tn',
                                                                default => '€',
                                                            };
                                                        })
                                                        ->reactive()
                                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                            $precio = $state;
                                                            $cantidad = $get('cantidad');

                                                            if ($precio !== null && $cantidad !== null) {
                                                                $set('importe_sin_iva', round((float) $precio * (float) $cantidad, 2));
                                                            }
                                                        }),

                                                    Forms\Components\TextInput::make('cantidad')
                                                        ->label('Cantidad')
                                                        ->numeric()
                                                        ->step('0.01')
                                                        ->nullable()
                                                        ->reactive()
                                                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                                            $cantidad = $state;
                                                            $precio = $get('importe');

                                                            if ($precio !== null && $cantidad !== null) {
                                                                $set('importe_sin_iva', round((float) $precio * (float) $cantidad, 2));
                                                            }
                                                        }),

                                                    Forms\Components\TextInput::make('importe_sin_iva')
                                                        ->label('Importe sin IVA')
                                                        ->numeric()
                                                        ->step('0.01')
                                                        ->suffix('€')
                                                        ->nullable()
                                                        ->columnSpan(2)
                                                        ->helperText('Se calcula automáticamente a partir de tipo de facturación, importe y cantidad, pero puedes ajustarlo manualmente si es necesario.'),
                                                ]),

                                            Forms\Components\Textarea::make('notas')
                                                ->label('Notas')
                                                ->rows(5)
                                                ->nullable()
                                                ->columnSpanFull(),
                                        ])
                                        ->defaultItems(0),
                                ]),
                        ]),

                    Tabs\Tab::make('Historial de cambios')
                        ->schema([
                            Forms\Components\View::make('filament.resources.referencia-resource.partials.historial-cambios')
                                ->viewData([
                                    'logs' => \Spatie\Activitylog\Models\Activity::where('subject_type', Referencia::class)
                                        ->where('subject_id', $this->record?->id)
                                        ->latest()
                                        ->take(20)
                                        ->get(),
                                ])
                                ->columnSpanFull(),
                        ]),

                    Tabs\Tab::make('Mapa')
                        ->schema([
                            Forms\Components\View::make('filament.resources.referencia-resource.partials.mapa-referencias')
                                ->viewData([
                                    'referenciaActualId' => $this->record->getKey(),
                                    'markers' => Referencia::query()
                                        ->withoutTrashed()
                                        ->whereNotNull('ubicacion_gps')
                                        ->where('estado', '!=', 'cerrado')
                                        ->where('ubicacion_gps', '!=', '')
                                        ->select('id', 'referencia', 'provincia', 'ayuntamiento', 'ubicacion_gps')
                                        ->get()
                                        ->map(function ($ref) {
                                            $raw = trim((string) $ref->ubicacion_gps);
                                            $raw = str_replace([';', '|'], ',', $raw);
                                            $raw = preg_replace('/\s+/', ' ', $raw);

                                            // Separar por coma y limpiar
                                            $parts = array_values(array_filter(explode(',', $raw), fn($v) => trim($v) !== ''));

                                            // Si hay más de 2 partes y parecen números partidos por coma decimal
                                            if (count($parts) > 2 && preg_match('/^-?\d+$/', trim($parts[0])) && preg_match('/^\d+$/', trim($parts[1]))) {
                                                $parts = [
                                                    $parts[0] . '.' . $parts[1],
                                                    $parts[2] . (isset($parts[3]) ? '.' . $parts[3] : '')
                                                ];
                                            }

                                            if (count($parts) < 2) {
                                                return null;
                                            }

                                            $convert = function ($coord) {
                                                $coord = trim($coord);
                                                // DMS → Decimal
                                                if (preg_match('/(\d+)°(\d+)\'([\d.]+)"?([NSEW])?/i', $coord, $m)) {
                                                    $deg = (float) $m[1];
                                                    $min = (float) $m[2];
                                                    $sec = (float) $m[3];
                                                    $dir = strtoupper($m[4] ?? 'N');
                                                    $decimal = $deg + ($min / 60) + ($sec / 3600);
                                                    if (in_array($dir, ['S', 'W'])) {
                                                        $decimal *= -1;
                                                    }
                                                    return $decimal;
                                                }
                                                // Decimal con coma
                                                $coord = str_replace(',', '.', $coord);
                                                return (float) $coord;
                                            };

                                            $lat = $convert($parts[0]);
                                            $lng = $convert($parts[1]);

                                            if ($lat === 0 || $lng === 0 || abs($lat) > 90 || abs($lng) > 180) {
                                                return null;
                                            }

                                            return [
                                                'id' => (int) $ref->id,
                                                'lat' => $lat,
                                                'lng' => $lng,
                                                'titulo' => "{$ref->referencia} — {$ref->ayuntamiento}, {$ref->provincia}",
                                                'url' => RefRes::getUrl('edit', ['record' => $ref->id]),
                                            ];
                                        })
                                        ->filter()
                                        ->values()
                                ])
                                ->columnSpanFull(),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->record;

        $this->estadoAnterior = $record->estado ?? null;
        $estadoFactAnterior = $record->estado_facturacion ?? null;

        $nuevoEstado = $data['estado'] ?? $this->estadoAnterior;

        /**
         * Si el estado de referencia pasa a "cerrado_no_procede",
         * la facturación pasa directamente a "no_procede".
         */
        if ($nuevoEstado === 'cerrado_no_procede') {
            $data['estado_facturacion'] = 'no_procede';
        }

        /**
         * Si la referencia estaba cerrada y facturada completa,
         * y se vuelve a abrir → facturación pasa a PARCIAL.
         */
        if (
            in_array($this->estadoAnterior, ['cerrado', 'cerrado_no_procede'], true)
            && $estadoFactAnterior === 'completa'
            && in_array($nuevoEstado, ['abierto', 'en_proceso'], true)
        ) {
            $data['estado_facturacion'] = 'parcial';
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->usuarios()->sync($this->data['usuarios'] ?? []);

        // Si el estado ha cambiado a "cerrado", desvincular usuarios
        if (
            $this->estadoAnterior !== 'cerrado' &&
            $this->record->estado === 'cerrado'
        ) {
            $this->record->usuarios()->detach();

            // Opcional: notificación visual
            \Filament\Notifications\Notification::make()
                ->title('Usuarios desvinculados')
                ->body('Al cerrar la referencia, se han desvinculado todos los usuarios.')
                ->success()
                ->send();

            redirect('/referencias');
        }
    }
}
