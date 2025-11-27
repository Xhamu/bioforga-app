<?php

namespace App\Exports\Sheets;

use App\Models\ParteTrabajoSuministroOperacionMaquina;
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

class PartesOperacionMaquinaSheet implements
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
        return 'Operación máquina';
    }

    public function headings(): array
    {
        return [
            'Usuario',
            'Referencia',
            'Fecha y hora inicio',
            'GPS inicio',
            'Fecha y hora fin',
            'GPS fin',
            'Máquina',
            'Tipo trabajo',
            'Horas encendido',
            'Horas rotor',
            'Horas trabajo',
            'Cantidad producida',
            'Tipo cantidad',
            'Consumo gasoil',
            'Consumo cuchillas',
            'Consumo muelas',
            'Horómetro',
            'Observaciones',
        ];
    }

    public function collection(): Collection
    {
        return ParteTrabajoSuministroOperacionMaquina::with(['usuario', 'referencia', 'maquina'])
            ->orderBy('fecha_hora_inicio_trabajo')
            ->get();
    }

    public function map($row): array
    {
        return [
            $row->usuario?->name,
            $row->referencia?->referencia,
            $row->fecha_hora_inicio_trabajo?->format('d/m/Y H:i'),
            $row->gps_inicio_trabajo,
            $row->fecha_hora_fin_trabajo?->format('d/m/Y H:i'),
            $row->gps_fin_trabajo,
            $row->maquina?->marca_modelo,
            $row->tipo_trabajo,
            $row->horas_encendido,
            $row->horas_rotor,
            $row->horas_trabajo,
            $row->cantidad_producida,
            $row->tipo_cantidad_producida,
            $row->consumo_gasoil,
            $row->consumo_cuchillas,
            $row->consumo_muelas,
            $row->horometro,
            $row->observaciones,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet    = $event->sheet->getDelegate();
                $rowCount = $sheet->getHighestRow();

                // Congelar cabecera
                $sheet->freezePane('A2');

                // Autofiltro
                $sheet->setAutoFilter('A1:R1');

                // Ajustar altura según Observaciones (columna R)
                for ($row = 2; $row <= $rowCount; $row++) {
                    $contenido = (string) $sheet->getCell("R{$row}")->getValue();
                    $saltos    = substr_count($contenido, PHP_EOL);
                    $lineas    = floor(strlen($contenido) / 140);
                    $altura    = max(15 * ($lineas + $saltos + 1), 16);

                    $sheet->getRowDimension($row)->setRowHeight($altura);
                }
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
            'A:R' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true,
                ],
            ],
        ];
    }
}
