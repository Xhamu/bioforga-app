<?php

namespace App\Exports;

use App\Models\CargaTransporte;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class BalanceDeMasasExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize
{
    protected $agrupado;

    public function __construct()
    {
        // Agrupar las cargas por referencia
        $this->agrupado = CargaTransporte::with([
            'referencia',
            'parteTrabajoSuministroTransporte.usuario',
            'parteTrabajoSuministroTransporte.camion',
            'parteTrabajoSuministroTransporte.cliente',
            'almacen'
        ])->get()->groupBy('referencia_id');
    }

    public function collection()
    {
        $filas = collect();

        foreach ($this->agrupado as $referenciaId => $cargas) {
            $ref = $cargas->first()->referencia;
            $cantidadAprox = $ref?->cantidad_aprox ?? 0;
            $totalCargado = $cargas->sum('cantidad');
            $saldo = $cantidadAprox - $totalCargado;

            $filas->push([
                'Referencia' => $ref?->referencia ?? 'Sin referencia',
                'Cantidad Aprox' => $cantidadAprox,
                'Fecha Inicio' => '',
                'Fecha Fin' => '',
                'Cantidad Carga' => '',
                'Transportista' => '',
                'Camión' => '',
                'Destino' => '',
            ]);

            foreach ($cargas as $carga) {
                $parte = $carga->parteTrabajoSuministroTransporte;

                if (!$parte || $parte->trashed()) {
                    continue;
                }

                $filas->push([
                    'Referencia' => '',
                    'Cantidad Aprox' => '',
                    'Fecha Inicio' => optional($carga->fecha_hora_inicio_carga)->format('d/m/Y H:i'),
                    'Fecha Fin' => optional($carga->fecha_hora_fin_carga)->format('d/m/Y H:i'),
                    'Cantidad Carga' => $carga->cantidad,
                    'Transportista' => optional($parte?->usuario)->name ?? '—',
                    'Camión' => optional($parte?->camion)->matricula ?? '—',
                    'Destino' => optional($parte?->cliente)->razon_social ?? optional($carga->almacen)->nombre ?? '—',
                ]);
            }

            $filas->push([
                'Referencia' => 'TOTAL CARGADO',
                'Cantidad Aprox' => $totalCargado,
                'Fecha Inicio' => '',
                'Fecha Fin' => '',
                'Cantidad Carga' => 'SALDO',
                'Transportista' => $saldo,
                'Camión' => '',
                'Destino' => '',
            ]);
        }

        return $filas;
    }

    public function headings(): array
    {
        return [
            'Referencia',
            'Cantidad Aprox',
            'Fecha Inicio',
            'Fecha Fin',
            'Cantidad Carga',
            'Transportista',
            'Camión',
            'Destino',
        ];
    }

    public function map($row): array
    {
        return array_values($row);
    }

    public function title(): string
    {
        return 'BALANCE MASAS';
    }
}
