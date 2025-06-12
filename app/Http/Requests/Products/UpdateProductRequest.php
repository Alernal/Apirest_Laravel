<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'size' => ['nullable', 'string', 'max:10'],
            'material' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'in_stock' => ['sometimes', 'boolean'],
            'stock_count' => ['sometimes', 'integer', 'min:0'],
            'category' => ['nullable', 'string', 'max:50'],
        ];
    }
}
