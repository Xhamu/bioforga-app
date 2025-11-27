<?php

namespace App\Exports\Sheets;

use App\Models\ParteTrabajoSuministroOtros;
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

class PartesOtrosSheet implements
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
        return 'Otros';
    }

    public function headings(): array
    {
        return [
            'Usuario',
            'Fecha y hora inicio',
            'GPS inicio',
            'Fecha y hora fin',
            'GPS fin',
            'Descripción',
            'Observaciones',
        ];
    }

    public function collection(): Collection
    {
        return ParteTrabajoSuministroOtros::with('usuario')
            ->orderBy('fecha_hora_inicio_otros')
            ->get();
    }

    public function map($row): array
    {
        return [
            $row->usuario?->name,
            $row->fecha_hora_inicio_otros?->format('d/m/Y H:i'),
            $row->gps_inicio_otros,
            $row->fecha_hora_fin_otros?->format('d/m/Y H:i'),
            $row->gps_fin_otros,
            $row->descripcion,
            $row->observaciones,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:G1');
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
            'A:G' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }
}
