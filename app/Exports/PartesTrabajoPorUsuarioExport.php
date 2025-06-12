<?php

namespace App\Exports;

use App\Models\User;
use App\Models\ParteTrabajoSuministroOperacionMaquina;
use App\Models\ParteTrabajoSuministroDesplazamiento;
use App\Models\ParteTrabajoSuministroAveria;
use App\Models\ParteTrabajoSuministroOtros;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ... namespace y use statements igual que antes ...

class PartesTrabajoPorUsuarioExport implements FromCollection, WithTitle, WithEvents, WithStyles, ShouldAutoSize
{
    public function collection(): Collection
    {
        $cabecera = [
            'Usuario',
            'Tipo',
            'Fecha y hora inicio',
            'GPS inicio',
            'Fecha y hora fin',
            'GPS fin',
            'Máquina',
            'Vehículo',
            'Tipo avería',
            'Trabajo realizado',
            'Descripción',
            'Destino',
            'Horas encendido',
            'Horas rotor',
            'Horas trabajo',
            'Cantidad producida',
            'Tipo cantidad',
            'Consumo gasoil',
            'Consumo cuchillas',
            'Consumo muelas',
            'Horómetro',
            'Observaciones'
        ];

        $exportData = collect();

        foreach (User::all() as $user) {
            $partes = collect();

            // Parte de TRABAJO
            foreach (ParteTrabajoSuministroOperacionMaquina::with('maquina')->where('usuario_id', $user->id)->get() as $p) {
                $partes->push([
                    'Usuario' => $user->name,
                    'Tipo' => 'Trabajo',
                    'Fecha y hora inicio' => optional($p->fecha_hora_inicio_trabajo)?->format('d/m/Y H:i'),
                    'GPS inicio' => $p->gps_inicio_trabajo,
                    'Fecha y hora fin' => optional($p->fecha_hora_fin_trabajo)?->format('d/m/Y H:i'),
                    'GPS fin' => $p->gps_fin_trabajo,
                    'Máquina' => $p->maquina->marca_modelo ?? null,
                    'Vehículo' => null,
                    'Tipo avería' => null,
                    'Trabajo realizado' => null,
                    'Descripción' => null,
                    'Destino' => null,
                    'Horas encendido' => $p->horas_encendido,
                    'Horas rotor' => $p->horas_rotor,
                    'Horas trabajo' => $p->horas_trabajo,
                    'Cantidad producida' => $p->cantidad_producida,
                    'Tipo cantidad' => $p->tipo_cantidad_producida,
                    'Consumo gasoil' => $p->consumo_gasoil,
                    'Consumo cuchillas' => $p->consumo_cuchillas,
                    'Consumo muelas' => $p->consumo_muelas,
                    'Horómetro' => $p->horometro,
                    'Observaciones' => $p->observaciones,
                ]);
            }

            // Parte de DESPLAZAMIENTO
            foreach (ParteTrabajoSuministroDesplazamiento::with('vehiculo')->where('usuario_id', $user->id)->get() as $p) {
                $partes->push([
                    'Usuario' => $user->name,
                    'Tipo' => 'Desplazamiento',
                    'Fecha y hora inicio' => optional($p->fecha_hora_inicio_desplazamiento)?->format('d/m/Y H:i'),
                    'GPS inicio' => $p->gps_inicio_desplazamiento,
                    'Fecha y hora fin' => optional($p->fecha_hora_fin_desplazamiento)?->format('d/m/Y H:i'),
                    'GPS fin' => $p->gps_fin_desplazamiento,
                    'Máquina' => null,
                    'Vehículo' => $p->vehiculo->marca_modelo ?? null,
                    'Tipo avería' => null,
                    'Trabajo realizado' => null,
                    'Descripción' => null,
                    'Destino' => $p->destino,
                    'Horas encendido' => null,
                    'Horas rotor' => null,
                    'Horas trabajo' => null,
                    'Cantidad producida' => null,
                    'Tipo cantidad' => null,
                    'Consumo gasoil' => null,
                    'Consumo cuchillas' => null,
                    'Consumo muelas' => null,
                    'Horómetro' => null,
                    'Observaciones' => $p->observaciones,
                ]);
            }

            // Parte de AVERÍA
            foreach (ParteTrabajoSuministroAveria::with('maquina')->where('usuario_id', $user->id)->get() as $p) {
                $partes->push([
                    'Usuario' => $user->name,
                    'Tipo' => 'Avería',
                    'Fecha y hora inicio' => optional($p->fecha_hora_inicio_averia)?->format('d/m/Y H:i'),
                    'GPS inicio' => $p->gps_inicio_averia,
                    'Fecha y hora fin' => optional($p->fecha_hora_fin_averia)?->format('d/m/Y H:i'),
                    'GPS fin' => $p->gps_fin_averia,
                    'Máquina' => $p->maquina->marca_modelo ?? null,
                    'Vehículo' => null,
                    'Tipo avería' => $p->tipo,
                    'Trabajo realizado' => $p->trabajo_realizado,
                    'Descripción' => null,
                    'Destino' => null,
                    'Horas encendido' => null,
                    'Horas rotor' => null,
                    'Horas trabajo' => null,
                    'Cantidad producida' => null,
                    'Tipo cantidad' => null,
                    'Consumo gasoil' => null,
                    'Consumo cuchillas' => null,
                    'Consumo muelas' => null,
                    'Horómetro' => null,
                    'Observaciones' => $p->observaciones,
                ]);
            }

            // Parte de OTROS
            foreach (ParteTrabajoSuministroOtros::where('usuario_id', $user->id)->get() as $p) {
                $partes->push([
                    'Usuario' => $user->name,
                    'Tipo' => 'Otros',
                    'Fecha y hora inicio' => optional($p->fecha_hora_inicio_otros)?->format('d/m/Y H:i'),
                    'GPS inicio' => $p->gps_inicio_otros,
                    'Fecha y hora fin' => optional($p->fecha_hora_fin_otros)?->format('d/m/Y H:i'),
                    'GPS fin' => $p->gps_fin_otros,
                    'Máquina' => null,
                    'Vehículo' => null,
                    'Tipo avería' => null,
                    'Trabajo realizado' => null,
                    'Descripción' => $p->descripcion,
                    'Destino' => null,
                    'Horas encendido' => null,
                    'Horas rotor' => null,
                    'Horas trabajo' => null,
                    'Cantidad producida' => null,
                    'Tipo cantidad' => null,
                    'Consumo gasoil' => null,
                    'Consumo cuchillas' => null,
                    'Consumo muelas' => null,
                    'Horómetro' => null,
                    'Observaciones' => $p->observaciones,
                ]);
            }

            // Orden por mes (abril antes que junio)
            $partes = $partes->sortBy([
                fn($a, $b) => substr($a['Fecha y hora inicio'], 3, 2) <=> substr($b['Fecha y hora inicio'], 3, 2),
                fn($a, $b) => strtotime($a['Fecha y hora inicio']) <=> strtotime($b['Fecha y hora inicio']),
            ]);

            // Encabezado
            if ($exportData->isEmpty()) {
                $exportData->push($cabecera);
            } else {
                $exportData->push(array_fill_keys($cabecera, ''));
            }

            foreach ($partes as $fila) {
                $exportData->push($fila);
            }
        }

        return $exportData;
    }

    public function title(): string
    {
        return 'Partes de trabajo';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowCount = $sheet->getHighestRow();

                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:W1');

                for ($row = 2; $row <= $rowCount; $row++) {
                    $contenido = $sheet->getCell("W{$row}")->getValue(); // Observaciones
                    $saltos = substr_count((string) $contenido, PHP_EOL);
                    $lineas = floor(strlen((string) $contenido) / 140);
                    $altura = max(15 * ($lineas + $saltos + 1), 16);
                    $sheet->getRowDimension($row)->setRowHeight($altura);
                }
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
            'A:W' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }
}
