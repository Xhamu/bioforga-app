<?php

namespace Database\Seeders;

use App\Models\Pais;
use App\Models\Provincia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class PaisesYProvinciasSeeder extends Seeder
{
    public function run(): void
    {
        $json = File::get(resource_path('data/paises_provincias_seed.json'));
        $data = json_decode($json, true);

        foreach ($data as $paisData) {
            $pais = Pais::firstOrCreate(
                ['nombre' => $paisData['nombre']],
                ['codigo_iso' => $paisData['codigo_iso']]
            );

            foreach ($paisData['provincias'] as $provinciaNombre) {
                Provincia::firstOrCreate([
                    'nombre' => $provinciaNombre,
                    'pais_id' => $pais->id,
                ]);
            }
        }
    }
}
