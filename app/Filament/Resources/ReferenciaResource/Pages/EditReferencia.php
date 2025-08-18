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
                ->id('referencia-tabs')           // id único para esta Tabs
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
                                        // 👇 importante: trae TODAS las cargas del parte para poder prorratear
                                        'parteTrabajoSuministroTransporte.cargas',
                                        'referencia',
                                    ])
                                        ->where('referencia_id', $this->record?->id) // solo cargas de ESTA referencia
                                        ->whereNull('deleted_at')
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
                                                'cantidad_total' => $m3DeEstaRef,               // m³ de ESTA referencia en el parte
                                                'cargas' => $cargasDeEstaRef,
                                                'peso_neto_ref' => $pesoNetoRef,               // 👈 ya prorrateado
                                            ];
                                        })
                                        ->values(),
                                    'partesMaquina' => $this->record?->partesMaquina ?? collect(),
                                ])
                                ->columnSpanFull(),
                        ]),

                    Tabs\Tab::make('Facturación')
                        ->schema([
                            Forms\Components\Select::make('estado_facturacion')
                                ->label('Estado')
                                ->searchable()
                                ->options([
                                    'completa' => 'Completa',
                                    'parcial' => 'Parcial',
                                    'no_facturada' => 'No facturada',
                                ]),

                            Repeater::make('facturas')
                                ->relationship()
                                ->label('Facturas')
                                ->schema([
                                    Forms\Components\TextInput::make('numero')
                                        ->label('Número de factura')
                                        ->nullable(),

                                    Forms\Components\DatePicker::make('fecha')
                                        ->label('Fecha')
                                        ->default(now())
                                        ->nullable(),

                                    Forms\Components\Select::make('tipo')
                                        ->options([
                                            'horas' => 'Horas',
                                            'toneladas' => 'Tn',
                                        ])
                                        ->label('Tipo')
                                        ->searchable()
                                        ->nullable()
                                        ->reactive(),

                                    Forms\Components\TextInput::make('importe')
                                        ->label(fn(callable $get) => match ($get('tipo')) {
                                            'horas' => 'Importe / hora',
                                            'toneladas' => 'Importe / tonelada',
                                            default => 'Importe',
                                        })
                                        ->numeric()
                                        ->nullable()
                                        ->suffix(function (callable $get) {
                                            return match ($get('tipo')) {
                                                'horas' => '€/hora',
                                                'toneladas' => '€/tn',
                                                default => '€',
                                            };
                                        }),

                                    Forms\Components\TextInput::make('importe_sin_iva')
                                        ->label('Importe sin IVA')
                                        ->numeric()
                                        ->suffix('€')
                                        ->nullable(),

                                    Forms\Components\TextInput::make('cantidad')
                                        ->label('Cantidad')
                                        ->numeric()
                                        ->step(0.01)
                                        ->nullable(),

                                    Forms\Components\Textarea::make('notas')
                                        ->label('Notas')
                                        ->nullable()
                                        ->columnSpanFull(),
                                ])
                                ->columns(2) // Opcional: puedes poner en columnas si quieres ahorrar espacio
                                ->defaultItems(1) // Opcional: cuántas facturas se muestran por defecto
                                ->createItemButtonLabel('Añadir factura'),
                        ]),

                    Tabs\Tab::make('Historial de cambios')
                        ->schema([
                            Forms\Components\View::make('filament.resources.referencia-resource.partials.historial-cambios')
                                ->viewData([
                                    'logs' => \Spatie\Activitylog\Models\Activity::where('subject_type', \App\Models\Referencia::class)
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
        $this->estadoAnterior = $this->record->estado ?? null; // guarda estado antes de guardar
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
