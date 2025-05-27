<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\BaseController;
use App\Models\Products\Product;
use App\Models\Products\ProductFeature;
use Illuminate\Http\Request;

class ProductFeatureController extends BaseController
{
    public function store(Request $request, Product $product)
    {
        $request->validate([
            'features' => 'required|array',
            'features.*' => 'required|string|max:1000',
        ]);

        foreach ($request->features as $text) {
            $product->features()->create(['feature' => $text]);
        }

        return $this->sendResponse([], 'Características agregadas correctamente.', 201);
    }

    public function update(Request $request, ProductFeature $feature)
    {
        $request->validate([
            'feature' => 'required|string|max:1000',
        ]);

        $feature->update(['feature' => $request->feature]);

        return $this->sendResponse([], 'Característica actualizada correctamente.', 200);
    }

    public function destroy(ProductFeature $feature)
    {
        $feature->delete();

        return $this->sendResponse([], 'Característica eliminada correctamente.', 200);
    }
}
