<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Image\Image;

class ConvertHeicImages extends Command
{
    protected $signature = 'images:convert-heic';
    protected $description = 'Convierte imágenes .heic a .jpg en public/archivos/horometros';

    public function handle()
    {
        $directory = public_path('archivos/horometros');
        $files = glob($directory . '/*.heic');

        if (empty($files)) {
            $this->info('No hay archivos .heic en la carpeta.');
            return;
        }

        foreach ($files as $originalPath) {
            $newPath = preg_replace('/\.heic$/i', '.jpg', $originalPath);

            try {
                Image::load($originalPath)->format('jpg')->save($newPath);
                unlink($originalPath);
                $this->info("Convertido: " . basename($originalPath));
            } catch (\Exception $e) {
                $this->error("Error al convertir " . basename($originalPath) . ": " . $e->getMessage());
            }
        }

        $this->info('Conversión completada.');
    }
}
