<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Products\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends BaseController
{
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->has('category')) {
            $categories = is_array($request->category)
                ? $request->category
                : explode(',', $request->category);

            $query->whereIn('category', $categories);
        }

        if ($request->has('material')) {
            $materials = is_array($request->material)
                ? $request->material
                : explode(',', $request->material);

            $query->whereIn('material', $materials);
        }

        if ($request->has('price_min')) {
            $query->where('price', '>=', $request->price_min);
        }

        if ($request->has('price_max')) {
            $query->where('price', '<=', $request->price_max);
        }

        if ($request->has('sort')) {
            switch ($request->sort) {
                case 'price-low':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price-high':
                    $query->orderBy('price', 'desc');
                    break;
                case 'rating':
                    $query->withAvg('reviews', 'rating')->orderBy('reviews_avg_rating', 'desc');
                    break;
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
            }
        }

        $products = $query->with('images', 'reviews')->search()->paginate(12);

        $products->getCollection()->transform(function ($product) {
            $product->images->transform(function ($image) {
                $originalPath = str_replace('/storage/', '', $image->url);
                $filename = pathinfo($originalPath, PATHINFO_FILENAME) . '.webp';
                $optimizedPath = 'products/optimized/' . $filename;

                $image->url = Storage::url(
                    Storage::disk('public')->exists($optimizedPath)
                        ? $optimizedPath
                        : $originalPath
                );

                return $image;
            });

            return $product;
        });

        return $this->sendResponse(new ProductResource($products), 'Lista de productos obtenida exitosamente.');
    }

    public function getByIds(Request $request)
    {
        $ids = $request->input('ids');

        if (!is_array($ids) || empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No se proporcionaron IDs válidos.',
            ], 400);
        }

        $products = Product::whereIn('id', $ids)
            ->with(['images', 'reviews'])
            ->get();

        $products->transform(function ($product) {
            $product->images->transform(function ($image) {
                $originalPath = str_replace('/storage/', '', $image->url);
                $filename = pathinfo($originalPath, PATHINFO_FILENAME) . '.webp';
                $optimizedPath = 'products/optimized/' . $filename;

                $image->url = Storage::url(
                    Storage::disk('public')->exists($optimizedPath)
                        ? $optimizedPath
                        : $originalPath
                );

                return $image;
            });

            return $product;
        });

        return $this->sendResponse(ProductResource::collection($products), 'Productos obtenidos exitosamente.');
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
                'material',
                'description',
                'in_stock',
                'stock_count',
                'category'
            ]));

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('products', 'public');
                    $url = Storage::url($path);
                    $product->images()->create(['url' => $url]);
                }
            }

            DB::commit();

            return $this->sendResponse(['product' => ProductResource::make($product)], 'Producto creado exitosamente.', 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->sendError('Error al crear el producto: ' . $e->getMessage(), [],  500);
        }
    }

    public function show(Product $product)
    {
        $product->load('images', 'reviews');

        // Sobrescribimos las URLs con las optimizadas si existen
        $product->images->transform(function ($image) {
            $originalPath = str_replace('/storage/', '', $image->url);
            $filename = pathinfo($originalPath, PATHINFO_FILENAME) . '.webp';
            $optimizedPath = 'products/optimized/' . $filename;

            $finalPath = Storage::disk('public')->exists($optimizedPath)
                ? $optimizedPath
                : $originalPath;

            $image->url = Storage::url($finalPath);
            return $image;
        });

        return $this->sendResponse(ProductResource::make($product), 'Producto obtenido exitosamente.');
    }

    public function slug($slug)
    {
        $product = Product::where('slug', $slug)
            ->with(['images', 'reviews'])
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        // Reemplazar imágenes por sus versiones .webp si existen
        $product->images->transform(function ($image) {
            $originalPath = str_replace('/storage/', '', $image->url);
            $filename = pathinfo($originalPath, PATHINFO_FILENAME) . '.webp';
            $optimizedPath = 'products/optimized/' . $filename;

            $finalPath = Storage::disk('public')->exists($optimizedPath)
                ? $optimizedPath
                : $originalPath;

            $image->url = Storage::url($finalPath);
            return $image;
        });

        return $this->sendResponse(
            ProductResource::make($product),
            'Producto obtenido exitosamente.'
        );
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
                'material',
                'description',
                'in_stock',
                'stock_count',
                'category'
            ]))->save();

            DB::commit();

            return $this->sendResponse(ProductResource::make($product), 'Producto actualizado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->sendError('Error al actualizar el producto: ' . $e->getMessage(), [],  500);
        }
    }

    public function destroy(Product $product)
    {
        DB::beginTransaction();

        try {
            // Eliminar imagen física si existe
            if ($product->image_url) {
                $path = str_replace('/storage/', '', $product->image_url); // Obtener ruta relativa
                Storage::disk('public')->delete($path); // Borrar archivo
            }

            // Eliminar el producto
            $product->delete();

            DB::commit();

            return $this->sendResponse([], 'Producto eliminado exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->sendError('Error al eliminar el producto: ' . $e->getMessage(), [], 500);
        }
    }
}
