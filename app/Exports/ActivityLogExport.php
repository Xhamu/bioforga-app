<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Events\AfterSheet;
use Spatie\Activitylog\Models\Activity;

class ActivityLogExport implements FromCollection, WithHeadings, WithEvents, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        return Activity::latest()->get()->map(function ($log) {
            return [
                'Acción' => $this->traducirAccion($log->description),
                'Usuario' => $log->causer?->name . ' ' . $log->causer?->apellidos,
                'Email' => $log->causer?->email,
                'Fecha' => $log->created_at->format('d/m/Y H:i'),
                'Cambios' => $this->resumenCambios($log),
                'IP' => $log->properties['ip'] ?? '—',
                'Navegador' => $log->properties['user_agent'] ?? '—',
            ];
        });
    }

    public function headings(): array
    {
        return ['Acción', 'Usuario', 'Email', 'Fecha', 'Cambios', 'IP', 'Navegador'];
    }

    protected function traducirAccion(string $accion): string
    {
        return match ($accion) {
            'updated' => 'Actualización',
            'created' => 'Creación',
            'deleted' => 'Eliminación',
            'login' => 'Inicio de sesión',
            'logout' => 'Cierre de sesión',
            default => ucfirst($accion),
        };
    }

    protected function resumenCambios(Activity $record): string
    {
        $changes = $record->properties['attributes'] ?? [];
        $old = $record->properties['old'] ?? [];

        unset($changes['updated_at'], $old['updated_at']);

        $changes = collect($changes)->filter(function ($new, $key) use ($old) {
            return !is_null($new) && ($old[$key] ?? null) !== $new;
        });

        if ($changes->isEmpty()) {
            return '—';
        }

        $lineas = $changes->map(function ($new, $key) use ($old) {
            $oldVal = is_array($old[$key] ?? null) ? json_encode($old[$key]) : ($old[$key] ?? '—');
            $newVal = is_array($new) ? json_encode($new) : $new;

            return "{$key}: \"{$oldVal}\" → \"{$newVal}\"";
        });

        return $lineas->implode(PHP_EOL);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowCount = $sheet->getHighestRow();

                // Congelar encabezado
                $sheet->freezePane('A2');

                // Filtro automático
                $sheet->setAutoFilter('A1:G1');

                // Altura dinámica para navegador (columna F)
                for ($row = 2; $row <= $rowCount; $row++) {
                    $contenido = $sheet->getCell("E{$row}")->getValue();
                    $saltos = substr_count((string) $contenido, PHP_EOL);
                    $lineas = floor(strlen((string) $contenido) / 100);
                    $altura = max(15 * ($lineas + $saltos + 1), 16);
                    $sheet->getRowDimension($row)->setRowHeight($altura);
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
            'A:G' => [ // Todas las columnas
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }
}
