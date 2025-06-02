<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $existing = $cart->products()->where('product_id', $productId)->first();

        if ($existing) {
            $cart->products()->updateExistingPivot($productId, [
                'quantity' => $existing->pivot->quantity + $quantity,
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
            return $this->sendError('Producto no encontrado en el carrito', 404);
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
