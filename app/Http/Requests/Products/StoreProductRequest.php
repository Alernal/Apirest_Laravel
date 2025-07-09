<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */

    public function rules(): array
    {
        $productId = $this->product?->id;

        return [
            // Producto
            'slug' => 'required|string|max:255|unique:products,slug,' . $productId,
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'size' => 'nullable|string|max:10',
            'material' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'in_stock' => 'boolean',
            'stock_count' => 'required|integer|min:0',
            'category' => 'nullable|string|max:50',

            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:51200',

        ];
    }
}
