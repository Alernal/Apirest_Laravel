<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Products\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CartController extends BaseController
{
    public function index()
    {
        $user = Auth::user();

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $products = $cart->products()->get()->map(function ($product) {
            return [
                'product_id' => $product->id,
                'quantity' => $product->pivot->quantity,
            ];
        });

        return $this->sendResponse($products, 'Carrito cargado correctamente');
    }

    public function store($productId, Request $request)
    {
        $user = Auth::user();

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $quantity = max((int) $request->input('quantity', 1), 1);

        // Obtener el producto con su stock
        $product = Product::find($productId);

        if (!$product) {
            return $this->sendError('Producto no encontrado', [], 404);
        }

        // Verificar si ya está en el carrito
        $existing = $cart->products()->where('product_id', $productId)->first();

        // Calcular la cantidad total que tendría en el carrito
        $currentQty = $existing ? $existing->pivot->quantity : 0;
        $newTotalQty = $currentQty + $quantity;

        if ($newTotalQty > $product->stock_count) {
            return $this->sendError(
                "No se puede agregar el producto '{$product->name}' al carrito. Stock disponible: {$product->stock_count}, cantidad solicitada: {$newTotalQty}.",
                [],
                422
            );
        }

        // Agregar o actualizar en el carrito
        if ($existing) {
            $cart->products()->updateExistingPivot($productId, [
                'quantity' => $newTotalQty,
            ]);
        } else {
            $cart->products()->attach($productId, ['quantity' => $quantity]);
        }

        return $this->sendResponse(null, 'Producto añadido al carrito');
    }

    public function decrement($productId)
    {
        $user = Auth::user();

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $product = $cart->products()->where('product_id', $productId)->first();

        if (!$product) {
            return $this->sendError('Producto no encontrado en el carrito', [], 404);
        }

        $currentQuantity = $product->pivot->quantity;

        if ($currentQuantity <= 1) {
            // Elimina el producto si ya es 1 o menos
            $cart->products()->detach($productId);
        } else {
            $cart->products()->updateExistingPivot($productId, [
                'quantity' => $currentQuantity - 1,
            ]);
        }

        return $this->sendResponse(null, 'Cantidad disminuida');
    }

    public function clear()
    {
        $user = Auth::user();

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $cart->products()->detach(); // Elimina todos los productos

        return $this->sendResponse(null, 'Carrito vaciado');
    }

    public function destroy($productId)
    {
        $user = Auth::user();

        $cart = Cart::firstOrCreate(['user_id' => $user->id]);

        $cart->products()->detach($productId);

        return $this->sendResponse(null, 'Producto eliminado del carrito');
    }
}
