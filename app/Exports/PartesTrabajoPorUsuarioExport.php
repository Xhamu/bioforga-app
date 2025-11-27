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
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class PartesTrabajoPorUsuarioExport implements FromCollection, WithTitle, WithEvents, WithStyles, ShouldAutoSize
{
    /**
     * Cabeceras del Excel (y orden de columnas).
     */
    private const HEADERS = [
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
        'Observaciones',
    ];

    public function collection(): Collection
    {
        $exportData = collect();

        // Primera fila: cabeceras
        $exportData->push(self::HEADERS);

        /** @var \App\Models\User $user */
        foreach (User::orderBy('name')->get() as $user) {
            $partes = collect();

            // ================== TRABAJO (operación máquina) ==================
            ParteTrabajoSuministroOperacionMaquina::with('maquina')
                ->where('usuario_id', $user->id)
                ->get()
                ->each(function ($p) use (&$partes, $user) {
                    $partes->push([
                        'sort_key' => optional($p->fecha_hora_inicio_trabajo)?->timestamp ?? 0,
                        'Usuario' => $user->name,
                        'Tipo' => 'Trabajo',
                        'Fecha y hora inicio' => $this->formatDateTime($p->fecha_hora_inicio_trabajo),
                        'GPS inicio' => $p->gps_inicio_trabajo,
                        'Fecha y hora fin' => $this->formatDateTime($p->fecha_hora_fin_trabajo),
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
                });

            // ================== DESPLAZAMIENTO ==================
            ParteTrabajoSuministroDesplazamiento::with('vehiculo')
                ->where('usuario_id', $user->id)
                ->get()
                ->each(function ($p) use (&$partes, $user) {
                    $partes->push([
                        'sort_key' => optional($p->fecha_hora_inicio_desplazamiento)?->timestamp ?? 0,
                        'Usuario' => $user->name,
                        'Tipo' => 'Desplazamiento',
                        'Fecha y hora inicio' => $this->formatDateTime($p->fecha_hora_inicio_desplazamiento),
                        'GPS inicio' => $p->gps_inicio_desplazamiento,
                        'Fecha y hora fin' => $this->formatDateTime($p->fecha_hora_fin_desplazamiento),
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
                });

            // ================== AVERÍA ==================
            ParteTrabajoSuministroAveria::with('maquina')
                ->where('usuario_id', $user->id)
                ->get()
                ->each(function ($p) use (&$partes, $user) {
                    $partes->push([
                        'sort_key' => optional($p->fecha_hora_inicio_averia)?->timestamp ?? 0,
                        'Usuario' => $user->name,
                        'Tipo' => 'Avería',
                        'Fecha y hora inicio' => $this->formatDateTime($p->fecha_hora_inicio_averia),
                        'GPS inicio' => $p->gps_inicio_averia,
                        'Fecha y hora fin' => $this->formatDateTime($p->fecha_hora_fin_averia),
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
                });

            // ================== OTROS ==================
            ParteTrabajoSuministroOtros::where('usuario_id', $user->id)
                ->get()
                ->each(function ($p) use (&$partes, $user) {
                    $partes->push([
                        'sort_key' => optional($p->fecha_hora_inicio_otros)?->timestamp ?? 0,
                        'Usuario' => $user->name,
                        'Tipo' => 'Otros',
                        'Fecha y hora inicio' => $this->formatDateTime($p->fecha_hora_inicio_otros),
                        'GPS inicio' => $p->gps_inicio_otros,
                        'Fecha y hora fin' => $this->formatDateTime($p->fecha_hora_fin_otros),
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
                });

            // Si el usuario no tiene ningún parte, no metemos nada en el Excel
            if ($partes->isEmpty()) {
                continue;
            }

            // Ordenar por la clave temporal
            $partes = $partes
                ->sortBy('sort_key')
                ->map(function (array $fila) {
                    unset($fila['sort_key']);

                    // Garantizar el orden de columnas según HEADERS
                    return collect(self::HEADERS)
                        ->map(fn(string $col) => $fila[$col] ?? null)
                        ->values()
                        ->all();
                });

            // Fila separadora entre usuarios (fila vacía)
            if ($exportData->count() > 1) {
                $exportData->push(array_fill(0, count(self::HEADERS), null));
            }

            // Añadir filas del usuario
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
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $rowCount = $sheet->getHighestRow();

                // Congelar cabecera
                $sheet->freezePane('A2');

                // Autofiltro sobre todas las columnas reales (A..V)
                $sheet->setAutoFilter('A1:V1');

                // Ajustar altura de filas según longitud de "Observaciones" (columna V)
                for ($row = 2; $row <= $rowCount; $row++) {
                    $contenido = (string) $sheet->getCell("V{$row}")->getValue();
                    $saltos = substr_count($contenido, PHP_EOL);
                    $lineas = floor(strlen($contenido) / 140);
                    $altura = max(15 * ($lineas + $saltos + 1), 16);

                    $sheet->getRowDimension($row)->setRowHeight($altura);
                }
            },
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Fila de encabezado
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

            // Estilo general columnas A..V
            'A:V' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }

    /**
     * Formatea fecha/hora a d/m/Y H:i o devuelve null.
     */
    private function formatDateTime($value): ?string
    {
        return $value ? $value->format('d/m/Y H:i') : null;
    }
}
