<?php

namespace App\Exports;

use App\Models\Referencia;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReferenciasFiltradasExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithEvents,
    WithStyles
{
    /** @var \Illuminate\Support\Collection<int, \App\Models\Referencia> */
    protected Collection $referencias;

    public function __construct(Collection $referencias)
    {
        $this->referencias = $referencias;
    }

    public function collection(): Collection
    {
        return $this->referencias;
    }

    public function headings(): array
    {
        return [
            'FECHA CREACIÓN',
            'REFERENCIA',
            'PROVEEDOR / CLIENTE',
            'PROVINCIA',
            'MUNICIPIO',
            'MONTE / PARCELA',
            'UBICACIÓN GPS',
            'TIPO',
            'ESPECIE',
            'CANTIDAD APROX.',
            'TIPO CANTIDAD',
            'ESTADO',
            'NEGOCIACIÓN',
            'OBSERVACIONES',
            'USUARIOS',
        ];
    }

    public function map($row): array
    {
        /** @var Referencia $row */
        $esServicio = !str_contains((string) $row->referencia, 'SU');

        $interviniente = $esServicio
            ? optional($row->cliente)->razon_social
            : optional($row->proveedor)->razon_social;

        $estado = match ($row->estado) {
            'abierto' => 'Abierto',
            'en_proceso' => 'En proceso',
            'cerrado' => 'Cerrado',
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
            $interviniente,
            $row->provincia,
            $row->ayuntamiento,
            $row->monte_parcela,
            $row->ubicacion_gps,
            $row->producto_tipo,
            $row->producto_especie,
            $row->cantidad_aprox,
            $row->tipo_cantidad,
            $estado,
            $negociacion,
            $row->observaciones,
            optional($row->usuarios)->pluck('name')->join(', '),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $rowCount = $sheet->getHighestRow();

                // Congelar encabezado
                $sheet->freezePane('A2');

                // Autofiltro en la fila 1
                $sheet->setAutoFilter('A1:O1');
            },
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
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
            'A:O' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }
}
