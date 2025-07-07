<?php

namespace App\Exports;

use App\Models\CargaTransporte;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BalanceDeMasasExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithEvents, WithStyles
{
    protected $agrupado;

    public function __construct()
    {
        $cargas = CargaTransporte::with([
            'referencia',
            'almacen',
            'parteTrabajoSuministroTransporte.usuario',
            'parteTrabajoSuministroTransporte.camion',
            'parteTrabajoSuministroTransporte.cliente',
        ])->get();

        $this->agrupado = $cargas->groupBy(function ($carga) {
            return $carga->referencia_id ?? $carga->almacen_id;
        });
    }

    public function collection()
    {
        $filas = collect();

        foreach ($this->agrupado as $referenciaId => $cargas) {
            $ref = $cargas->first()->referencia ?? $cargas->first()->almacen;

            $totalCargadoReferencia = $cargas->sum('cantidad');

            $filas->push([
                'Referencia' => $ref?->referencia ?? $ref?->almacen,
                'Lugar' => '',
                'Fecha Inicio' => '',
                'Ubicación Inicio' => '',
                'Fecha Fin' => '',
                'Ubicación Fin' => '',
                'Cantidad Carga' => '',
                'Cantidad Carga Tn' => '',
                'Transportista' => '',
                'Camión' => '',
                'Destino' => '',
                'Conversor a Tn' => '',
            ]);

            foreach ($cargas as $carga) {
                $parte = $carga->parteTrabajoSuministroTransporte;

                if (!$parte || $parte->trashed())
                    continue;

                $ayuntamiento = optional($carga->referencia)?->ayuntamiento ?? optional($carga->almacen)?->ayuntamiento ?? '—';
                $monteParcela = optional($carga->referencia)?->monte_parcela ?? optional($carga->almacen)?->monte_parcela ?? '—';

                $lugar = "{$ayuntamiento}, {$monteParcela}";

                $filas->push([
                    'Referencia' => '',
                    'Lugar' => $lugar,
                    'Fecha Inicio' => optional($carga->fecha_hora_inicio_carga)?->format('d/m/Y H:i'),
                    'Ubicación Inicio' => $carga->gps_inicio_carga ? '=HYPERLINK("https://maps.google.com/?q=' . $carga->gps_inicio_carga . '", "Ver mapa")' : '',
                    'Fecha Fin' => optional($carga->fecha_hora_fin_carga)?->format('d/m/Y H:i'),
                    'Ubicación Fin' => $carga->gps_fin_carga ? '=HYPERLINK("https://maps.google.com/?q=' . $carga->gps_fin_carga . '", "Ver mapa")' : '',
                    'Cantidad Carga' => $carga->cantidad,
                    'Cantidad Carga Tn' => '', // fórmula en AfterSheet
                    'Transportista' => optional($parte->usuario)?->name . ' ' . optional($parte->usuario)?->apellidos ?? '—',
                    'Camión' => optional($parte->camion)?->matricula_cabeza ?? '—',
                    'Destino' => optional($parte->cliente)?->razon_social ?? optional($carga->almacen)?->nombre ?? '—',
                    'Conversor a Tn' => '',
                ]);
            }

            $filas->push([
                'Referencia' => 'TOTAL CARGADO',
                'Lugar' => '',
                'Fecha Inicio' => '',
                'Ubicación Inicio' => '',
                'Fecha Fin' => '',
                'Ubicación Fin' => '',
                'Cantidad Carga' => $totalCargadoReferencia,
                'Cantidad Carga Tn' => round($totalCargadoReferencia * 23, 2),
                'Transportista' => '',
                'Camión' => '',
                'Destino' => '',
                'Conversor a Tn' => '',
            ]);

            $filas->push(array_fill_keys([
                'Referencia',
                'Lugar',
                'Fecha Inicio',
                'Ubicación Inicio',
                'Fecha Fin',
                'Ubicación Fin',
                'Cantidad Carga',
                'Cantidad Carga Tn',
                'Transportista',
                'Camión',
                'Destino',
                'Conversor a Tn'
            ], null));
        }

        $totalGlobal = CargaTransporte::sum('cantidad');

        $filas->push([
            'Referencia' => 'RESUMEN GLOBAL',
            'Lugar' => '',
            'Fecha Inicio' => '',
            'Ubicación Inicio' => '',
            'Fecha Fin' => '',
            'Ubicación Fin' => '',
            'Cantidad Carga' => $totalGlobal,
            'Cantidad Carga Tn' => round($totalGlobal * 23, 2),
            'Transportista' => '',
            'Camión' => '',
            'Destino' => '',
            'Conversor a Tn' => '',
        ]);

        // Fila final con el conversor global
        $filas->push([
            'Referencia' => 'CONVERSOR A TN GLOBAL',
            'Lugar' => '',
            'Fecha Inicio' => '',
            'Ubicación Inicio' => '',
            'Fecha Fin' => '',
            'Ubicación Fin' => '',
            'Cantidad Carga' => '',
            'Cantidad Carga Tn' => '',
            'Transportista' => '',
            'Camión' => '',
            'Destino' => '',
            'Conversor a Tn' => 23,
        ]);

        return $filas;
    }

    public function headings(): array
    {
        return [
            'REFERENCIA',
            'LUGAR',
            'INICIO',
            'UBICACIÓN INICIO',
            'FIN',
            'UBICACIÓN FIN',
            'CANTIDAD CARGADA (m3)',
            'CANTIDAD CARGADA (Tn)',
            'USUARIO',
            'CAMIÓN',
            'DESTINO',
            'CONVERSOR A TN'
        ];
    }

    public function map($row): array
    {
        return array_values($row);
    }

    public function title(): string
    {
        return 'BALANCE DE MASAS';
    }

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowCount = $sheet->getHighestRow();

                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:L1');

                // Calcular fila del conversor global
                $conversorRow = $rowCount;

                for ($row = 2; $row < $conversorRow; $row++) {
                    $cantidadCarga = $sheet->getCell("G{$row}")->getValue();

                    $colA = $sheet->getCell("A{$row}")->getValue();
                    $style = $sheet->getStyle("A{$row}:L{$row}");

                    if (!empty($colA)) {
                        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
                        $style->getFont()->setBold(true);
                    }

                    if ($colA === 'TOTAL CARGADO') {
                        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C8E6C9');
                    }

                    if ($colA === 'RESUMEN GLOBAL') {
                        $style->getFont()->setBold(true)->setSize(10);
                        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFD54F');
                    }

                    // Filas de detalle: poner fórmula en I solo si tienen cantidad
                    if (!empty($cantidadCarga)) {
                        $sheet->setCellValue("H{$row}", "=G{$row}*\$L\${$conversorRow}");
                    }
                }
            },
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 8,
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'bottom' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ],
            'A:L' => [
                'font' => ['size' => 8],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }
}
