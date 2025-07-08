<?php

namespace App\Filament\Resources\ReferenciaResource\Pages;

use App\Filament\Resources\ReferenciaResource;
use Filament\Actions;
use Filament\Forms\Components\Repeater;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;
use Filament\Forms\Components\Tabs;

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
            Tabs::make('Formulario')->tabs([
                Tabs\Tab::make('General')
                    ->schema(ReferenciaResource::generalFormSchema()),

                Tabs\Tab::make('Partes de trabajo')
                    ->schema([
                        Forms\Components\View::make('filament.resources.referencia-resource.partials.partes-trabajo')
                            ->viewData([
                                'partesTransporteAgrupados' => \App\Models\CargaTransporte::with([
                                    'parteTrabajoSuministroTransporte.cliente',
                                    'referencia',
                                ])
                                    ->where('referencia_id', $this->record?->id)
                                    ->whereNull('deleted_at')
                                    ->get()
                                    ->groupBy('parte_trabajo_suministro_transporte_id')
                                    ->map(function ($cargas) {
                                        $parte = $cargas->first()->parteTrabajoSuministroTransporte;

                                        return (object) [
                                            'referencias' => $cargas->pluck('referencia.referencia')->filter()->unique()->values(),
                                            'cliente' => $parte?->cliente?->razon_social ?? '-',
                                            'inicio' => $cargas->min('fecha_hora_inicio_carga'),
                                            'fin' => $cargas->max('fecha_hora_fin_carga'),
                                            'cantidad_total' => $cargas->sum('cantidad'),
                                            'cargas' => $cargas,
                                        ];
                                    })
                                    ->values(),
                                'partesMaquina' => $this->record?->partesMaquina ?? collect(),
                            ])
                            ->columnSpanFull(),
                    ]),

                Tabs\Tab::make('Facturación')
                    ->schema([
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

                                Forms\Components\TextInput::make('importe')
                                    ->label('Importe / Tn (€)')
                                    ->numeric()
                                    ->prefix('€')
                                    ->nullable(),

                                Forms\Components\Textarea::make('notas')
                                    ->label('Notas')
                                    ->nullable(),
                            ])
                            ->columns(2) // Opcional: puedes poner en columnas si quieres ahorrar espacio
                            ->defaultItems(1) // Opcional: cuántas facturas se muestran por defecto
                            ->createItemButtonLabel('Añadir factura'),
                    ])
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
        }
    }
}
