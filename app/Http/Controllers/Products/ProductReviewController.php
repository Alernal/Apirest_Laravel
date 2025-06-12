<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Models\Products\Product;
use App\Models\Products\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductReviewController extends BaseController
{
    public function index(Product $product)
    {
        $reviews = $product->reviews()->with('user:id,name')->latest()->get();

        return $this->sendResponse($reviews, 'Lista de reseñas cargada correctamente.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = ProductReview::updateOrCreate(
            [
                'product_id' => $request->product_id,
                'user_id' => Auth::id(),
            ],
            [
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]
        );

        return $this->sendResponse($review, 'Reseña guardada correctamente.', 201);
    }

    public function update(Request $request, ProductReview $review)
    {
        $this->authorize('update', $review);

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review->update([
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return $this->sendResponse($review, 'Reseña actualizada correctamente.');
    }

    public function destroy(ProductReview $review)
    {
        $this->authorize('delete', $review);

        $review->delete();

        return $this->sendResponse([], 'Reseña eliminada correctamente.');
    }
}
