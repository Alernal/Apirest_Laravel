<?php

namespace App\Console\Commands;

use App\Models\Products\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class UpdateWebpUrls extends Command
{
    protected $signature = 'images:update-webp-urls';
    protected $description = 'Actualiza la URL de imágenes a versiones .webp si existen';

    public function handle()
    {
        $images = ProductImage::all();
        $updated = 0;

        foreach ($images as $image) {
            // Saltar si ya es .webp
            if (str_ends_with($image->url, '.webp')) {
                $this->line("🔹 Ya es WebP: {$image->url}");
                continue;
            }

            // Convertir /storage/products/image.jpg → products/image.webp
            $relativePath = str_replace('/storage/', '', $image->url);
            $filename = pathinfo($relativePath, PATHINFO_FILENAME) . '.webp';
            $webpRelativePath = "products/{$filename}";
            $webpAbsolutePath = storage_path("app/public/{$webpRelativePath}");

            if (!file_exists($webpAbsolutePath)) {
                $this->warn("⚠️ No existe el archivo WebP: {$webpRelativePath}");
                continue;
            }

            // Actualizar la URL en la base de datos
            $image->url = Storage::url($webpRelativePath); // genera /storage/products/archivo.webp
            $image->save();

            $this->info("✔ URL actualizada a WebP: {$image->url}");
            $updated++;
        }

        $this->info("✅ Proceso terminado. URLs actualizadas: {$updated}");
        return Command::SUCCESS;
    }
}
