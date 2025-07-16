<?php

namespace App\Console\Commands;

use App\Models\Products\ProductImage;
use Illuminate\Console\Command;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class GenerateWebpImages extends Command
{
    protected $signature = 'images:generate-webp';
    protected $description = 'Genera versiones optimizadas en WebP de imágenes existentes';

    public function handle()
    {
        $images = ProductImage::all();
        $count = 0;

        foreach ($images as $image) {
            // La URL en DB tiene formato: /storage/products/imagen.jpg
            $relativePath = str_replace('/storage/', '', $image->url);
            $originalPath = storage_path('app/public/' . $relativePath);

            if (!file_exists($originalPath)) {
                $this->warn("No existe: {$originalPath}");
                continue;
            }

            $filename = pathinfo($relativePath, PATHINFO_FILENAME) . '.webp';
            $webpDirectory = storage_path('app/public/products/optimized/');
            $webpPath = $webpDirectory . $filename;

            // Crear el directorio si no existe
            if (!file_exists($webpDirectory)) {
                mkdir($webpDirectory, 0755, true);
            }

            if (file_exists($webpPath)) {
                $this->line("Ya existe: {$filename}");
                continue;
            }

            try {
                // ✅ Crear instancia de ImageManager con driver gd
                $manager = new ImageManager(new Driver());


                $img = $manager->read($originalPath)
                    ->scaleDown(width: 800)     // Escala manteniendo la proporción
                    ->toWebp(quality: 75);      // Convierte a WebP con calidad 75

                $img->save($webpPath);

                $this->info("✔ Generado: {$filename}");
                $count++;
            } catch (\Throwable $e) {
                $this->error("Error con {$image->url}: " . $e->getMessage());
            }
        }

        $this->info("✅ Proceso terminado. Imágenes nuevas: {$count}");
        return Command::SUCCESS;
    }
}
