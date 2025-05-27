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
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        foreach ($request->file('images') as $image) {
            // Guardar la imagen en storage/app/public/products
            $path = $image->store('products', 'public');

            // Obtener la URL pública
            $url = Storage::url($path);

            // Guardar en DB
            $product->images()->create(['url' => $url]);
        }

        return $this->sendResponse([], 'Imágenes subidas correctamente.', 201);
    }

    public function destroy(ProductImage $image)
    {
        // Extraer path relativo
        $path = str_replace('/storage/', '', $image->url);

        // Eliminar archivo físico si existe
        Storage::disk('public')->delete($path);

        // Eliminar de DB
        $image->delete();

        return $this->sendResponse([], 'Imagen eliminada correctamente.', 200);
    }
}
