<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
        return [
            'user_id' => 'required|exists:users,id',
            'address_id' => 'nullable|exists:addresses,id',
            'payment_method' => 'required|string',
            'payment_status' => 'required|string',
            'status' => 'required|string',
            'shipping_method' => 'nullable|string',
            'shipping_cost' => 'required|numeric',
            'tax' => 'required|numeric',
            'subtotal' => 'required|numeric',
            'total' => 'required|numeric',
            'transaction_id' => 'nullable|string',
            'products' => 'required|array|min:1',

            // Validación para cada producto en el array
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.product_name' => 'required|string|max:255',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.total' => 'required|numeric|min:0',
        ];
    }
}
