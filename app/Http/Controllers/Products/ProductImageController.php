<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\BaseController;
use App\Models\Products\Product;
use App\Models\Products\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends BaseController
{
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'images' => 'required|array',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
        ]);

        $failedImages = [];

        foreach ($request->file('images') as $index => $image) {
            if (!$image->isValid()) {
                $errorMessage = $image->getErrorMessage();
                $originalName = $image->getClientOriginalName();

                Log::error("Falló la carga de imagen [{$index}] - {$originalName}: {$errorMessage}");

                $failedImages[] = [
                    'index' => $index,
                    'name' => $originalName,
                    'error' => $errorMessage,
                ];

                continue; // opcional: omitir esta imagen y seguir con las demás
            }

            try {
                // Guardar la imagen en storage/app/public/products
                $path = $image->store('products', 'public');
                $url = Storage::url($path);

                // Guardar en DB
                $product->images()->create(['url' => $url]);
            } catch (\Exception $e) {
                Log::error("Error al guardar imagen [{$index}] - {$image->getClientOriginalName()}: " . $e->getMessage());

                $failedImages[] = [
                    'index' => $index,
                    'name' => $image->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        if (count($failedImages)) {
            return response()->json([
                'message' => 'Algunas imágenes no se pudieron subir.',
                'errors' => [
                    'images' => $failedImages
                ]
            ], 422);
        }

        return $this->sendResponse([], 'Imágenes subidas correctamente.', 201);
    }

    public function destroy(Product $product, int $id)
    {
        $image = ProductImage::findOrFail($id); // Busca la imagen por ID

        // Extraer path relativo
        $path = str_replace('/storage/', '', $image->url);

        // Eliminar archivo físico si existe
        Storage::disk('public')->delete($path);

        // Eliminar de DB
        $image->delete();

        return $this->sendResponse([], 'Imagen eliminada correctamente.', 200);
    }
}
