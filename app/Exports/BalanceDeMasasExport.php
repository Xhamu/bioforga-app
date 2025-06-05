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
use function PHPUnit\Framework\isNull;

class BalanceDeMasasExport implements FromCollection, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithEvents, WithStyles
{
    protected $agrupado;

    public function __construct()
    {
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
                'Ubicación Inicio' => '',
                'Fecha Fin' => '',
                'Ubicación Fin' => '',
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
                    'Cantidad Aprox' => '-- CARGA --',
                    'Fecha Inicio' => optional($carga->fecha_hora_inicio_carga)?->format('d/m/Y H:i'),
                    'Ubicación Inicio' => $carga->gps_inicio_carga
                        ? '=HYPERLINK("https://maps.google.com/?q=' . $carga->gps_inicio_carga . '", "Ver mapa")'
                        : '',
                    'Fecha Fin' => optional($carga->fecha_hora_fin_carga)?->format('d/m/Y H:i'),
                    'Ubicación Fin' => $carga->gps_fin_carga
                        ? '=HYPERLINK("https://maps.google.com/?q=' . $carga->gps_fin_carga . '", "Ver mapa")'
                        : '',
                    'Cantidad Carga' => $carga->cantidad,
                    'Transportista' => optional($parte->usuario)?->name . ' ' . optional($parte->usuario)?->apellidos ?? '—',
                    'Camión' => optional($parte->camion)?->marca . ' ' . optional($parte->camion)?->modelo ?? '—',
                    'Destino' => optional($parte->cliente)?->razon_social ?? optional($carga->almacen)?->nombre ?? '—',
                ]);
            }

            $filas->push([
                'Referencia' => 'TOTAL CARGADO',
                'Cantidad Aprox' => $totalCargado,
                'Fecha Inicio' => '',
                'Ubicación Inicio' => '',
                'Fecha Fin' => '',
                'Ubicación Fin' => '',
                'Cantidad Carga' => 'SALDO',
                'Transportista' => $saldo,
                'Camión' => '',
                'Destino' => '',
            ]);

            $filas->push(array_fill_keys([
                'Referencia',
                'Cantidad Aprox',
                'Fecha Inicio',
                'Ubicación Inicio',
                'Fecha Fin',
                'Ubicación Fin',
                'Cantidad Carga',
                'Transportista',
                'Camión',
                'Destino'
            ], null));
        }

        // Añadir fila de resumen global
        $totalAprox = $this->agrupado->map(fn($cargas) => optional($cargas->first()->referencia)?->cantidad_aprox ?? 0)->sum();
        $totalCargado = $this->agrupado->flatMap(fn($cargas) => $cargas)->sum('cantidad');
        $saldoGlobal = $totalAprox - $totalCargado;

        $filas->push([
            'Referencia' => 'RESUMEN GLOBAL',
            'Cantidad Aprox' => $totalAprox,
            'Fecha Inicio' => '',
            'Ubicación Inicio' => '',
            'Fecha Fin' => '',
            'Ubicación Fin' => '',
            'Cantidad Carga' => $totalCargado,
            'Transportista' => 'SALDO GLOBAL',
            'Camión' => $saldoGlobal,
            'Destino' => '',
        ]);

        return $filas;
    }

    public function headings(): array
    {
        return [
            'REFERENCIA',
            'CANTIDAD APROX.',
            'INICIO',
            'UBICACIÓN INICIO',
            'FIN',
            'UBICACIÓN FIN',
            'CANTIDAD CARGADA',
            'USUARIO',
            'CAMIÓN',
            'DESTINO',
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

    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowCount = $sheet->getHighestRow();

                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:J1');

                for ($row = 2; $row <= $rowCount; $row++) {
                    $contenido = $sheet->getCell("H{$row}")->getValue();
                    $saltos = substr_count((string) $contenido, PHP_EOL);
                    $lineas = floor(strlen((string) $contenido) / 100);
                    $altura = max(15 * ($lineas + $saltos + 1), 16);
                    $sheet->getRowDimension($row)->setRowHeight($altura);

                    $colA = $sheet->getCell("A{$row}")->getValue();
                    $colB = $sheet->getCell("B{$row}")->getValue();

                    $style = $sheet->getStyle("A{$row}:J{$row}");

                    if (!empty($colA)) {
                        $style->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setRGB('D9D9D9');
                        $style->getFont()->setBold(true);
                    }

                    if ($colB === '-- CARGA --') {
                        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F0FE');
                    }

                    if ($colA === 'TOTAL CARGADO') {
                        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C8E6C9');
                    }

                    if ($colA === 'RESUMEN GLOBAL') {
                        $style->getFont()->setBold(true)->setSize(10);
                        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFD54F');
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
            'A:J' => [
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
