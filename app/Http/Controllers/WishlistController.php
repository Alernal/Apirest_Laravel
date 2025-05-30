<?php

namespace App\Http\Controllers;

use App\Models\Products\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistController extends BaseController
{

    public function index()
    {
        $user = Auth::user();

        $wishlist = Wishlist::where('user_id', $user->id)
            ->get(['product_id']);

        return $this->sendResponse($wishlist, 'Lista de deseos obtenida correctamente');
    }

    public function store($productId)
    {
        $user = Auth::user();

        $wishlist = Wishlist::firstOrCreate([
            'user_id' => $user->id,
            'product_id' => $productId,
        ]);

        return $this->sendResponse($wishlist, 'Producto añadido a favoritos', 201);
    }

    public function destroy($productId)
    {
        $user = Auth::user();

        $deleted = Wishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->delete();

        return $this->sendResponse(null, 'Producto eliminado de favoritos');
    }
}
