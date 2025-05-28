<?php

namespace App\Exports;

use App\Models\Referencia;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Collection;

class ReferenciasExport implements FromCollection, WithHeadings, WithMapping, WithEvents, WithStyles, ShouldAutoSize
{
    protected $tipo;

    public function __construct(string $tipo)
    {
        $this->tipo = $tipo;
    }
    public function collection()
    {
        $filtros = ['SUSA', 'SUEX', 'SUOT', 'SUCA'];

        if ($this->tipo === 'servicio') {
            return Referencia::where(function ($query) use ($filtros) {
                foreach ($filtros as $filtro) {
                    $query->where('referencia', 'NOT LIKE', "%$filtro%");
                }
            })->get();
        }

        // Si es suministro, agrupamos en bloques por filtro
        $bloques = collect();

        foreach ($filtros as $tipo) {
            $datos = Referencia::where('referencia', 'LIKE', "%$tipo%")->get();

            if ($datos->isNotEmpty()) {
                $nombreMapeado = match ($tipo) {
                    'SUSA' => 'SACA',
                    'SUEX' => 'EXPLOTACIÓN',
                    'SUCA' => 'CARGADOR',
                    'SUOT' => 'OTROS',
                    default => $tipo,
                };

                // Fila de título de grupo
                $bloques->push((object) [
                    'created_at' => null,
                    'referencia' => "=== {$nombreMapeado} ===",
                    'proveedor_id' => null,
                    'contacto_nombre' => null,
                    'monte_parcela' => null,
                    'ayuntamiento' => null,
                    'ubicacion_gps' => null,
                    'cantidad_aprox' => null,
                    'producto_tipo' => null,
                    'observaciones' => null,
                    'estado' => null,
                ]);

                // Fila de encabezado personalizada
                $bloques->push((object) [
                    'created_at' => 'FECHA',
                    'referencia' => 'REFERENCIA',
                    'proveedor_id' => 'PROVEEDOR',
                    'contacto_nombre' => 'CONTACTO',
                    'monte_parcela' => 'MONTE',
                    'ayuntamiento' => 'MUNICIPIO',
                    'ubicacion_gps' => 'UBICACION',
                    'cantidad_aprox' => 'CANTIDAD',
                    'producto_tipo' => 'TIPO-CLASIFICACIÓN',
                    'observaciones' => 'OBSERVACIONES',
                    'referencia_precio' => 'PRECIO',
                    'estado' => 'ESTADO',
                ]);

                // Datos reales
                foreach ($datos as $d) {
                    $bloques->push($d);
                }

                // Separador visual
                $bloques->push((object) [
                    'created_at' => null,
                    'referencia' => null,
                    'proveedor_id' => null,
                    'contacto_nombre' => null,
                    'monte_parcela' => null,
                    'ayuntamiento' => null,
                    'ubicacion_gps' => null,
                    'cantidad_aprox' => null,
                    'producto_tipo' => null,
                    'observaciones' => null,
                    'estado' => null,
                ]);
            }
        }

        return $bloques;
    }

    public function headings(): array
    {
        return [
            'FECHA',
            'REFERENCIA',
            'PROVEEDOR',
            'CONTACTO',
            'MONTE',
            'MUNICIPIO',
            'UBICACIÓN',
            'CANTIDAD',
            'TIPO-CLASIFICACIÓN',
            'OBSERVACIONES',
            'PRECIO',
            'ESTADO',
        ];
    }

    public function map($row): array
    {
        // Fila de grupo (título tipo "=== SACA ===")
        if ($row->created_at === null && str_starts_with($row->referencia, '=== ')) {
            return [
                "'" . $row->referencia,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null
            ];
        }

        // Fila de encabezados personalizados para cada grupo
        if ($row->created_at === 'FECHA') {
            return [
                'FECHA',
                'REFERENCIA',
                'PROVEEDOR',
                'CONTACTO',
                'MONTE',
                'MUNICIPIO',
                'UBICACION',
                'CANTIDAD',
                'TIPO-CLASIFICACIÓN',
                'OBSERVACIONES',
                'PRECIO',
                'ESTADO'
            ];
        }

        // Fila separadora vacía
        if ($row->created_at === null && $row->referencia === null) {
            return array_fill(0, 12, null);
        }

        return [
            $row->created_at?->format('d/m/Y'),
            $row->referencia,
            $row->proveedor_id,
            $row->contacto_nombre,
            $row->monte_parcela,
            $row->ayuntamiento,
            $row->ubicacion_gps
            ? '=HYPERLINK("https://maps.google.com/?q=' . $row->ubicacion_gps . '")'
            : null,
            $row->cantidad_aprox,
            $row->producto_tipo,
            $row->observaciones,
            $row->referencia,
            $row->estado,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowCount = $sheet->getHighestRow();

                // Congelar fila de encabezado
                $sheet->freezePane('A2');

                // Autofiltro en fila 1
                $sheet->setAutoFilter('A1:L1');

                // Ajustar altura según observaciones (columna I)
                for ($row = 2; $row <= $rowCount; $row++) {
                    $contenido = $sheet->getCell("J{$row}")->getValue();
                    $saltos = substr_count((string) $contenido, PHP_EOL);
                    $lineas = floor(strlen((string) $contenido) / 140);
                    $altura = max(15 * ($lineas + $saltos + 1), 16);
                    $sheet->getRowDimension($row)->setRowHeight($altura);
                }

                // Colores según estado en columna K
                for ($row = 2; $row <= $rowCount; $row++) {
                    $estado = strtolower($sheet->getCell("L{$row}")->getValue());

                    $color = match ($estado) {
                        'cerrado' => 'FFCCCC',
                        'en_proceso' => 'CCFFCC',
                        default => null,
                    };

                    if ($color) {
                        $sheet->getStyle("A{$row}:L{$row}")
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setRGB($color);
                    }
                }
            },
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => [
                    'bottom' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ],
            'A:L' => [
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
            'J' => [
                'alignment' => [
                    'wrapText' => true,
                ],
            ],
        ];
    }
}
