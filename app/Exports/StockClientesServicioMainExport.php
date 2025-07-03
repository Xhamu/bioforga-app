<?php

namespace App\Exports;

use App\Models\Cliente;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StockClientesServicioMainExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $clientesServicio = Cliente::where('tipo_cliente', 'Servicio')->get();

        $sheets = [];

        foreach ($clientesServicio as $cliente) {
            $sheets[] = new StockClientesServicioExport($cliente);
        }

        return $sheets;
    }
}
