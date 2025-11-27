<?php

namespace App\Exports\Sheets;

use App\Models\ParteTrabajoSuministroDesplazamiento;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class PartesDesplazamientoSheet implements
    FromCollection,
    WithTitle,
    WithHeadings,
    WithMapping,
    WithEvents,
    WithStyles,
    ShouldAutoSize
{
    public function title(): string
    {
        return 'Desplazamientos';
    }

    public function headings(): array
    {
        return [
            'Usuario',
            'Fecha y hora inicio',
            'GPS inicio',
            'Fecha y hora fin',
            'GPS fin',
            'Vehículo',
            'Origen',
            'Destino',
            'Km inicio',
            'Km fin',
            'Km recorridos',
            'Observaciones',
        ];
    }

    public function collection(): Collection
    {
        return ParteTrabajoSuministroDesplazamiento::with(['usuario', 'vehiculo'])
            ->orderBy('fecha_hora_inicio_desplazamiento')
            ->get();
    }

    public function map($row): array
    {
        return [
            $row->usuario?->name,
            $row->fecha_hora_inicio_desplazamiento?->format('d/m/Y H:i'),
            $row->gps_inicio_desplazamiento,
            $row->fecha_hora_fin_desplazamiento?->format('d/m/Y H:i'),
            $row->gps_fin_desplazamiento,
            $row->vehiculo?->marca_modelo,
            $row->origen,
            $row->destino,
            $row->km_inicio,
            $row->km_fin,
            $row->km_recorridos,
            $row->observaciones,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:L1');
            },
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
                'borders'   => [
                    'bottom' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ],
            'A:L' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true,
                ],
            ],
        ];
    }
}
