<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\ProductResorce;
use App\Models\Products\Product;
use Illuminate\Support\Facades\DB;

class ProductController extends BaseController
{
    public function index()
    {
        $products = Product::paginate();

        return $this->sendResponse(ProductResorce::collection($products), 'Lista de productos obtenida exitosamente.');
    }

    public function store(StoreProductRequest $request)
    {
        DB::beginTransaction();

        try {
            // 1. Crear el producto
            $product = Product::create($request->only([
                'slug',
                'name',
                'price',
                'original_price',
                'size',
                'color',
                'description',
                'in_stock',
                'stock_count'
            ]));

            // 3. Asociar características
            if ($request->filled('features')) {
                foreach ($request->features as $feature) {
                    $product->features()->create(['feature' => $feature]);
                }
            }

            DB::commit();

            return $this->sendResponse(['product' => ProductResorce::make($product)], 'Producto creado exitosamente.', 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->sendError('Error al crear el producto: ' . $e->getMessage(), 500);
        }
    }

    public function show(Product $product)
    {
        return $this->sendResponse(ProductResorce::make($product), 'Producto obtenido exitosamente.');
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        DB::beginTransaction();

        try {
            // Actualizar el producto
            $product->fill($request->only([
                'slug',
                'name',
                'price',
                'original_price',
                'size',
                'color',
                'description',
                'in_stock',
                'stock_count'
            ]))->save();

            DB::commit();

            return $this->sendResponse(ProductResorce::make($product), 'Producto actualizado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->sendError('Error al actualizar el producto: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Product $product)
    {
        DB::beginTransaction();

        try {
            // Eliminar el producto
            $product->delete();

            DB::commit();

            return $this->sendResponse([], 'Producto eliminado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->sendError('Error al eliminar el producto: ' . $e->getMessage(), 500);
        }
    }
}
