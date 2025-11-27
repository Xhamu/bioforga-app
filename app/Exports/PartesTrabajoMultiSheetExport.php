<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PartesTrabajoMultiSheetExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new Sheets\PartesOperacionMaquinaSheet(),
            new Sheets\PartesDesplazamientoSheet(),
            new Sheets\PartesAveriaSheet(),
            new Sheets\PartesOtrosSheet(),
        ];
    }
}
