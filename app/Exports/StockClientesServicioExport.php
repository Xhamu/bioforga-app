<?php

namespace App\Exports;

use App\Models\Cliente;
use App\Models\Referencia;
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

class StockClientesServicioExport implements FromCollection, WithTitle, WithEvents, WithStyles, ShouldAutoSize
{
    protected Cliente $cliente;

    public function __construct(Cliente $cliente)
    {
        $this->cliente = $cliente;
    }

    public function collection(): Collection
    {
        $datosCliente = collect();

        // Puedes añadir aquí una cabecera con los datos del cliente si quieres
        $datosCliente->push([
            'Cliente' => $this->cliente->razon_social,
            'NIF' => $this->cliente->nif,
            'Dirección' => $this->cliente->direccion,
            'Teléfono' => $this->cliente->telefono_principal,
        ]);

        $referencias = Referencia::where('cliente_id', $this->cliente->id)->get();

        if ($referencias->isEmpty()) {
            $datosCliente->push([
                'Referencia' => 'Este cliente no tiene referencias registradas.',
            ]);
        } else {
            $datosCliente->push([
                'Referencia' => 'LISTADO DE REFERENCIAS Y TRABAJOS:',
            ]);

            foreach ($referencias as $referencia) {
                $tituloReferencia = $referencia->referencia . ' (' . $referencia->ayuntamiento . ', ' . $referencia->monte_parcela . ')';

                $datosCliente->push([
                    'Referencia' => $tituloReferencia ?: 'Referencia sin nombre',
                ]);

                // Buscar trabajos realizados en esta referencia
                $trabajos = \App\Models\ParteTrabajoSuministroOperacionMaquina::where('referencia_id', $referencia->id)
                    ->whereNotNull('fecha_hora_fin_trabajo')
                    ->get();

                if ($trabajos->isEmpty()) {
                    $datosCliente->push([
                        'Trabajo' => 'No se han registrado trabajos en esta referencia.',
                    ]);
                } else {
                    // Cabecera de columnas para los trabajos
                    $datosCliente->push([
                        'Trabajo' => 'Fecha inicio',
                        'Usuario' => 'Usuario',
                        'Máquina' => 'Máquina',
                        'Cantidad producida' => 'Cantidad',
                        'Consumo gasoil' => 'Gasoil',
                        'Observaciones' => 'Observaciones',
                    ]);

                    foreach ($trabajos as $trabajo) {
                        $datosCliente->push([
                            'Trabajo' => optional($trabajo->fecha_hora_inicio_trabajo)->format('d/m/Y H:i'),
                            'Usuario' => optional($trabajo->usuario)->name ?? 'N/A',
                            'Máquina' => optional($trabajo->maquina)->marca . ' ' . optional($trabajo->maquina)->modelo ?? 'N/A',
                            'Cantidad producida' => ($trabajo->cantidad_producida ? $trabajo->cantidad_producida . ' ' . $trabajo->tipo_cantidad_producida : '-'),
                            'Consumo gasoil' => $trabajo->consumo_gasoil ?? '-',
                            'Observaciones' => $trabajo->observaciones ?? '',
                        ]);
                    }
                }
            }
        }

        return $datosCliente;
    }

    public function title(): string
    {
        return substr($this->cliente->razon_social, 0, 31);
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
                    $contenido = $sheet->getCell("A{$row}")->getValue();

                    // Ajuste de altura si hay mucho texto
                    $saltos = substr_count((string) $contenido, PHP_EOL);
                    $lineas = floor(strlen((string) $contenido) / 140);
                    $altura = max(15 * ($lineas + $saltos + 1), 16);
                    $sheet->getRowDimension($row)->setRowHeight($altura);

                    // Pintar solo las filas que parecen ser títulos de referencias
                    if (
                        $contenido &&                          // No vacío
                        !str_starts_with($contenido, 'LISTADO DE REFERENCIAS') && // No es cabecera general
                        !str_starts_with($contenido, 'Fecha inicio') &&           // No es cabecera de trabajos
                        !str_starts_with($contenido, 'No se han registrado') &&   // No es mensaje sin trabajos
                        strlen($contenido) > 5                // Para evitar falsos positivos en líneas vacías o muy cortas
                    ) {
                        $sheet->getStyle("A{$row}:W{$row}")
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setARGB('FFE4B5'); // Color de fondo beige claro
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
