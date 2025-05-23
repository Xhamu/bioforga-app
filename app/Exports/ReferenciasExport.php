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

class ReferenciasExport implements FromCollection, WithHeadings, WithMapping, WithEvents, WithStyles, ShouldAutoSize
{
    protected $tipo;

    public function __construct(string $tipo)
    {
        $this->tipo = $tipo;
    }

    public function collection()
    {
        $esSuministro = $this->tipo === 'suministro';
        $filtros = ['SUSA', 'SUEX', 'SUOT', 'SUCA'];

        return Referencia::where(function ($query) use ($filtros, $esSuministro) {
            foreach ($filtros as $filtro) {
                $esSuministro
                    ? $query->orWhere('referencia', 'LIKE', "%$filtro%")
                    : $query->where('referencia', 'NOT LIKE', "%$filtro%");
            }
        })->get();
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
        return [
            $row->created_at?->format('d/m/Y'),
            $row->referencia,
            $row->proveedor_id,
            $row->contacto_nombre,
            $row->monte_parcela,
            $row->ayuntamiento,
            '=HYPERLINK("https://maps.google.com/?q=' . $row->ubicacion_gps . '")',
            $row->cantidad_aprox,
            $row->producto_tipo,
            $row->observaciones,
            $row->referencia, // Asumo que aquí hay que ajustar si quieres otro campo
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
