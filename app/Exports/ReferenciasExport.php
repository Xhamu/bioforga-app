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
            $referencias = Referencia::where(function ($query) use ($filtros) {
                foreach ($filtros as $filtro) {
                    $query->where('referencia', 'NOT LIKE', "%$filtro%");
                }
            })->get()->groupBy(fn($item) => optional($item->cliente)->razon_social ?? 'Sin Cliente');

            $bloques = collect();

            foreach ($referencias as $cliente => $items) {
                $bloques->push((object) [
                    'created_at' => null,
                    'referencia' => "=== {$cliente} ===",
                ]);

                $bloques->push((object) [
                    'created_at' => 'FECHA',
                    'referencia' => 'REFERENCIA',
                    'proveedor_id' => 'CLIENTE',
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

                foreach ($items as $d) {
                    $bloques->push($d);
                }

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
                    'referencia_precio' => null,
                    'estado' => null,
                ]);
            }

            return $bloques;
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
            'PROVEEDOR / CLIENTE',
            'CONTACTO',
            'PROVINCIA', // new
            'MUNICIPIO', // new
            'MONTE',
            'UBICACIÓN',
            'TIPO', // new
            'ESPECIE', // new
            'CANTIDAD',
            'OBSERVACIONES',
            'CERTIFICACIÓN', // NEW
            'G. SANIDAD', // NEW
            'TARIFA / UNIDAD', // new
            'ESTADO',
            'NEGOCIACIÓN', // new
            'FOTOS', // new
            'USUARIOS', // new
        ];
    }

    public function map($row): array
    {
        // Fila de grupo (título tipo "=== SACA ===")
        if ($row->created_at === null && str_starts_with($row->referencia, '=== ')) {
            return array_pad(["'" . $row->referencia], 20, null);
        }

        // Fila de encabezados personalizados para cada grupo
        if ($row->created_at === 'FECHA') {
            return $this->headings();
        }

        // Fila separadora vacía
        if ($row->created_at === null && $row->referencia === null) {
            return array_fill(0, 20, null);
        }

        $esServicio = !str_contains($row->referencia, 'SU');

        return [
            $row->created_at?->format('d/m/Y'),
            $row->referencia,
            $esServicio
            ? optional($row->cliente)->razon_social
            : optional($row->proveedor)->razon_social,
            $row->contacto_nombre,
            $row->provincia,
            $row->ayuntamiento,
            $row->monte_parcela,
            $row->ubicacion_gps
            ? '=HYPERLINK("https://maps.google.com/?q=' . urlencode($row->ubicacion_gps) . '")'
            : null,
            $row->producto_tipo,
            $row->producto_especie,
            $row->cantidad_aprox,
            $row->observaciones,
            $row->tipo_certificacion
            ? match ($row->tipo_certificacion) {
                'sure_induestrial' => 'SURE - Industrial',
                'sure_foresal' => 'SURE - Forestal',
                'sbp' => 'SBP',
                'pefc' => 'PEFC',
                default => $row->tipo_certificacion,
            }
            : null,
            $row->guia_sanidad ? 'Sí' : 'No',
            $row->tarifa,
            match ($row->estado) {
                'abierto' => 'Abierto',
                'cerrado' => 'Cerrado',
                'en_proceso' => 'En proceso',
                default => $row->estado ?? '',
            },
            match ($row->en_negociacion) {
                'confirmado' => 'Confirmado',
                'sin_confirmar' => 'Sin confirmar',
                default => $row->en_negociacion ?? '',
            },
            '', // FOTOS (vacío, puedes añadir lógica si tienes imágenes)
            optional($row->usuarios)->pluck('name')->join(', '),
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
                $sheet->setAutoFilter('A1:S1');

                // Ajustar altura según observaciones (columna I)
                for ($row = 2; $row <= $rowCount; $row++) {
                    $contenido = $sheet->getCell("L{$row}")->getValue();
                    $saltos = substr_count((string) $contenido, PHP_EOL);
                    $lineas = floor(strlen((string) $contenido) / 140);
                    $altura = max(15 * ($lineas + $saltos + 1), 16);
                    $sheet->getRowDimension($row)->setRowHeight($altura);
                }

                // Colores según estado en columna K
                for ($row = 2; $row <= $rowCount; $row++) {
                    $estado = (string) $sheet->getCell("P{$row}")->getValue();

                    $color = match ($estado) {
                        'Cerrado' => 'FFCCCC',
                        'En proceso' => 'B8B825',
                        default => null,
                    };

                    if ($color) {
                        $sheet->getStyle("A{$row}:T{$row}")
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
            1 => [ // Fila de encabezado
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'bottom' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ],
            'A:S' => [ // Aplica a todas las columnas de datos
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }
}
