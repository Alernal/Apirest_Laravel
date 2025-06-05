<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
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
            'user_id' => 'sometimes|exists:users,id',
            'address_id' => 'nullable|exists:addresses,id',
            'payment_method' => 'sometimes|string',
            'payment_status' => 'sometimes|string',
            'status' => 'sometimes|string',
            'shipping_method' => 'nullable|string',
            'shipping_cost' => 'sometimes|numeric',
            'tax' => 'sometimes|numeric',
            'subtotal' => 'sometimes|numeric',
            'total' => 'sometimes|numeric',
            'transaction_id' => 'nullable|string',
        ];
    }
}
