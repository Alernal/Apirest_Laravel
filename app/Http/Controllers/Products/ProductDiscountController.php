<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\BaseController;
use App\Models\Products\Product;
use Illuminate\Http\Request;

class ProductDiscountController extends BaseController
{
    public function store(Request $request, Product $product)
    {
        $request->validate([
            'discount_value' => 'required|numeric|min:0',
            'discount_type' => 'required|in:percent,fixed',
            'start_date' => 'nullable|date|before_or_equal:end_date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $now = now();

        $existing = $product->discount()
            ->whereDate('start_date', '<=', $now)
            ->whereDate('end_date', '>=', $now)
            ->first();

        if ($existing) {
            return $this->sendResponse([], 'Este producto ya tiene un descuento activo.', 422);
        }

        $product->discount()->create($request->only([
            'discount_value',
            'discount_type',
            'start_date',
            'end_date'
        ]));

        return $this->sendResponse([], 'Descuento agregado correctamente.', 201);
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'discount_value' => 'required|numeric|min:0',
            'discount_type' => 'required|in:percent,fixed',
            'start_date' => 'nullable|date|before_or_equal:end_date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $newStart = $request->start_date ? now()->parse($request->start_date) : null;
        $newEnd = $request->end_date ? now()->parse($request->end_date) : null;

        $currentDiscount = $product->discount;

        // Validar si el nuevo rango sigue activo y ya existe uno activo
        $now = now();
        $isOverlapping = $newStart && $newEnd && $newStart <= $now && $newEnd >= $now;

        if ($currentDiscount && $isOverlapping) {
            // Ya existe y las fechas lo mantendrían activo ahora mismo
            return $this->sendResponse([], 'Este descuento ya está activo en este rango de fechas.', 422);
        }

        $product->discount()->updateOrCreate([], $request->only([
            'discount_value',
            'discount_type',
            'start_date',
            'end_date'
        ]));

        return $this->sendResponse([], 'Descuento actualizado correctamente.');
    }

    public function destroy(Product $product)
    {
        $product->discount()->delete();

        return $this->sendResponse([], 'Descuento eliminado correctamente.', 200);
    }
}
