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

        // Definir columnas fijas
        $columnas = [
            'REFERENCIA',
            'USUARIOS',
            'MÁQUINA',
            'CANTIDAD',
            'CONSUMO',
            'OBSERVACIONES',
        ];

        // Cabecera
        $datosCliente->push(array_combine($columnas, [
            'REFERENCIA',
            'USUARIO',
            'MÁQUINA',
            'CANTIDAD',
            'CONSUMO',
            'OBSERVACIONES'
        ]));

        $referencias = Referencia::where('cliente_id', $this->cliente->id)->get();

        if ($referencias->isEmpty()) {
            $datosCliente->push(array_combine($columnas, [
                'Este cliente no tiene referencias registradas.',
                '',
                '',
                '',
                '',
                ''
            ]));
        } else {
            $datosCliente->push(array_combine($columnas, [
                'LISTADO DE REFERENCIAS Y TRABAJOS:',
                '',
                '',
                '',
                '',
                ''
            ]));

            foreach ($referencias as $referencia) {
                $tituloReferencia = $referencia->referencia . ' (' . $referencia->ayuntamiento . ', ' . $referencia->monte_parcela . ')';

                $datosCliente->push(array_combine($columnas, [
                    'REF: ' . ($tituloReferencia ?: 'Referencia sin nombre'),
                    '',
                    '',
                    '',
                    '',
                    ''
                ]));

                $trabajos = \App\Models\ParteTrabajoSuministroOperacionMaquina::where('referencia_id', $referencia->id)
                    ->whereNotNull('fecha_hora_fin_trabajo')
                    ->get();

                if ($trabajos->isEmpty()) {
                    $datosCliente->push(array_combine($columnas, [
                        'No se han registrado trabajos en esta referencia.',
                        '',
                        '',
                        '',
                        '',
                        ''
                    ]));
                } else {
                    // Cabecera para las filas de trabajos
                    $datosCliente->push(array_combine($columnas, [
                        'Fecha inicio',
                        'Usuario',
                        'Máquina',
                        'Cantidad',
                        'Gasoil',
                        'Observaciones'
                    ]));

                    foreach ($trabajos as $trabajo) {
                        $datosCliente->push(array_combine($columnas, [
                            optional($trabajo->fecha_hora_inicio_trabajo)->format('d/m/Y H:i') ?? '-',
                            (optional($trabajo->usuario)->name . ' ' . optional($trabajo->usuario)->apellidos) ?: 'N/A',
                            (optional($trabajo->maquina)->marca . ' ' . optional($trabajo->maquina)->modelo) ?: 'N/A',
                            $trabajo->cantidad_producida ? $trabajo->cantidad_producida . ' ' . $trabajo->tipo_cantidad_producida : '-',
                            $trabajo->consumo_gasoil ?? '-',
                            $trabajo->observaciones ?? '',
                        ]));
                    }
                }
            }
        }

        return $datosCliente;
    }

    public function title(): string
    {
        // Evita los errores al abrir debido a los caracteres no válidos de los proveedores.
        $raw = substr($this->cliente->razon_social, 0, 31);

        $sanitized = str_replace([':', '\\', '/', '?', '*', '[', ']'], '', $raw);

        $sanitized = preg_replace('/[\x00-\x1F]/u', '', $sanitized);

        return $sanitized ?: 'SinNombre';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowCount = $sheet->getHighestRow();

                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:F1');

                for ($row = 2; $row <= $rowCount; $row++) {
                    $contenido = $sheet->getCell("A{$row}")->getValue();

                    // Ajuste de altura dinámico
                    $saltos = substr_count((string) $contenido, PHP_EOL);
                    $lineas = floor(strlen((string) $contenido) / 140);
                    $altura = max(15 * ($lineas + $saltos + 1), 16);
                    $sheet->getRowDimension($row)->setRowHeight($altura);

                    // Pintar líneas de referencias con 'REF:'
                    if (str_starts_with((string) $contenido, 'REF:')) {
                        $sheet->getStyle("A{$row}:F{$row}")
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setARGB('FFE4B5');
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
            'A:F' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
            ],
        ];
    }
}
