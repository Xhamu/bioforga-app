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
                                        'referencia',
                                    ])
                                        ->where('referencia_id', $this->record?->id)
                                        ->whereNull('deleted_at')
                                        ->get()
                                        ->groupBy('parte_trabajo_suministro_transporte_id')
                                        ->map(function ($cargas) {
                                            $parte = $cargas->first()->parteTrabajoSuministroTransporte;

                                            return (object) [
                                                'id' => $parte?->id,
                                                'referencias' => $cargas->pluck('referencia.referencia')->filter()->unique()->values(),
                                                'cliente' => $parte?->cliente?->razon_social ?? null,
                                                'almacen' => $parte?->almacen?->referencia ?? null,
                                                'inicio' => $cargas->min('fecha_hora_inicio_carga'),
                                                'fin' => $cargas->max('fecha_hora_fin_carga'),
                                                'cantidad_total' => $cargas->sum('cantidad'),
                                                'cargas' => $cargas,
                                                'peso_neto' => $parte->peso_neto ?? '-',
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
                        ->visible(fn() => auth()->user()?->hasRole('superadmin'))
                        ->schema([
                            Forms\Components\View::make('filament.resources.referencia-resource.partials.mapa-referencias')
                                ->viewData([
                                    'markers' => Referencia::query()
                                        ->whereNull('deleted_at')
                                        ->whereNotNull('ubicacion_gps')
                                        ->where('ubicacion_gps', '!=', '')
                                        ->get(['id', 'referencia', 'provincia', 'ayuntamiento', 'ubicacion_gps'])
                                        ->map(function ($ref) {
                                            $raw = trim((string) $ref->ubicacion_gps);
                                            $raw = str_replace([';', '|'], ',', $raw);
                                            $raw = preg_replace('/\s+/', ' ', $raw);
                                            $raw = str_replace(' ', ',', $raw);
                                            $parts = array_values(array_filter(explode(',', $raw), fn($v) => $v !== ''));

                                            if (count($parts) < 2)
                                                return null;

                                            $lat = (float) str_replace(',', '.', $parts[0]);
                                            $lng = (float) str_replace(',', '.', $parts[1]);
                                            if ($lat === 0 || $lng === 0)
                                                return null;

                                            return [
                                                'lat' => $lat,
                                                'lng' => $lng,
                                                'titulo' => "{$ref->referencia} — {$ref->ayuntamiento}, {$ref->provincia}",
                                                'url' => RefRes::getUrl('edit', ['record' => $ref->id]),
                                            ];
                                        })
                                        ->filter()
                                        ->values(),
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
