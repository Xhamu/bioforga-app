<?php

namespace Database\Seeders;

use App\Models\Poblacion;
use App\Models\Provincia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class PoblacionesSeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(resource_path('data/ubicaciones.json'));
        $data = json_decode($json, true);

        $mapaProvincias = [
            'Balears, Illes' => 'Illes Balears',
            'Palmas, Las' => 'Las Palmas',
            'Alicante/Alacant' => 'Alicante',
            'Castellón/Castelló' => 'Castellón',
            'Valencia/València' => 'Valencia',
            'Coruña, A' => 'A Coruña',
            'Araba/Álava' => 'Álava',
            'Bizkaia' => 'Vizcaya',
            'Gipuzkoa' => 'Guipúzcoa',
            'Rioja, La' => 'La Rioja',
            'Ceuta' => null,
            'Melilla' => null,
        ];

        foreach ($data as $pais) {
            foreach ($pais['provinces'] as $provinciaData) {
                $nombreOriginal = $provinciaData['label'];
                $nombreNormalizado = $mapaProvincias[$nombreOriginal] ?? $nombreOriginal;

                if (!$nombreNormalizado) {
                    $this->command->warn("⚠️ Provincia excluida: $nombreOriginal");
                    continue;
                }

                $provincia = Provincia::where('nombre', $nombreNormalizado)->first();

                if (!$provincia) {
                    $this->command->warn("⚠️ Provincia no encontrada: $nombreNormalizado");
                    continue;
                }

                foreach ($provinciaData['towns'] as $town) {
                    Poblacion::firstOrCreate([
                        'nombre' => $town['label'],
                        'codigo' => $town['code'],
                        'provincia_id' => $provincia->id,
                    ]);
                }
            }
        }
    }
}
