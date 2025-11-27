<?php

namespace App\Exports;

use App\Models\Referencia;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReferenciasExport implements FromCollection, WithHeadings, WithMapping, WithEvents, WithStyles, ShouldAutoSize
{
    /**
     * @var 'suministro'|'servicio'
     */
    protected string $tipo;

    // Número de columnas reales (A..S => 19)
    private const COLS = 19;

    public function __construct(string $tipo)
    {
        $this->tipo = $tipo;
    }

    public function collection(): Collection
    {
        $filtros = ['SUSA', 'SUEX', 'SUOT', 'SUCA'];

        // ================= SERVICIO =================
        if ($this->tipo === 'servicio') {
            $referencias = Referencia::query()
                ->with(['cliente', 'usuarios'])
                ->where(function ($query) use ($filtros) {
                    foreach ($filtros as $filtro) {
                        $query->where('referencia', 'NOT LIKE', "%{$filtro}%");
                    }
                })
                ->orderBy('cliente_id')
                ->orderBy('created_at')
                ->get()
                ->groupBy(fn($item) => optional($item->cliente)->razon_social ?? 'Sin cliente');

            $bloques = collect();

            foreach ($referencias as $cliente => $items) {
                // Título de grupo
                $bloques->push((object) [
                    'created_at' => null,
                    'referencia' => "=== {$cliente} ===",
                ]);

                // Datos
                foreach ($items as $d) {
                    $bloques->push($d);
                }

                // Separador visual
                $bloques->push($this->emptySeparatorRow());
            }

            return $bloques;
        }

        // ================= SUMINISTRO =================
        $bloques = collect();

        foreach ($filtros as $tipo) {
            $datos = Referencia::query()
                ->with(['proveedor', 'usuarios'])
                ->where('referencia', 'LIKE', "%{$tipo}%")
                ->orderBy('created_at')
                ->get();

            if ($datos->isEmpty()) {
                continue;
            }

            $nombreMapeado = match ($tipo) {
                'SUSA' => 'Saca',
                'SUEX' => 'Explotación',
                'SUCA' => 'Cargadero',
                'SUOT' => 'Otros',
                default => $tipo,
            };

            // Título de grupo
            $bloques->push((object) [
                'created_at' => null,
                'referencia' => "=== {$nombreMapeado} ===",
            ]);

            // Datos
            foreach ($datos as $d) {
                $bloques->push($d);
            }

            // Separador visual
            $bloques->push($this->emptySeparatorRow());
        }

        return $bloques;
    }

    /**
     * Cabecera estándar (fila 1 y cabeceras de cada bloque)
     */
    public function headings(): array
    {
        return [
            'FECHA',
            'REFERENCIA',
            'PROVEEDOR / CLIENTE',
            'CONTACTO',
            'PROVINCIA',
            'MUNICIPIO',
            'MONTE',
            'UBICACIÓN',
            'TIPO',
            'ESPECIE',
            'CANTIDAD',
            'OBSERVACIONES',
            'CERTIFICACIÓN',
            'G. SANIDAD',
            'TARIFA / UNIDAD',
            'ESTADO',
            'NEGOCIACIÓN',
            'FOTOS',
            'USUARIOS',
        ];
    }

    /**
     * Fila separadora en blanco entre bloques.
     */
    protected function emptySeparatorRow(): object
    {
        return (object) [
            'created_at' => null,
            'referencia' => null,
            'proveedor_id' => null,
            'cliente_id' => null,
            'contacto_nombre' => null,
            'monte_parcela' => null,
            'provincia' => null,
            'ayuntamiento' => null,
            'ubicacion_gps' => null,
            'cantidad_aprox' => null,
            'producto_tipo' => null,
            'producto_especie' => null,
            'observaciones' => null,
            'tipo_certificacion' => null,
            'guia_sanidad' => null,
            'tarifa' => null,
            'estado' => null,
            'en_negociacion' => null,
        ];
    }

    public function map($row): array
    {
        // ========= Fila de título de grupo (=== X ===) =========
        if ($row->created_at === null && !empty($row->referencia) && str_starts_with((string) $row->referencia, '=== ')) {
            // Prefijamos con ' para que Excel NO lo trate como fórmula
            return array_pad(["'" . (string) $row->referencia], self::COLS, null);
        }

        // ========= Fila cabecera interna de bloque =========
        if ($row->created_at === 'FECHA') {
            return $this->headings();
        }

        // ========= Fila separadora en blanco =========
        if ($row->created_at === null && empty($row->referencia)) {
            return array_fill(0, self::COLS, null);
        }

        // ========= Fila de datos "normal" =========
        $esServicio = !str_contains((string) $row->referencia, 'SU');

        $clienteOProveedor = $esServicio
            ? optional($row->cliente)->razon_social
            : optional($row->proveedor)->razon_social;

        // Link de Google Maps (como fórmula de Excel)
        $gps = $row->ubicacion_gps
            ? '=HYPERLINK("https://maps.google.com/?q=' . urlencode($row->ubicacion_gps) . '","Ver mapa")'
            : null;

        // Mapeo tipos de certificación
        $certificacion = $row->tipo_certificacion
            ? match ($row->tipo_certificacion) {
                'sure_induestrial' => 'SURE - Industrial',
                'sure_foresal' => 'SURE - Forestal',
                'sbp' => 'SBP',
                'pefc' => 'PEFC',
                default => $row->tipo_certificacion,
            }
            : null;

        // Tarifa / unidad un poco más legible
        $tarifa = $row->tarifa
            ? match ($row->tarifa) {
                'toneladas' => '€/tonelada',
                'm3' => '€/m³',
                'hora' => '€/hora',
                default => $row->tarifa,
            }
            : null;

        // Estado y negociación en texto legible
        $estado = match ($row->estado) {
            'abierto' => 'Abierto',
            'cerrado' => 'Cerrado',
            'en_proceso' => 'En proceso',
            'cerrado_no_procede' => 'Cerrado (no procede)',
            default => $row->estado ?? '',
        };

        $negociacion = match ($row->en_negociacion) {
            'confirmado' => 'Confirmado',
            'sin_confirmar' => 'Sin confirmar',
            default => $row->en_negociacion ?? '',
        };

        return [
            optional($row->created_at)?->format('d/m/Y'),
            $row->referencia,
            $clienteOProveedor,
            $row->contacto_nombre,
            $row->provincia,
            $row->ayuntamiento,
            $row->monte_parcela,
            $gps,
            $row->producto_tipo,
            $row->producto_especie,
            $row->cantidad_aprox,
            $row->observaciones,
            $certificacion,
            $row->guia_sanidad ? 'Sí' : 'No',
            $tarifa,
            $estado,
            $negociacion,
            '', // FOTOS (reservado para futuro)
            optional($row->usuarios)->pluck('name')->join(', '),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $rowCount = $sheet->getHighestRow();

                // Congelar encabezado (fila 1)
                $sheet->freezePane('A2');

                // Autofiltro sobre fila de encabezado
                $sheet->setAutoFilter('A1:S1');

                // Ajustar altura de filas según longitud de OBSERVACIONES (columna L)
                for ($row = 2; $row <= $rowCount; $row++) {
                    $contenido = (string) $sheet->getCell("L{$row}")->getValue();
                    $saltos = substr_count($contenido, PHP_EOL);
                    $lineas = floor(strlen($contenido) / 140);
                    $altura = max(15 * ($lineas + $saltos + 1), 16);

                    $sheet->getRowDimension($row)->setRowHeight($altura);
                }

                // Colorear filas según ESTADO (columna P)
                for ($row = 2; $row <= $rowCount; $row++) {
                    $estado = (string) $sheet->getCell("P{$row}")->getValue();

                    $color = match ($estado) {
                        'Cerrado', 'Cerrado (no procede)' => 'FFCCCC', // rojo suave
                        'En proceso' => 'FFF7C2', // amarillo suave
                        default => null,
                    };

                    if ($color) {
                        $sheet->getStyle("A{$row}:S{$row}")
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
            // Fila de encabezado principal
            1 => [
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'bottom' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ],

            // Estilo general columnas A..S
            'A:S' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }
}
